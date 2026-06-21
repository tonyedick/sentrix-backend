<?php

declare(strict_types=1);

namespace App\Domains\Crm\Services;

use App\Domains\Crm\DTOs\CreateLeadData;
use App\Domains\Crm\DTOs\UpdateLeadData;
use App\Domains\Crm\Events\LeadConverted;
use App\Domains\Crm\Events\LeadCreated;
use App\Domains\Crm\Models\Lead;
use App\Domains\Crm\Support\Enums\LeadStage;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\OrganizationService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owns the CRM lead lifecycle: create -> update (stage/notes/contact) -> attach
 * quote -> convert into a live tenant.
 *
 * PLATFORM-scoped: leads pre-date tenants, so there is no organization context
 * here. Tenant provisioning on convert is delegated wholesale to
 * OrganizationService — this service never duplicates org-creation logic.
 */
final readonly class LeadService
{
    public function __construct(
        private OrganizationService $organizations,
    ) {}

    public function create(CreateLeadData $data, User $actor): Lead
    {
        return DB::transaction(function () use ($data, $actor): Lead {
            $lead = Lead::create([
                'created_by' => $actor->getKey(),
                'name' => $data->name,
                'client_type' => $data->clientType,
                'contact_name' => $data->contactName,
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'region' => $data->region,
                'source' => $data->source,
                'stage' => LeadStage::New,
                'notes' => $data->notes,
            ]);

            event(new LeadCreated($lead, $actor->getKey()));

            return $lead;
        });
    }

    /**
     * Patch a lead's stage, notes, and/or contact details. A converted lead is
     * terminal and cannot be edited (mirrors the Omni store: updateLead rejects
     * an already-converted lead).
     */
    public function update(Lead $lead, UpdateLeadData $data): Lead
    {
        return DB::transaction(function () use ($lead, $data): Lead {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            $this->assertNotConverted($locked);

            $attributes = $data->toAttributes();

            if ($attributes !== []) {
                $locked->update($attributes);
            }

            return $locked->refresh();
        });
    }

    /**
     * Attach a pricing snapshot (the posted quote object) to the lead. Mirrors
     * the Omni store: attaching a quote to a brand-new lead advances it to the
     * quoted stage.
     *
     * @param  array<string, mixed>  $quote
     */
    public function attachQuote(Lead $lead, array $quote): Lead
    {
        return DB::transaction(function () use ($lead, $quote): Lead {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            $this->assertNotConverted($locked);

            $locked->quote = $quote;

            if ($locked->stage === LeadStage::New) {
                $locked->stage = LeadStage::Quoted;
            }

            $locked->save();

            return $locked->refresh();
        });
    }

    /**
     * Convert a won lead into a live tenant.
     *
     * - Idempotent: a lead already linked to an organization returns that org
     *   unchanged (a converted lead is never re-provisioned).
     * - A lost lead cannot be converted.
     * - Tenant provisioning is delegated to OrganizationService->create, which
     *   itself runs in a transaction (org + default roles + owner membership +
     *   active-context switch). We nest our DB::transaction around it so the
     *   lead mutation and the org creation commit or roll back together.
     *
     * OWNER RESOLUTION: a lead carries only a contact_email, but
     * CreateOrganizationData requires an owner User. We therefore create-or-find
     * a User by that email first (the DatabaseSeeder "find-or-create by email"
     * pattern), then provision the org for that owner.
     *
     * @return array{lead: Lead, organization: Organization}
     */
    public function convert(Lead $lead, ?User $actor = null): array
    {
        return DB::transaction(function () use ($lead, $actor): array {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            // Idempotent: already converted -> return the existing tenant.
            if ($locked->converted_organization_id !== null) {
                /** @var Organization $existing */
                $existing = Organization::query()->whereKey($locked->converted_organization_id)->firstOrFail();

                return ['lead' => $locked, 'organization' => $existing];
            }

            if ($locked->stage === LeadStage::Lost) {
                throw ValidationException::withMessages([
                    'stage' => ['A lost lead cannot be converted into a tenant.'],
                ])->status(Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $owner = $this->resolveOwner($locked);

            // Reuse the Organization domain's provisioning wholesale — no
            // org-creation logic is duplicated here.
            $organization = $this->organizations->create(new CreateOrganizationData(
                name: $locked->name,
                owner: $owner,
            ));

            $locked->forceFill([
                'stage' => LeadStage::Won,
                'converted_organization_id' => $organization->getKey(),
            ])->save();

            event(new LeadConverted($locked, $organization->getKey(), $actor?->getKey()));

            return ['lead' => $locked->refresh(), 'organization' => $organization];
        });
    }

    /**
     * Find the user behind the lead's contact email, or create one. New users
     * get a random password (the tenant owner resets via the standard flow);
     * the email is normalized to lowercase to match how leads store it.
     */
    private function resolveOwner(Lead $lead): User
    {
        $email = Str::lower(trim((string) $lead->contact_email));

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        return User::create([
            'name' => $lead->contact_name !== '' ? $lead->contact_name : 'Account Admin',
            'email' => $email,
            'password' => Hash::make(Str::password(32)),
        ]);
    }

    private function assertNotConverted(Lead $lead): void
    {
        if ($lead->converted_organization_id !== null) {
            throw ValidationException::withMessages([
                'lead' => ['A converted lead can no longer be edited.'],
            ])->status(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
