<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Http\Requests\CreateSkillRequest;
use App\Domains\Responder\Http\Resources\SkillResource;
use App\Domains\Responder\Models\Skill;
use App\Domains\Responder\Services\ResponderCapabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The organization's responder skill catalogue.
 */
final class SkillController extends Controller
{
    public function __construct(private readonly ResponderCapabilityService $capabilities) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        $skills = Skill::query()
            ->where('organization_id', $organization->getKey())
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return SkillResource::collection($skills);
    }

    public function store(CreateSkillRequest $request, Organization $organization): JsonResponse
    {
        $skill = $this->capabilities->createSkill(
            $organization,
            $request->string('code')->value(),
            $request->string('name')->value(),
        );

        return SkillResource::make($skill)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
