<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\DTOs\IssuePassData;
use App\Domains\Access\Events\PassIssued;
use App\Domains\Access\Events\PassRevoked;
use App\Domains\Access\Models\Pass;
use App\Domains\Access\Support\Enums\PassStatus;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues and revokes visitor passes. Gate verification (scan) lives in
 * {@see GateService}; this service owns the pass lifecycle.
 */
final readonly class PassService
{
    /**
     * Mint a new pass for a visitor, vouched for by $host.
     */
    public function issue(Organization $organization, User $host, IssuePassData $data): Pass
    {
        return DB::transaction(function () use ($organization, $host, $data): Pass {
            $pass = new Pass([
                'organization_id' => $organization->getKey(),
                'host_id' => $host->getKey(),
                'code' => $this->generateUniqueCode($organization),
                'visitor_name' => $data->visitorName,
                'type' => $data->type,
                'status' => PassStatus::Active,
                'valid_from' => $data->validFrom !== null ? Carbon::parse($data->validFrom) : Carbon::now(),
                'valid_until' => $data->validUntil !== null ? Carbon::parse($data->validUntil) : null,
                'uses_count' => 0,
                'metadata' => $data->metadata,
            ]);
            $pass->save();

            event(new PassIssued($pass, $host->getKey(), [
                'code' => $pass->code,
                'visitor_name' => $pass->visitor_name,
                'type' => $pass->type->value,
            ]));

            return $pass;
        });
    }

    /**
     * Revoke a pass (terminal). Idempotent: re-revoking a revoked pass is a
     * no-op that returns the pass unchanged.
     */
    public function revoke(Pass $pass, User $actor): Pass
    {
        return DB::transaction(function () use ($pass, $actor): Pass {
            /** @var Pass $locked */
            $locked = Pass::query()->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === PassStatus::Revoked) {
                return $locked;
            }

            $locked->status = PassStatus::Revoked;
            $locked->revoked_by = $actor->getKey();
            $locked->revoked_at = Carbon::now();
            $locked->save();

            event(new PassRevoked($locked, $actor->getKey(), [
                'code' => $locked->code,
            ]));

            return $locked;
        });
    }

    /**
     * Generate a 6-char uppercase alphanumeric code unique within the org.
     * Excludes ambiguous characters (0/O, 1/I) for human readability at a gate.
     */
    private function generateUniqueCode(Organization $organization): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists = Pass::query()
                ->where('organization_id', $organization->getKey())
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }
}
