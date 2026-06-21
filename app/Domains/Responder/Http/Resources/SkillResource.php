<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Resources;

use App\Domains\Responder\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Skill
 */
final class SkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            // Present only when loaded via a responder's skills() pivot.
            'proficiency' => $this->whenPivotLoaded('responder_skill', fn () => $this->pivot->proficiency),
        ];
    }
}
