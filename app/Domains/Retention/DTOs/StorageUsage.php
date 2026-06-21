<?php

declare(strict_types=1);

namespace App\Domains\Retention\DTOs;

use App\Domains\Shared\Data\DataTransferObject;

/**
 * Computed storage usage rollup for an organization vs its plan quota. A pure
 * read model — assembled by RetentionService::usage and projected by the
 * controller into a plain `data` array (no Resource, no state change).
 */
final class StorageUsage extends DataTransferObject
{
    /**
     * @param  array<string, int>  $countsByTier
     */
    public function __construct(
        public readonly string $plan,
        public readonly int $quotaGb,
        public readonly array $countsByTier,
        public readonly int $total,
        public readonly int $onLegalHold,
        public readonly int $archived,
        public readonly int $estimatedBytes,
        public readonly float $pctOfQuota,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'quota_gb' => $this->quotaGb,
            'counts_by_tier' => $this->countsByTier,
            'total' => $this->total,
            'on_legal_hold' => $this->onLegalHold,
            'archived' => $this->archived,
            'estimated_bytes' => $this->estimatedBytes,
            'pct_of_quota' => $this->pctOfQuota,
        ];
    }
}
