<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\StoreSafetyContactRequest;
use App\Domains\Identity\Http\Requests\UpdateSafetyContactRequest;
use App\Domains\Identity\Http\Resources\SafetyContactResource;
use App\Domains\Identity\Models\SafetyContact;
use App\Domains\Identity\Services\SafetyContactService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The authenticated user's safety contacts (user-scoped, ADR-0001).
 */
final class SafetyContactController extends Controller
{
    public function __construct(private readonly SafetyContactService $contacts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SafetyContactResource::collection($this->contacts->list($request->user()));
    }

    public function store(StoreSafetyContactRequest $request): SafetyContactResource
    {
        /** @var array{name:string,phone:string,email?:?string,relationship?:?string,is_primary?:bool} $data */
        $data = $request->validated();

        return SafetyContactResource::make($this->contacts->add($request->user(), $data));
    }

    public function update(UpdateSafetyContactRequest $request, SafetyContact $contact): SafetyContactResource
    {
        $this->assertOwned($request, $contact);

        return SafetyContactResource::make($this->contacts->update($contact, $request->validated()));
    }

    public function destroy(Request $request, SafetyContact $contact): Response
    {
        $this->assertOwned($request, $contact);
        $this->contacts->delete($contact);

        return response()->noContent();
    }

    private function assertOwned(Request $request, SafetyContact $contact): void
    {
        abort_if($contact->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
