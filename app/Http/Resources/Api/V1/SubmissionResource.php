<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Submission */
class SubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'contact_email' => $this->contact_email,
            'created_at' => $this->created_at?->toISOString(),
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
