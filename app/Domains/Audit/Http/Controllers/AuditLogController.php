<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Controllers;

use App\Domains\Audit\Http\Resources\AuditLogResource;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only access to an organization's audit trail. The {organization} binding
 * + organization.team middleware scope the request to one tenant.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::AuditView->value),
            Response::HTTP_FORBIDDEN,
        );

        $logs = AuditLog::query()
            ->with('user')
            ->where('organization_id', $organization->getKey())
            ->when(
                $request->filled('action'),
                fn ($query) => $query->where('action', $request->string('action')->value()),
            )
            // Cursor (keyset) pagination: O(1) regardless of depth and no
            // COUNT(*) on a large append-only table. Order includes the unique
            // id as a tiebreaker so the cursor is stable when created_at ties.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return AuditLogResource::collection($logs);
    }
}
