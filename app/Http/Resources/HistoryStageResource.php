<?php

namespace App\Http\Resources;

use App\Services\ApplicationStageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoryStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $this->changedBy;

        return [
            'id' => $this->id,
            'stage' => $this->stage,
            'label' => ApplicationStageService::getStageLabel($this->stage),
            'actor_name' => $this->is_system ? 'System' : ($actor?->first_name . ' ' . $actor?->last_name ?: 'Unknown'),
            'actor_role' => $this->is_system ? 'system' : ($actor?->role ?? 'unknown'),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
