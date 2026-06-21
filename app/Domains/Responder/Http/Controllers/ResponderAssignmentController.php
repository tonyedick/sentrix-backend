<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Http\Resources\ResponderAssignmentResource;
use App\Domains\Responder\Models\Responder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * A responder's assignment participation — current and historical. Returns the
 * responder's assignment lines (newest first) with a compact incident summary,
 * so the workspace can show both the active engagement and past dispatches
 * without leaking the full Assignment aggregate.
 */
final class ResponderAssignmentController extends Controller
{
    public function index(Request $request, Organization $organization, Responder $responder): AnonymousResourceCollection
    {
        abort_if($responder->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        $lines = AssignmentResponder::query()
            ->where('responder_id', $responder->getKey())
            ->where('organization_id', $organization->getKey())
            ->with('incident')
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return ResponderAssignmentResource::collection($lines);
    }
}
