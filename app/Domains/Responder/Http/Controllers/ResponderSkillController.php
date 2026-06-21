<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Http\Requests\AttachSkillRequest;
use App\Domains\Responder\Http\Resources\SkillResource;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Models\Skill;
use App\Domains\Responder\Services\ResponderCapabilityService;
use App\Domains\Responder\Support\Enums\SkillProficiency;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The skills a specific responder holds.
 */
final class ResponderSkillController extends Controller
{
    public function __construct(private readonly ResponderCapabilityService $capabilities) {}

    public function index(Request $request, Organization $organization, Responder $responder): AnonymousResourceCollection
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        return SkillResource::collection($responder->load('skills')->skills);
    }

    public function store(AttachSkillRequest $request, Organization $organization, Responder $responder): AnonymousResourceCollection
    {
        $this->assertResponderInOrganization($organization, $responder);

        $skill = Skill::query()
            ->whereKey($request->string('skill_id')->value())
            ->where('organization_id', $organization->getKey())
            ->first();

        abort_if($skill === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'The skill does not belong to this organization.');

        $proficiency = SkillProficiency::from(
            $request->string('proficiency', SkillProficiency::Trained->value)->value(),
        );

        $this->capabilities->attachSkill($responder, $skill, $proficiency);

        return SkillResource::collection($responder->load('skills')->skills);
    }

    public function destroy(Request $request, Organization $organization, Responder $responder, Skill $skill): Response
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_unless($request->user()->can(DefaultPermission::RespondersManage->value), Response::HTTP_FORBIDDEN);

        $this->capabilities->detachSkill($responder, $skill);

        return response()->noContent();
    }

    private function assertResponderInOrganization(Organization $organization, Responder $responder): void
    {
        abort_if($responder->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
