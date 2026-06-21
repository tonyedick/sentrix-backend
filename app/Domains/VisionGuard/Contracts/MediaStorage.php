<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Contracts;

/**
 * Abstraction over media object storage. Real drivers issue presigned
 * direct-to-bucket upload URLs (S3 / GCS) with encryption-at-rest; the 'local'
 * stub returns deterministic URLs so the flow is testable without a bucket.
 */
interface MediaStorage
{
    /**
     * @return array{upload_url:string,method:string,expires_at:string}
     */
    public function uploadTarget(string $key, string $contentType): array;

    public function publicUrl(string $key): string;

    public function name(): string;
}
