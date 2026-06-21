<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Services;

use App\Domains\DriverOnboarding\DTOs\BookInspectionData;
use App\Domains\DriverOnboarding\DTOs\RegisterDriverData;
use App\Domains\DriverOnboarding\DTOs\UploadDocumentData;
use App\Domains\DriverOnboarding\Events\DriverActivated;
use App\Domains\DriverOnboarding\Events\DriverDecisionRecorded;
use App\Domains\DriverOnboarding\Events\DriverRegistered;
use App\Domains\DriverOnboarding\Models\Driver;
use App\Domains\DriverOnboarding\Models\DriverDocument;
use App\Domains\DriverOnboarding\Models\Inspection;
use App\Domains\DriverOnboarding\Models\VettingCenter;
use App\Domains\DriverOnboarding\Support\Enums\DocumentStatus;
use App\Domains\DriverOnboarding\Support\Enums\DriverAvailability;
use App\Domains\DriverOnboarding\Support\Enums\DriverStage;
use App\Domains\DriverOnboarding\Support\Enums\InspectionStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

/**
 * Driver onboarding state machine + staff review. User-scoped: a Driver belongs
 * 1:1 to its user. Stage progression:
 *
 *   documents_review --(staff approve docs)--> documents_approved
 *   documents_approved --(driver books)--> inspection_booked
 *   inspection_booked --(staff inspection pass)--> active
 *   inspection_booked --(staff inspection fail)--> documents_approved (re-book)
 *   documents_review --(staff reject)--> rejected
 *   rejected --(driver re-uploads a doc)--> documents_review
 *
 * Mirrors the SentrixGo rides router driver/* and staff/* endpoints, adapted to
 * Laravel + UUIDs. Concurrency-sensitive transitions lockForUpdate the driver row.
 */
