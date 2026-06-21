<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Http\Requests\CreateCertificationRequest;
use App\Domains\Responder\Http\Resources\ResponderCertificationResource;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Models\ResponderCertification;
use App\Domains\Responder\Services\ResponderCapabilityService;
use App\Domains\Responder\Support\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * A responder's certifications. Verification is management-gated; the expiry
 * sweep transitions verified certs to expired automatically.
 */
final class ResponderCertificationController extends Controller
{
    public function __construct(private readonly ResponderCapabilityService $capabilities) {}

    public function index(Request $request, Organization $organization, Responder $responder): AnonymousResourceCollection
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        return ResponderCertificationResource::collection(
            $responder->certifications()->latest('created_at')->paginate($this->perPage($request)),
        );
    }

    public function store(CreateCertificationRequest $request, Organization $organization, Responder $responder): JsonResponse
    {
        $this->assertResponderInOrganization($organization, $responder);

        $certification = $this->capabilities->addCertification($responder, $request->validated());

        return ResponderCertificationResource::make($certification)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function verify(Request $request, Organization $organization, Responder $responder, ResponderCertification $certification): ResponderCertificationResource
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_if($certification->responder_id !== $responder->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($request->user()->can(DefaultPermission::RespondersManage->value), Response::HTTP_FORBIDDEN);

        return ResponderCertificationResource::make(
            $this->capabilities->setCertificationStatus($certification, CertificationStatus::Verified),
        );
    }

    private function assertResponderInOrganization(Organization $organization, Responder $responder): void
    {
        abort_if($responder->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
