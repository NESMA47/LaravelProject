<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployerPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'city' => $this->city,
            'country' => $this->country,
            'is_verified' => $this->is_verified,
            'average_rating' => $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'active_jobs' => $this->whenLoaded('jobs', function () {
                return $this->jobs->map(fn ($job) => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'slug' => $job->slug,
                    'type' => $job->type,
                    'location' => $job->location,
                ])->values();
            }),
            'recent_reviews' => CompanyReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
