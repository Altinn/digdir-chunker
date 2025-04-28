<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'uuid' => $this->uuid,
                'conversion_backend' => $this->backend,
                'chunking_method' => $this->chunking_method,
                'status' => $this->status,
                'created_at' => $this->created_at,
                'started_at' => $this->started_at,
                'finished_at' => $this->finished_at,
                'expires_at' => $this->expires_at,
                'delete_at' => $this->delete_at,
                'file' => new FileResource($this->file),
            ],
            'links' => [
                // 'self' => url()
            ],
        ];
    }
}
