<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FinalizeMediaRequest extends FormRequest
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
            'storage_key' => ['required', 'string', 'max:512'],
            'content_type' => ['sometimes', 'nullable', 'string', 'max:128'],
            'size_bytes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'camera_source_id' => ['sometimes', 'nullable', 'uuid'],
            'trip_id' => ['sometimes', 'nullable', 'uuid'],
            'emergency_id' => ['sometimes', 'nullable', 'uuid'],
            'captured_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
