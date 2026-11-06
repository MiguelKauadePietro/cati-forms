<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Form */
class PublicFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'request_contact_email' => $this->request_contact_email,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
