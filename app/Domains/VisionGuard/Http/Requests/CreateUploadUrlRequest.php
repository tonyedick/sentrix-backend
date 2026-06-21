<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateUploadUrlRequest extends FormRequest
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
            'content_type' => ['required', 'string', 'max:128'],
        ];
    }
}
