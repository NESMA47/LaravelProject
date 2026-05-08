<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResumeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'is_default' => $this->is_default,
            'file' => $this->whenLoaded('file', fn () => [
                'id' => $this->file->id,
                'url' => $this->file->url,
                'size_bytes' => $this->file->size_bytes,
                'original_name' => $this->file->original_name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
