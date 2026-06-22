<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::WebhooksManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'starts_with:https://', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
