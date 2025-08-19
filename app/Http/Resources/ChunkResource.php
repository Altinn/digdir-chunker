<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChunkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * @example "4490ff5e-3f25-49d9-b6cf-b7a252e96429"
             */
            'id' => $this->id,
            /**
             * @example "paragraph"
             */
            'chunk_type' => $this->chunk_type,
            'file' => [
                'url' => $this->file->url,
                'metadata' => $this->file->metadata,
            ],
            /**
             * @example "The sum of the squares of the two legs of a right triangle is equal to the square of the hypotenuse."
             */
            'text' => $this->text,
            /**
             * @example [12]
             */
            'page_numbers' => $this->page_numbers,
            /**
             * @example "415"
             */
            'chunk_number' => $this->chunk_number,

            'derivatives' => ChunkDerivativeSearchResource::collection($this->derivatives) ?: [],
        ];
    }
}
