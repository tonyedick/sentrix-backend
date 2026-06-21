<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Services;

use App\Domains\VisionGuard\Contracts\MediaStorage;
use App\Domains\VisionGuard\Models\CameraSource;
use App\Domains\VisionGuard\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Vision Guard: camera-source registry + media capture pipeline. Upload is
 * direct-to-storage (presigned), then finalized into a MediaAsset row.
 * User-scoped (ADR-0001).
 */
final readonly class VisionGuardService
{
    public function __construct(private MediaStorage $storage) {}

    public function registerSource(User $user, string $type, string $label, ?array $metadata = null): CameraSource
    {
        return CameraSource::create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'label' => $label,
            'status' => 'active',
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array{key:string,upload_url:string,method:string,expires_at:string}
     */
    public function createUploadTarget(User $user, string $contentType): array
    {
        $key = 'media/'.$user->getKey().'/'.Str::uuid()->toString();
        $target = $this->storage->uploadTarget($key, $contentType);

        return ['key' => $key, ...$target];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function finalize(User $user, array $data): MediaAsset
    {
        return MediaAsset::create([
            'user_id' => $user->getKey(),
            'camera_source_id' => $data['camera_source_id'] ?? null,
            'storage_key' => $data['storage_key'],
            'content_type' => $data['content_type'] ?? null,
            'size_bytes' => $data['size_bytes'] ?? null,
            'status' => 'uploaded',
            'trip_id' => $data['trip_id'] ?? null,
            'emergency_id' => $data['emergency_id'] ?? null,
            'captured_at' => $data['captured_at'] ?? now(),
        ]);
    }

    public function urlFor(MediaAsset $asset): string
    {
        return $this->storage->publicUrl($asset->storage_key);
    }
}
