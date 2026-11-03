<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Question */
class QuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'prompt' => $this->prompt,
            'position' => $this->position,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
