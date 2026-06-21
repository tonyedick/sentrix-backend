<?php

declare(strict_types=1);

namespace App\Domains\Retention\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bundle archive-eligible observations into an export manifest and seal them.
 * Accepts an OPTIONAL explicit `observation_ids` list; when omitted the service
 * archives the default eligible set (cold, non-hold, non-sealed).
 */
final class ArchiveExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::RetentionArchive->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observation_ids' => ['sometimes', 'array'],
            'observation_ids.*' => ['uuid'],
        ];
    }
}
