<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Http\Controllers;

use App\Domains\VisionGuard\Http\Requests\CreateUploadUrlRequest;
use App\Domains\VisionGuard\Http\Requests\FinalizeMediaRequest;
use App\Domains\VisionGuard\Http\Resources\MediaAssetResource;
use App\Domains\VisionGuard\Models\MediaAsset;
use App\Domains\VisionGuard\Services\VisionGuardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Media capture pipeline: request a direct-to-storage upload target, then
 * finalize the asset. Encrypted storage + retention live behind the
 * MediaStorage abstraction. User-scoped (ADR-0001).
 */
final class ConsumerMediaController extends Controller
{
    public function __construct(private readonly VisionGuardService $vision)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $media = MediaAsset::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return MediaAssetResource::collection($media);
    }

    public function uploadUrl(CreateUploadUrlRequest $request): JsonResponse
    {
        $target = $this->vision->createUploadTarget($request->user(), $request->string('content_type')->value());

        return response()->json(['data' => $target], Response::HTTP_CREATED);
    }

    public function store(FinalizeMediaRequest $request): MediaAssetResource
    {
        $asset = $this->vision->finalize($request->user(), $request->validated());

        return MediaAssetResource::make($asset);
    }

    /**
     * Local-storage upload receiver (dev only). Accepts the raw PUT body from the
     * client and writes it to the public disk under the issued key. Production
     * uses a real signed PUT straight to the object store, so this is never hit.
     */
    public function localUpload(Request $request, string $key): JsonResponse
    {
        // The issued key is namespaced by user id (media/{userId}/{uuid}); only
        // the owner may write to it.
        abort_unless(str_starts_with($key, 'media/' . $request->user()->getKey() . '/'), Response::HTTP_FORBIDDEN);

        Storage::disk('public')->put($key, (string) $request->getContent());

        return response()->json(['stored' => true]);
    }
}
