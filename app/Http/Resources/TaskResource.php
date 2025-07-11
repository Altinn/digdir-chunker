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
                /**
                 * @example "4490ff5e-3f25-49d9-b6cf-b7a252e96429"
                 */
                'uuid' => $this->uuid,
                /**
                 * The conversion backend used for the task.
                 */
                'conversion_backend' => $this->conversion_backend,
                /**
                 * The chunking method used for the task.
                 */
                'chunking_method' => $this->chunking_method,
                /**
                 * The status of the task.
                 */
                'task_status' => $this->task_status,
                /**
                 * The time the task was created.
                 */
                'created_at' => $this->created_at,
                /**
                 * The time the task started processing.
                 */
                'started_at' => $this->started_at,
                /**
                 * The time the task finished processing.
                 */
                'finished_at' => $this->finished_at,
                /**
                 * The time the task expires if it is not completed. Expired tasks are deleted.
                 */
                'expires_at' => $this->expires_at,
                /**
                 * The time the task and its associated resources are deleted.
                 */
                'delete_at' => $this->delete_at,
                'file' => new FileResource($this->file),
            ],
            'links' => [ 
                /**
                 * The link to the task resource. Should be used to retrieve the task.
                 * @example "https://api.example.com/tasks/4490ff5e-3f25-49d9-b6cf-b7a252e96429"
                 */
                'self' => route('task.show', $this->uuid),
            ],
        ];
    }
}
