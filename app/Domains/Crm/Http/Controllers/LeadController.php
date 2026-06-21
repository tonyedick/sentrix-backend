<?php

declare(strict_types=1);

namespace App\Domains\Crm\Http\Controllers;

use App\Domains\Crm\DTOs\CreateLeadData;
use App\Domains\Crm\DTOs\UpdateLeadData;
use App\Domains\Crm\Http\Requests\AttachQuoteRequest;
use App\Domains\Crm\Http\Requests\CreateLeadRequest;
use App\Domains\Crm\Http\Requests\UpdateLeadRequest;
use App\Domains\Crm\Http\Resources\LeadResource;
use App\Domains\Crm\Models\Lead;
use App\Domains\Crm\Services\LeadService;
use App\Domains\Crm\Support\Enums\ClientType;
use App\Domains\Crm\Support\Enums\LeadStage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-scoped CRM lead pipeline: sales -> onboarding -> tenant activation.
 *
 * Unlike the org-scoped domains, leads pre-date tenants, so these routes are NOT
 * behind `organization.team` and carry no {organization} segment. Every action
 * is gated on the platform SuperAdmin.
 *
 * TODO: replace SuperAdmin gate with platform-staff roles (sales/onboarding_manager) + separation of duties (sales cannot convert) once a platform-staff RBAC layer exists.
 */
final class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'stage' => ['sometimes', Rule::in(LeadStage::values())],
            'client_type' => ['sometimes', Rule::in(ClientType::values())],
        ]);

        $leads = Lead::query()
            ->when(isset($validated['stage']), fn ($query) => $query->where('stage', $validated['stage']))
            ->when(isset($validated['client_type']), fn ($query) => $query->where('client_type', $validated['client_type']))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return LeadResource::collection($leads);
    }

    public function store(CreateLeadRequest $request): JsonResponse
    {
        $lead = $this->leads->create(CreateLeadData::fromRequest($request), $request->user());

        return LeadResource::make($lead)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Lead $lead): LeadResource
    {
        abort_unless($request->user()->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        return LeadResource::make($lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        return LeadResource::make(
            $this->leads->update($lead, UpdateLeadData::fromRequest($request)),
        );
    }

    public function quote(AttachQuoteRequest $request, Lead $lead): LeadResource
    {
        /** @var array<string, mixed> $quote */
        $quote = $request->validated('quote');

        return LeadResource::make($this->leads->attachQuote($lead, $quote));
    }

    public function convert(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        $result = $this->leads->convert($lead, $request->user());

        return response()->json([
            'data' => [
                'lead' => LeadResource::make($result['lead'])->resolve($request),
                'organization_id' => $result['organization']->getKey(),
            ],
        ]);
    }
}
