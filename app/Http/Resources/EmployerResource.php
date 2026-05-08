<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'company_name' => $this->company_name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->cover_image_url,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'founded_year' => $this->founded_year,
            'website' => $this->website,
            'description' => $this->description,
            'headquarters' => $this->headquarters,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'is_verified' => $this->is_verified,
            'average_rating' => $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'team_members' => EmployerTeamMemberResource::collection($this->whenLoaded('teamMembers')),
        ];
    }
}