final readonly class DriverService
{
    /**
     * Standard Sentrix security kit installed at the vetting center on pass.
     *
     * @var list<string>
     */
    private const STANDARD_HARDWARE_KIT = ['gps', 'dashcam', 'panic_button', 'immobilizer'];

    public function __construct(private DatabaseManager $db) {}

    // ---- Driver side -------------------------------------------------------

    /**
     * Register the caller as a driver. One profile per user (409 if one exists).
     *
     * TODO: gate behind the future Verification domain (KYC) — SentrixGo blocks
     * unverified users here. For now registration is open; KYC is not yet a domain.
     */
    public function register(User $user, RegisterDriverData $data): Driver
    {
        return $this->db->transaction(function () use ($user, $data): Driver {
            if (Driver::query()->where('user_id', $user->getKey())->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'driver' => ['You are already registered as a driver.'],
                ])->status(409);
            }

            $driver = Driver::create([
                'user_id' => $user->getKey(),
                'stage' => DriverStage::DocumentsReview->value,
                'availability' => DriverAvailability::Offline->value,
                'review_note' => 'Documents pending review by Sentrix Staff.',
                // Fleet safety score is an ecosystem signal from Sentrix Fleet.
                // Env-gated + stubbed to null until Fleet is wired.
                'fleet_safety_score' => $this->fleetSafetyScore($user),
                'vehicle_make' => $data->vehicleMake,
                'vehicle_model' => $data->vehicleModel,
                'vehicle_plate' => $data->vehiclePlate,
                'vehicle_color' => $data->vehicleColor,
            ]);

            event(new DriverRegistered($driver));

            return $driver;
        });
    }

    /**
     * Upload (or add) one document for staff review. Re-uploading after a
     * rejection puts the application back under document review.
     */
    public function uploadDocument(Driver $driver, UploadDocumentData $data): DriverDocument
    {
        return $this->db->transaction(function () use ($driver, $data): DriverDocument {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            $document = DriverDocument::create([
                'driver_id' => $locked->getKey(),
                'type' => $data->type->value,
                'url' => $data->url,
                'status' => DocumentStatus::Pending->value,
            ]);

            if ($locked->stage === DriverStage::Rejected) {
                $locked->forceFill([
                    'stage' => DriverStage::DocumentsReview->value,
                    'review_note' => 'Re-submitted documents pending review by Sentrix Staff.',
                ])->save();
            }

            return $document;
        });
    }

    /**
     * Set the driver's availability. Only an `active` driver may go online — the
     * safety boundary (must finish docs + the in-person inspection first).
     */
    public function setOnline(Driver $driver, bool $online): Driver
    {
        return $this->db->transaction(function () use ($driver, $online): Driver {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            if ($online && $locked->stage !== DriverStage::Active) {
                throw ValidationException::withMessages([
                    'driver_not_active' => ['Finish document review and your in-person vehicle inspection before going online.'],
                ]);
            }

            $locked->forceFill([
                'availability' => $online
                    ? DriverAvailability::Online->value
                    : DriverAvailability::Offline->value,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Book a physical inspection slot. Requires documents_approved.
     */
    public function bookInspection(Driver $driver, BookInspectionData $data): Inspection
    {
        return $this->db->transaction(function () use ($driver, $data): Inspection {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->stage !== DriverStage::DocumentsApproved) {
                throw ValidationException::withMessages([
                    'stage' => ['Your documents must be approved before you can book an inspection.'],
                ]);
            }

            /** @var VettingCenter $center */
            $center = VettingCenter::query()->whereKey($data->vettingCenterId)->firstOrFail();

            $inspection = Inspection::create([
                'driver_id' => $locked->getKey(),
                'vetting_center_id' => $center->getKey(),
                'booked_slot' => $data->slot,
                'status' => InspectionStatus::Booked->value,
            ]);

            $locked->forceFill([
                'stage' => DriverStage::InspectionBooked->value,
                'review_note' => "Inspection booked at {$center->name} ({$data->slot}). Bring your vehicle and original documents.",
            ])->save();

            return $inspection;
        });
    }

    // ---- Staff side --------------------------------------------------------

    /**
     * Staff decision on a single document. The document must belong to $driver.
     */
    public function reviewDocument(Driver $driver, DriverDocument $document, string $decision, ?string $note, User $staff): DriverDocument
    {
        return $this->db->transaction(function () use ($document, $decision, $note, $staff): DriverDocument {
            /** @var DriverDocument $locked */
            $locked = DriverDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => $decision === 'approve'
                    ? DocumentStatus::Approved->value
                    : DocumentStatus::Rejected->value,
                'note' => $note,
                'reviewed_by' => $staff->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Staff document-review decision on the driver overall. Approving docs does
     * NOT make a driver live — it unlocks the in-person inspection. Rejecting
     * sends the application to `rejected`.
     */
    public function recordDecision(Driver $driver, string $decision, ?string $note, User $staff): Driver
    {
        return $this->db->transaction(function () use ($driver, $decision, $note, $staff): Driver {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            if ($decision === 'approve') {
                $locked->forceFill([
                    'stage' => DriverStage::DocumentsApproved->value,
                    'reviewer_id' => $staff->getKey(),
                    'review_note' => $note ?? 'Documents approved. Book your in-person vehicle inspection to continue.',
                ])->save();
            } else {
                $locked->forceFill([
                    'stage' => DriverStage::Rejected->value,
                    'availability' => DriverAvailability::Offline->value,
                    'reviewer_id' => $staff->getKey(),
                    'review_note' => $note ?? 'Application rejected. Please re-check your documents and re-submit.',
                ])->save();
            }

            $locked->refresh();

            event(new DriverDecisionRecorded($locked, $decision, $staff->getKey()));

            return $locked;
        });
    }

    /**
     * Staff records the in-person inspection outcome. On pass: install the
     * standard Sentrix hardware kit, activate the driver, mark the inspection
     * passed. On fail: send the driver back to documents_approved to re-book.
     *
     * Concurrency-safe: locks the driver row and re-reads before mutating.
     *
     * @param array<int|string, mixed>|null $checklist
     */
    public function recordInspection(Driver $driver, string $decision, ?array $checklist, User $staff): Inspection
    {
        return $this->db->transaction(function () use ($driver, $decision, $checklist, $staff): Inspection {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            /** @var Inspection $inspection */
            $inspection = Inspection::query()
                ->where('driver_id', $locked->getKey())
                ->where('status', InspectionStatus::Booked->value)
                ->lockForUpdate()
                ->latest('created_at')
                ->firstOrFail();

            if ($decision === 'pass') {
                $inspection->forceFill([
                    'status' => InspectionStatus::Passed->value,
                    'checklist' => $checklist,
                    'decided_by' => $staff->getKey(),
                    'decided_at' => now(),
                ])->save();

                $locked->forceFill([
                    'stage' => DriverStage::Active->value,
                    'installed_hardware' => self::STANDARD_HARDWARE_KIT,
                    'review_note' => "Vehicle vetted and Sentrix-secured. You're live — welcome to the network.",
                ])->save();

                $locked->refresh();

                event(new DriverActivated($locked, $staff->getKey()));
            } else {
                $inspection->forceFill([
                    'status' => InspectionStatus::Failed->value,
                    'checklist' => $checklist,
                    'decided_by' => $staff->getKey(),
                    'decided_at' => now(),
                ])->save();

                $locked->forceFill([
                    'stage' => DriverStage::DocumentsApproved->value,
                    'review_note' => 'Inspection did not pass. Resolve the noted items and re-book.',
                ])->save();
            }

            return $inspection->refresh();
        });
    }

    /**
     * Ecosystem vetting: the telematics-backed safety score Sentrix Fleet exposes
     * (the same signal the insurance engine prices on). Env-gated and stubbed to
     * null until Fleet is wired into this monolith.
     *
     * TODO: when Fleet integration lands, resolve via a FleetClient keyed on the
     * user's Sentrix id and return the live score.
     */
    private function fleetSafetyScore(User $user): ?int
    {
        if (! (bool) config('sentrix.rides.fleet_enabled', false)) {
            return null;
        }

        // Fleet is "enabled" but no client is wired yet — still null until the
        // FleetClient lands. Kept env/config-gated so the seam is explicit.
        return null;
    }
}
