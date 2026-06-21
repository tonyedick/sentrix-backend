<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Http\Resources;

use App\Domains\Ledger\Models\LedgerSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerSource
 *
 * Public projection of a source. The raw ingest key is NEVER exposed here; only
 * a short masked fingerprint of its hash is surfaced for operator recognition.
 */
final class LedgerSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'product' => $this->product,
            'kind' => $this->kind->value,
            'organization_id' => $this->organization_id,
            'status' => $this->status->value,
            'key_fingerprint' => $this->maskedKey(),
            'last_write_at' => $this->last_write_at?->toIso8601String(),
            'write_count' => $this->write_count,
            'stale_alerted' => $this->stale_alerted,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * A short, non-reversible fingerprint of the stored key hash — enough for an
     * operator to recognise a source, never enough to reconstruct the key.
     */
    private function maskedKey(): ?string
    {
        $hash = $this->key_hash;

        if (! is_string($hash) || $hash === '') {
            return null;
        }

        return substr($hash, 0, 8).'...';
    }
}
