<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\DTOs\ResponderLocationFix;
use App\Domains\Responder\Http\Requests\IngestResponderLocationsRequest;
use App\Domains\Responder\Http\Resources\ResponderLocationResource;
use App\Domains\Responder\Http\Resources\ResponderResource;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Services\ResponderLocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responder live location: idempotent batch ingest, history, and an
 * organization-wide latest-positions feed for the dispatcher map.
 */
final class ResponderLocationController extends Controller
{
    public function __construct(private readonly ResponderLocationService $locations) {}

    public function store(IngestResponderLocationsRequest $request, Organization $organization, Responder $responder): JsonResponse
    {
        $this->assertResponderInOrganization($organization, $responder);

        // Self-service for one's own profile; pushing another responder's
        // location requires management.
        if ($responder->user_id === $request->user()->getKey()) {
            abort_unless($request->user()->can(DefaultPermission::RespondersSelf->value), Response::HTTP_FORBIDDEN);
        } else {
            abort_unless($request->user()->can(DefaultPermission::RespondersManage->value), Response::HTTP_FORBIDDEN);
        }

        $fixes = array_map(
            static fn (array $fix): ResponderLocationFix => ResponderLocationFix::fromArray($fix),
            $request->validated('fixes'),
        );

        $stored = $this->locations->ingest($responder, $fixes);

        return response()->json([
            'stored' => $stored,
            'received' => count($fixes),
        ], Response::HTTP_ACCEPTED);
    }

    public function index(Request $request, Organization $organization, Responder $responder): AnonymousResourceCollection
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        return ResponderLocationResource::collection(
            $responder->locations()->latest('recorded_at')->paginate($this->perPage($request)),
        );
    }

    /**
     * Org-wide latest positions for on-duty responders (the live map).
     */
    public function positions(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        $responders = Responder::query()
            ->where('organization_id', $organization->getKey())
            ->where('on_duty', true)
            ->whereNotNull('last_seen_at')
            ->latest('last_seen_at')
            ->paginate($this->perPage($request));

        return ResponderResource::collection($responders);
    }

    private function assertResponderInOrganization(Organization $organization, Responder $responder): void
    {
        abort_if($responder->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
