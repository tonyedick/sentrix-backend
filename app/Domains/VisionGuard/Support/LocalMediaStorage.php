<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Support;

use App\Domains\VisionGuard\Contracts\MediaStorage;

/**
 * Dev/test storage stub: returns deterministic local URLs and a placeholder
 * upload target (no real bucket). Swap for an S3/GCS driver in production via
 * config('sentrix.visionguard.storage_driver').
 */
final class LocalMediaStorage implements MediaStorage
{
    /**
     * @return array{upload_url:string,method:string,expires_at:string}
     */
    public function uploadTarget(string $key, string $contentType): array
    {
        // Points at the in-app receiver (ConsumerMediaController::localUpload) so
        // the presigned-upload flow works end-to-end in local dev. In production
        // the S3/GCS driver returns a real signed PUT URL instead.
        return [
            'upload_url' => url('/api/v1/me/media/local-upload/'.$key),
            'method' => 'PUT',
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
    }

    public function publicUrl(string $key): string
    {
        // $key already includes the leading "media/" segment.
        return url('/storage/'.$key);
    }

    public function name(): string
    {
        return 'local';
    }
}
