<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\DTOs\RegisterResponderData;
use App\Domains\Responder\Http\Requests\ChangeResponderStatusRequest;
use App\Domains\Responder\Http\Requests\RegisterResponderRequest;
use App\Domains\Responder\Http\Resources\ResponderResource;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Services\ResponderService;
use App\Domains\Responder\Support\Enums\ResponderStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped responder roster + status management. Status changes are
 * self-service for one's own profile (responders.self) and management-gated for
 * others; suspension always requires responders.manage.
 */
final class ResponderController extends Controller
{
    public function __construct(private readonly ResponderService $responders) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        $responders = Responder::query()
            ->with('user')
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->boolean('assignable'), fn ($query) => $query->assignable())
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return ResponderResource::collection($responders);
    }

    public function store(RegisterResponderRequest $request, Organization $organization): JsonResponse
    {
        $user = User::whereKey($request->string('user_id')->value())->first();

        abort_unless(
            $user?->belongsToOrganization($organization) === true,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'The user is not a member of this organization.',
        );

        abort_if(
            Responder::query()
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $user->getKey())
                ->exists(),
            Response::HTTP_CONFLICT,
            'This user is already a responder in the organization.',
        );

        $responder = $this->responders->register($organization, $user, RegisterResponderData::fromRequest($request));

        return ResponderResource::make($responder)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Organization $organization, Responder $responder): ResponderResource
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        return ResponderResource::make($responder->load(['user', 'currentAssignment.incident']));
    }

    public function changeStatus(ChangeResponderStatusRequest $request, Organization $organization, Responder $responder): ResponderResource
    {
        $this->assertResponderInOrganization($organization, $responder);

        $target = ResponderStatus::from($request->string('status')->value());
        $this->authorizeStatusChange($request->user(), $responder, $target);

        return ResponderResource::make($this->responders->transition($responder, $target, $request->user()));
    }

    /**
     * Self-service for one's own profile; managing another responder — or any
     * suspension — requires responders.manage.
     */
    private function authorizeStatusChange(User $actor, Responder $responder, ResponderStatus $target): void
    {
        $isSelf = $responder->user_id === $actor->getKey();

        if ($target === ResponderStatus::Suspended || ! $isSelf) {
            abort_unless($actor->can(DefaultPermission::RespondersManage->value), Response::HTTP_FORBIDDEN);

            return;
        }

        abort_unless($actor->can(DefaultPermission::RespondersSelf->value), Response::HTTP_FORBIDDEN);
    }

    private function assertResponderInOrganization(Organization $organization, Responder $responder): void
    {
        abort_if($responder->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
