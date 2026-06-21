<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Http\Controllers;

use App\Domains\VisionGuard\Http\Requests\RegisterSourceRequest;
use App\Domains\VisionGuard\Http\Requests\UpdateSourceRequest;
use App\Domains\VisionGuard\Http\Resources\CameraSourceResource;
use App\Domains\VisionGuard\Models\CameraSource;
use App\Domains\VisionGuard\Services\VisionGuardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connected camera sources (Connected Sources screen). User-scoped (ADR-0001).
 */
final class ConsumerSourceController extends Controller
{
    public function __construct(private readonly VisionGuardService $vision) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $sources = CameraSource::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('created_at')
            ->get();

        return CameraSourceResource::collection($sources);
    }

    public function store(RegisterSourceRequest $request): CameraSourceResource
    {
        $source = $this->vision->registerSource(
            $request->user(),
            $request->string('type')->value(),
            $request->string('label')->value(),
            $request->input('metadata'),
        );

        return CameraSourceResource::make($source);
    }

    public function update(UpdateSourceRequest $request, CameraSource $source): CameraSourceResource
    {
        $this->assertOwned($request, $source);
        $source->fill($request->only(['label', 'status', 'metadata']))->save();

        return CameraSourceResource::make($source->refresh());
    }

    public function destroy(Request $request, CameraSource $source): Response
    {
        $this->assertOwned($request, $source);
        $source->delete();

        return response()->noContent();
    }

    private function assertOwned(Request $request, CameraSource $source): void
    {
        abort_if($source->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
