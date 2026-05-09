<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'skill_id' => $this->skill_id,
            'name' => $this->whenLoaded('skill', fn () => $this->skill->name),
            'slug' => $this->whenLoaded('skill', fn () => $this->skill->slug),
            'is_required' => $this->is_required,
            'min_proficiency' => $this->min_proficiency,
        ];
    }
}
