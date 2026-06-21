<?php

declare(strict_types=1);

namespace App\Domains\Community\Http\Requests;

use App\Domains\Community\Support\Enums\AlertCategory;
use App\Domains\Community\Support\Enums\AlertImpact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(AlertCategory::values())],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'impact' => ['sometimes', Rule::in(AlertImpact::values())],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
