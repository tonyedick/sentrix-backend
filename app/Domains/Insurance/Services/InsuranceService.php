<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Services;

use App\Domains\Insurance\DTOs\CreatePolicyData;
use App\Domains\Insurance\DTOs\FileClaimData;
use App\Domains\Insurance\Events\ClaimDecided;
use App\Domains\Insurance\Events\ClaimFiled;
use App\Domains\Insurance\Events\PolicyCreated;
use App\Domains\Insurance\Models\Claim;
use App\Domains\Insurance\Models\Policy;
use App\Domains\Insurance\Support\Enums\ClaimStatus;
use App\Domains\Insurance\Support\Enums\PolicyStatus;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the insurance policy + claim lifecycle. Writes run in a transaction and
 * emit a broadcast + audited event. The claim decision locks the row and
 * re-reads it so concurrent decisions are serialized and an illegal transition
 * (deciding an already-decided claim) is rejected with a 422.
 */
final readonly class InsuranceService
{
    public function createPolicy(Organization $organization, User $actor, CreatePolicyData $data): Policy
    {
        return DB::transaction(function () use ($organization, $actor, $data): Policy {
            $policy = Policy::create([
                'organization_id' => $organization->getKey(),
                'created_by' => $actor->getKey(),
                'title' => $data->title,
                'status' => PolicyStatus::Draft,
                'premium_cents' => $data->premiumCents,
                'currency' => $data->currency,
                'coverage' => $data->coverage,
                'period_start' => $data->periodStart,
                'period_end' => $data->periodEnd,
            ]);

            event(new PolicyCreated($policy, $actor->getKey(), [
                'status' => $policy->status->value,
                'premium_cents' => $policy->premium_cents,
                'currency' => $policy->currency,
            ]));

            return $policy;
        });
    }

    public function fileClaim(Organization $organization, User $actor, Policy $policy, FileClaimData $data): Claim
    {
        return DB::transaction(function () use ($organization, $actor, $policy, $data): Claim {
            $claim = Claim::create([
                'organization_id' => $organization->getKey(),
                'policy_id' => $policy->getKey(),
                'filed_by' => $actor->getKey(),
                'amount_cents' => $data->amountCents,
                'currency' => $data->currency,
                'status' => ClaimStatus::Filed,
                'description' => $data->description,
                'metadata' => $data->metadata,
            ]);

            event(new ClaimFiled($claim, $actor->getKey(), [
                'status' => $claim->status->value,
                'policy_id' => $claim->policy_id,
                'amount_cents' => $claim->amount_cents,
                'currency' => $claim->currency,
            ]));

            return $claim;
        });
    }

    /**
     * Approve or reject a filed claim. Concurrency-safe: the row is locked and
     * re-read inside the transaction so the decidability check and the write are
     * atomic against a concurrent decision. Deciding an already-decided claim is
     * an illegal transition and throws a 422.
     */
    public function decideClaim(Claim $claim, User $actor, ClaimStatus $outcome): Claim
    {
        if ($outcome !== ClaimStatus::Approved && $outcome !== ClaimStatus::Rejected) {
            throw ValidationException::withMessages([
                'decision' => ['A claim decision must be either approved or rejected.'],
            ]);
        }

        return DB::transaction(function () use ($claim, $actor, $outcome): Claim {
            /** @var Claim $locked */
            $locked = Claim::query()->whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isDecidable()) {
                throw ValidationException::withMessages([
                    'status' => ["A claim in the {$locked->status->value} state can no longer be decided."],
                ]);
            }

            $locked->update([
                'status' => $outcome,
                'decided_by' => $actor->getKey(),
                'decided_at' => now(),
            ]);

            event(new ClaimDecided($locked, $actor->getKey(), [
                'status' => $locked->status->value,
                'policy_id' => $locked->policy_id,
            ]));

            return $locked;
        });
    }
}
