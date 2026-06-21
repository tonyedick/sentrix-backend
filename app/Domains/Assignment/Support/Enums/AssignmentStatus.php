<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Support\Enums;

/**
 * Aggregate status of an assignment (the incident-scoped coordination record).
 * Derived from its responder lines and required composition by the service,
 * except `completed`/`cancelled` which are set explicitly.
 *
 *   pending          → created, no offers out yet
 *   dispatching      → offers outstanding, none accepted
 *   partially_filled → some accepted, but the required composition isn't met
 *   filled           → primary (+ required supporting) accepted
 *   active           → primary is en route / on scene
 *   escalated        → flagged for escalation (see escalation slice)
 *   completed        → terminal
 *   cancelled        → terminal
 */
enum AssignmentStatus: string
{
    case Pending = 'pending';
    case Dispatching = 'dispatching';
    case PartiallyFilled = 'partially_filled';
    case Filled = 'filled';
    case Active = 'active';
    case Escalated = 'escalated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
