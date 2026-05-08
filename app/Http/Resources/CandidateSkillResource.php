<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'skill_id' => $this->skill_id,
            'name' => $this->whenLoaded('skill', fn () => $this->skill->name),
            'proficiency_level' => $this->proficiency_level,
            'years_experience' => $this->years_experience,
        ];
    }
}
