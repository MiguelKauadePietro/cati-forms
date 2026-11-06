<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Form */
class FormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'public_url' => route('forms.public', ['form' => $this->public_id]),
            'title' => $this->title,
            'description' => $this->description,
            'request_contact_email' => $this->request_contact_email,
            'creator_id' => $this->creator_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
