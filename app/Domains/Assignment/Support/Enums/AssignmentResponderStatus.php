<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Support\Enums;

/**
 * Lifecycle of a single responder's participation in an assignment (the line
 * item), with an explicit transition graph.
 *
 *   offered    → dispatched, awaiting acceptance
 *   accepted   → responder accepted (now engaged)
 *   en_route   → travelling to the scene
 *   on_scene   → arrived
 *   completed  → handled (terminal)
 *   declined   → responder declined the offer (terminal)
 *   timed_out  → offer expired unaccepted (terminal)
 *   stood_down → withdrawn during reassignment/cancellation (terminal)
 *   cancelled  → assignment cancelled (terminal)
 *
 * The "active" set {offered, accepted, en_route, on_scene} is what the partial
 * unique indexes enforce (one active line per responder; one active primary per
 * assignment).
 */
enum AssignmentResponderStatus: string
{
    case Offered = 'offered';
    case Accepted = 'accepted';
    case EnRoute = 'en_route';
    case OnScene = 'on_scene';
    case Completed = 'completed';
    case Declined = 'declined';
    case TimedOut = 'timed_out';
    case StoodDown = 'stood_down';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Offered, self::Accepted, self::EnRoute, self::OnScene], true);
    }

    /**
     * Whether the responder is committed (accepted and engaged, not merely offered).
     */
    public function isCommitted(): bool
    {
        return in_array($this, [self::Accepted, self::EnRoute, self::OnScene], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Declined, self::TimedOut, self::StoodDown, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Offered => [self::Accepted, self::Declined, self::TimedOut, self::StoodDown, self::Cancelled],
            self::Accepted => [self::EnRoute, self::Completed, self::StoodDown, self::Cancelled],
            self::EnRoute => [self::OnScene, self::Completed, self::StoodDown, self::Cancelled],
            self::OnScene => [self::Completed, self::StoodDown, self::Cancelled],
            self::Completed, self::Declined, self::TimedOut, self::StoodDown, self::Cancelled => [],
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

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isActive()),
        );
    }
}
