<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'location_type' => $this->location_type,
            'location_details' => $this->location_details,
            'notes' => $this->notes,
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'cancellation_note' => $this->cancellation_note,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
        ];
    }
}
