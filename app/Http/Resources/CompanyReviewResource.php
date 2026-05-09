<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating_overall' => $this->rating_overall,
            'title' => $this->title,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'is_anonymous' => $this->is_anonymous,
            'created_at' => $this->created_at,
        ];
    }
}
