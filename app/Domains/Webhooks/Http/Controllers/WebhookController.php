<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Webhooks\DTOs\RegisterWebhookData;
use App\Domains\Webhooks\Http\Requests\RegisterWebhookRequest;
use App\Domains\Webhooks\Http\Resources\WebhookDeliveryResource;
use App\Domains\Webhooks\Http\Resources\WebhookResource;
use App\Domains\Webhooks\Models\Webhook;
use App\Domains\Webhooks\Models\WebhookDelivery;
use App\Domains\Webhooks\Services\WebhookRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped outbound webhook registry + delivery observability.
 */
final class WebhookController extends Controller
{
    public function __construct(private readonly WebhookRegistry $webhooks) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::WebhooksManage->value), Response::HTTP_FORBIDDEN);

        $webhooks = Webhook::query()
            ->where('organization_id', $organization->getKey())
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return WebhookResource::collection($webhooks);
    }

    public function store(RegisterWebhookRequest $request, Organization $organization): JsonResponse
    {
        $webhook = $this->webhooks->register(
            $organization,
            $request->user(),
            RegisterWebhookData::fromRequest($request),
        );

        return WebhookResource::make($webhook)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Organization $organization, Webhook $webhook): WebhookResource
    {
        $this->assertInOrganization($organization, $webhook);
        abort_unless($request->user()->can(DefaultPermission::WebhooksManage->value), Response::HTTP_FORBIDDEN);

        return WebhookResource::make($webhook);
    }

    public function destroy(Request $request, Organization $organization, Webhook $webhook): JsonResponse
    {
        $this->assertInOrganization($organization, $webhook);
        abort_unless($request->user()->can(DefaultPermission::WebhooksManage->value), Response::HTTP_FORBIDDEN);

        $this->webhooks->remove($webhook);

        return response()->json(['message' => 'Webhook removed.']);
    }

    public function deliveries(Request $request, Organization $organization, Webhook $webhook): AnonymousResourceCollection
    {
        $this->assertInOrganization($organization, $webhook);
        abort_unless($request->user()->can(DefaultPermission::WebhooksManage->value), Response::HTTP_FORBIDDEN);

        $deliveries = WebhookDelivery::query()
            ->where('webhook_id', $webhook->getKey())
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return WebhookDeliveryResource::collection($deliveries);
    }

    private function assertInOrganization(Organization $organization, Webhook $webhook): void
    {
        abort_if($webhook->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
