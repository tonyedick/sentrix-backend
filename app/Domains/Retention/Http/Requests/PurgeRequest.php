<?php

declare(strict_types=1);

namespace App\Domains\Retention\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Permanently delete archived (sealed), non-hold observations. No body. Legal
 * holds are never purged.
 */
final class PurgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::RetentionPurge->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
