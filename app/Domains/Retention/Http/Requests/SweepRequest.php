<?php

declare(strict_types=1);

namespace App\Domains\Retention\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Run the storage lifecycle sweep for this organization. No body.
 */
final class SweepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::RetentionManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
