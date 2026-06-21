<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Events\GateScanned;
use App\Domains\Access\Models\GateEvent;
use App\Domains\Access\Models\Pass;
use App\Domains\Access\Support\Enums\GateDirection;
use App\Domains\Access\Support\Enums\GateResult;
use App\Domains\Access\Support\Enums\PassStatus;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verifies visitor codes at a gate and maintains the immutable gate log.
 *
 * Every scan appends exactly one {@see GateEvent} (granted or denied), so the
 * log is a complete, tamper-evident record of who attempted entry and when.
 */
final readonly class GateService
{
    /**
     * Verify a code at a gate. Always appends a gate event and returns it
     * together with the matched pass (null when the code is unknown).
     *
     * Concurrency: the pass row is locked for the duration so two officers
     * cannot both consume the same single-use pass — the loser sees it already
     * consumed and is denied.
     *
     * @return array{event: GateEvent, pass: ?Pass}
     */
    public function scan(
        Organization $organization,
        string $code,
        string $gate,
        GateDirection $direction,
        User $officer,
    ): array {
        return DB::transaction(function () use ($organization, $code, $gate, $direction, $officer): array {
            /** @var Pass|null $pass */
            $pass = Pass::query()
                ->where('organization_id', $organization->getKey())
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($pass === null) {
                return [
                    'event' => $this->append($organization, null, $officer, $gate, $direction, GateResult::Denied, 'unknown_code', null),
                    'pass' => null,
                ];
            }

            $denial = $pass->denialReason();

            if ($denial !== null) {
                return [
                    'event' => $this->append($organization, $pass, $officer, $gate, $direction, GateResult::Denied, $denial, $pass->visitor_name),
                    'pass' => $pass,
                ];
            }

            // Granted: count the entry and consume a single-use pass.
            $pass->uses_count += 1;
            if ($pass->type->isSingleUse() && $direction === GateDirection::In) {
                $pass->status = PassStatus::Consumed;
            }
            $pass->save();

            return [
                'event' => $this->append($organization, $pass, $officer, $gate, $direction, GateResult::Granted, null, $pass->visitor_name),
                'pass' => $pass,
            ];
        });
    }

    /**
     * Append a manual gate entry (e.g. a known staff member waved through, or a
     * non-code visitor logged by the officer).
     */
    public function log(
        Organization $organization,
        User $officer,
        string $gate,
        GateDirection $direction,
        ?string $visitorName,
        GateResult $result = GateResult::Granted,
    ): GateEvent {
        return DB::transaction(fn (): GateEvent => $this->append(
            $organization,
            null,
            $officer,
            $gate,
            $direction,
            $result,
            'manual',
            $visitorName,
        ));
    }

    /**
     * Persist one gate event and emit the broadcast/audit event.
     */
    private function append(
        Organization $organization,
        ?Pass $pass,
        User $officer,
        string $gate,
        GateDirection $direction,
        GateResult $result,
        ?string $reason,
        ?string $visitorName,
    ): GateEvent {
        $event = new GateEvent([
            'organization_id' => $organization->getKey(),
            'pass_id' => $pass?->getKey(),
            'officer_id' => $officer->getKey(),
            'gate' => $gate,
            'direction' => $direction,
            'result' => $result,
            'reason' => $reason,
            'visitor_name' => $visitorName,
            'recorded_at' => Carbon::now(),
        ]);
        $event->save();

        event(new GateScanned($event, $officer->getKey(), [
            'gate' => $gate,
            'direction' => $direction->value,
            'result' => $result->value,
            'reason' => $reason,
            'pass_id' => $pass?->getKey(),
        ]));

        return $event;
    }
}
