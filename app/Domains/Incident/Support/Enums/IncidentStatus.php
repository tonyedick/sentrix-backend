<?php

declare(strict_types=1);

namespace App\Domains\Incident\Support\Enums;

/**
 * Incident workflow states with an explicit transition graph.
 *
 *   open          → newly recorded
 *   investigating → actively being worked
 *   escalated     → raised to a higher tier
 *   resolved      → handled, pending closure
 *   closed        → terminal
 */
enum IncidentStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * States this state may legally transition into.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Investigating, self::Escalated, self::Resolved, self::Closed],
            self::Investigating => [self::Escalated, self::Resolved, self::Closed],
            self::Escalated => [self::Investigating, self::Resolved, self::Closed],
            self::Resolved => [self::Investigating, self::Closed],
            self::Closed => [],
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
