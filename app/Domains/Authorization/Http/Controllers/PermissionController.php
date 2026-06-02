<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Http\Controllers;

use App\Domains\Authorization\Http\Resources\PermissionResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Read-only listing of the global permission catalogue (used to populate role
 * editors in the UI).
 */
final class PermissionController extends Controller
{
    public function index(PermissionRegistrar $registrar): AnonymousResourceCollection
    {
        $permissionClass = $registrar->getPermissionClass();

        return PermissionResource::collection($permissionClass::all());
    }
}
