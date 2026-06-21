<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Http\Requests;

use App\Domains\Assignment\Support\Enums\ResponderRole;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OfferResponderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::AssignmentsDispatch->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'responder_id' => ['required', 'uuid'],
            'role' => ['sometimes', Rule::in(ResponderRole::values())],
        ];
    }
}
