<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Requests;

use App\Domains\DriverOnboarding\Support\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Driver-scoped: the authenticated driver uploads a document.
 */
final class UploadDocumentRequest extends FormRequest
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
            'type' => ['required', Rule::in(DocumentType::values())],
            'url' => ['required', 'string', 'max:2048'],
        ];
    }
}
