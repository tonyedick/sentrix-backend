<?php

declare(strict_types=1);

namespace App\Domains\Responder\Support\Enums;

/**
 * A responder's operational disposition, with an explicit transition graph.
 *
 *   off_duty    → not on shift; not assignable (default)
 *   available   → on duty, idle, assignable
 *   unavailable → on duty but temporarily not assignable (break/busy)
 *   engaged     → actively handling an assignment
 *   suspended   → administratively disabled
 *
 * "Available for dispatch" is derived, not stored: it holds when status is
 * {@see self::Available} (presence and active-assignment checks refine it in the
 * dispatch and presence slices). The on-duty boolean on the responder mirrors
 * membership of the on-duty set {available, unavailable, engaged}.
 */
enum ResponderStatus: string
{
    case OffDuty = 'off_duty';
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Engaged = 'engaged';
    case Suspended = 'suspended';

    /**
     * Whether this status counts as being on duty (reachable/rostered).
     */
    public function isOnDuty(): bool
    {
        return match ($this) {
            self::Available, self::Unavailable, self::Engaged => true,
            self::OffDuty, self::Suspended => false,
        };
    }

    public function isAssignable(): bool
    {
        return $this === self::Available;
    }

    /**
     * States this state may legally transition into.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OffDuty => [self::Available, self::Suspended],
            self::Available => [self::Unavailable, self::Engaged, self::OffDuty, self::Suspended],
            self::Unavailable => [self::Available, self::OffDuty],
            // A responder must be released from an assignment (→ available) before
            // going off duty; they cannot abandon an active assignment directly.
            self::Engaged => [self::Available, self::Unavailable],
            self::Suspended => [self::OffDuty],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
