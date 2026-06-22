<?php

declare(strict_types=1);

namespace App\Domains\Community\Http\Requests;

use App\Domains\Community\Support\Enums\AlertCategory;
use App\Domains\Community\Support\Enums\AlertImpact;
use App\Domains\Community\Support\Enums\AlertSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Publish a trusted OFFICIAL / AI-sourced alert. Staff-only — gated on the
 * platform SuperAdmin role (no permission-catalogue entry required).
 */
final class PublishAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in([AlertSource::Official->value, AlertSource::Ai->value])],
            'category' => ['required', Rule::in(AlertCategory::values())],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'impact' => ['sometimes', Rule::in(AlertImpact::values())],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
