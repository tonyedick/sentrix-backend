<?php

declare(strict_types=1);

namespace App\Domains\Responder\Services;

use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Events\ResponderCertificationExpired;
use App\Domains\Responder\Events\ResponderCertificationExpiring;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Models\ResponderCertification;
use App\Domains\Responder\Models\Skill;
use App\Domains\Responder\Support\Enums\CertificationStatus;
use App\Domains\Responder\Support\Enums\SkillProficiency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manages responder capabilities: the organization's skill catalogue, the skills
 * a responder holds, and their certifications (including the expiry sweep).
 */
final readonly class ResponderCapabilityService
{
    public function createSkill(Organization $organization, string $code, string $name): Skill
    {
        return Skill::firstOrCreate(
            ['organization_id' => $organization->getKey(), 'code' => $code],
            ['name' => $name],
        );
    }

    public function attachSkill(Responder $responder, Skill $skill, SkillProficiency $proficiency): void
    {
        $responder->skills()->syncWithoutDetaching([
            $skill->getKey() => ['proficiency' => $proficiency->value],
        ]);
    }

    public function detachSkill(Responder $responder, Skill $skill): void
    {
        $responder->skills()->detach($skill->getKey());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addCertification(Responder $responder, array $attributes): ResponderCertification
    {
        return ResponderCertification::create([
            'responder_id' => $responder->getKey(),
            'organization_id' => $responder->organization_id,
            'name' => $attributes['name'],
            'authority' => $attributes['authority'] ?? null,
            'issued_at' => $attributes['issued_at'] ?? null,
            'expires_at' => $attributes['expires_at'] ?? null,
            'status' => $attributes['status'] ?? CertificationStatus::Pending->value,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function setCertificationStatus(ResponderCertification $certification, CertificationStatus $status): ResponderCertification
    {
        $certification->update(['status' => $status]);

        return $certification->refresh();
    }

    /**
     * Sweep certifications: warn on those approaching expiry (once), and lapse
     * those past it. Idempotent — re-running never double-notifies or
     * double-emits. Returns [expiring, expired] counts.
     *
     * @return array{expiring: int, expired: int}
     */
    public function sweepExpiry(): array
    {
        $now = CarbonImmutable::now();
        $warnDays = (int) config('sentrix.responders.certification_expiry_warning_days', 30);
        $window = $now->addDays($warnDays);

        $expiring = 0;
        $expired = 0;

        // Lapse verified certs that are now past expiry.
        ResponderCertification::query()
            ->where('status', CertificationStatus::Verified->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->with('responder')
            ->each(function (ResponderCertification $cert) use (&$expired): void {
                DB::transaction(function () use ($cert): void {
                    $cert->update(['status' => CertificationStatus::Expired]);

                    if ($cert->responder !== null) {
                        event(new ResponderCertificationExpired($cert->responder, null, [
                            'certification_id' => $cert->getKey(),
                            'name' => $cert->name,
                        ]));
                    }
                });
                $expired++;
            });

        // Warn (once) on verified certs approaching expiry within the window.
        ResponderCertification::query()
            ->where('status', CertificationStatus::Verified->value)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $window])
            ->with('responder')
            ->each(function (ResponderCertification $cert) use (&$expiring): void {
                $metadata = $cert->metadata ?? [];

                if (($metadata['expiring_notified'] ?? false) === true) {
                    return;
                }

                DB::transaction(function () use ($cert, $metadata): void {
                    $metadata['expiring_notified'] = true;
                    $cert->update(['metadata' => $metadata]);

                    if ($cert->responder !== null) {
                        event(new ResponderCertificationExpiring($cert->responder, null, [
                            'certification_id' => $cert->getKey(),
                            'name' => $cert->name,
                            'expires_at' => $cert->expires_at?->toIso8601String(),
                        ]));
                    }
                });
                $expiring++;
            });

        return ['expiring' => $expiring, 'expired' => $expired];
    }
}
