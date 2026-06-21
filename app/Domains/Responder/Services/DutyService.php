<?php

declare(strict_types=1);

namespace App\Domains\Responder\Services;

use App\Domains\Responder\Models\DutyShift;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Support\Enums\DutyShiftStatus;
use App\Domains\Responder\Support\Enums\ResponderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns duty scheduling. Shifts are activated at their start (putting the
 * responder on duty) and closed at their end by the duty sweep, which is
 * idempotent and row-atomic so it is safe to run every minute and concurrently.
 */
final readonly class DutyService
{
    public function __construct(private ResponderService $responders) {}

    public function schedule(Responder $responder, Carbon $startsAt, Carbon $endsAt, string $source = 'manual'): DutyShift
    {
        return DutyShift::create([
            'responder_id' => $responder->getKey(),
            'organization_id' => $responder->organization_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => DutyShiftStatus::Scheduled,
            'source' => $source,
        ]);
    }

    public function cancel(DutyShift $shift): DutyShift
    {
        return DB::transaction(function () use ($shift): DutyShift {
            $wasActive = $shift->status === DutyShiftStatus::Active;
            $shift->update(['status' => DutyShiftStatus::Cancelled]);

            if ($wasActive) {
                $this->takeOffDutyIfIdle($shift->responder);
            }

            return $shift->refresh();
        });
    }

    /**
     * Activate shifts whose window has opened and close those whose window has
     * ended. Idempotent. Returns [activated, closed] counts.
     *
     * @return array{activated: int, closed: int}
     */
    public function processDueShifts(): array
    {
        $now = CarbonImmutable::now();
        $activated = 0;
        $closed = 0;

        DutyShift::query()
            ->where('status', DutyShiftStatus::Scheduled->value)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->with('responder')
            ->each(function (DutyShift $shift) use (&$activated): void {
                DB::transaction(function () use ($shift): void {
                    $shift->update(['status' => DutyShiftStatus::Active]);

                    $responder = $shift->responder;

                    if ($responder !== null && $responder->status === ResponderStatus::OffDuty) {
                        $this->responders->goOnDuty($responder);
                    }
                });
                $activated++;
            });

        DutyShift::query()
            ->where('status', DutyShiftStatus::Active->value)
            ->where('ends_at', '<=', $now)
            ->with('responder')
            ->each(function (DutyShift $shift) use (&$closed): void {
                DB::transaction(function () use ($shift): void {
                    $shift->update(['status' => DutyShiftStatus::Completed]);

                    if ($shift->responder !== null) {
                        $this->takeOffDutyIfIdle($shift->responder);
                    }
                });
                $closed++;
            });

        return ['activated' => $activated, 'closed' => $closed];
    }

    /**
     * Send a responder off duty only if they are idle — never abandon an active
     * assignment (engaged responders stay on until released).
     */
    private function takeOffDutyIfIdle(Responder $responder): void
    {
        if (in_array($responder->status, [ResponderStatus::Available, ResponderStatus::Unavailable], true)) {
            $this->responders->goOffDuty($responder);
        }
    }
}
