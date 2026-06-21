<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\DTOs\IngestWriteData;
use App\Domains\Ledger\DTOs\OnboardSourceData;
use App\Domains\Ledger\Http\Requests\IngestWriteRequest;
use App\Domains\Ledger\Http\Requests\OnboardSourceRequest;
use App\Domains\Ledger\Http\Resources\LedgerSourceResource;
use App\Domains\Ledger\Http\Resources\LedgerWriteResource;
use App\Domains\Ledger\Models\LedgerSource;
use App\Domains\Ledger\Models\LedgerWrite;
use App\Domains\Ledger\Services\LedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sentrix Ledger HTTP surface.
 *
 * PLATFORM-scoped (NOT organization-scoped): the admin endpoints sit behind
 * auth:sanctum and are gated on SuperAdmin here (no organization permission).
 * The ingest endpoint is authed upstream by the ledger.key middleware.
 */
final class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function stats(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        return response()->json(['data' => $this->ledger->stats()]);
    }

    public function writes(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $writes = LedgerWrite::query()
            ->when($request->filled('source'), function ($query) use ($request): void {
                $source = $request->string('source')->value();
                $query->where('ledger_source_id', $source)
                    ->orWhereHas('source', fn ($q) => $q->where('slug', $source));
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->when($request->filled('org'), fn ($query) => $query->where('organization_id', $request->string('org')->value()))
            ->latest('recorded_at')
            ->paginate($this->perPage($request));

        return LedgerWriteResource::collection($writes);
    }

    public function sources(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $sources = LedgerSource::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return LedgerSourceResource::collection($sources);
    }

    public function onboard(OnboardSourceRequest $request): JsonResponse
    {
        // Ability is enforced in the Form Request's authorize() (SuperAdmin).
        $result = $this->ledger->onboard(OnboardSourceData::fromRequest($request));

        return response()->json([
            'message' => 'Source onboarded. Store this ingest key now — it will not be shown again.',
            'data' => [
                'source' => LedgerSourceResource::make($result['source']),
                'ingest_key' => $result['raw_key'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function activate(Request $request, LedgerSource $source): LedgerSourceResource
    {
        $this->assertSuperAdmin($request);

        return LedgerSourceResource::make($this->ledger->activate($source));
    }

    public function suspend(Request $request, LedgerSource $source): LedgerSourceResource
    {
        $this->assertSuperAdmin($request);

        return LedgerSourceResource::make($this->ledger->suspend($source));
    }

    public function revoke(Request $request, LedgerSource $source): LedgerSourceResource
    {
        $this->assertSuperAdmin($request);

        return LedgerSourceResource::make($this->ledger->revoke($source));
    }

    public function rotateKey(Request $request, LedgerSource $source): JsonResponse
    {
        $this->assertSuperAdmin($request);

        $result = $this->ledger->rotateKey($source);

        return response()->json([
            'message' => 'Ingest key rotated. Store this key now — it will not be shown again.',
            'data' => [
                'source' => LedgerSourceResource::make($result['source']),
                'ingest_key' => $result['raw_key'],
            ],
        ]);
    }

    /**
     * Ingest a write. Authenticated upstream by the ledger.key middleware, which
     * binds the resolved (active) source onto the request.
     */
    public function ingest(IngestWriteRequest $request): JsonResponse
    {
        /** @var LedgerSource $source */
        $source = $request->attributes->get('ledger_source');

        $write = $this->ledger->ingest($source, IngestWriteData::fromRequest($request));

        return LedgerWriteResource::make($write)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function assertSuperAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->isSuperAdmin(), Response::HTTP_FORBIDDEN);
    }
}
