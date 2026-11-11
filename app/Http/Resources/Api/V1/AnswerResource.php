<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Answer */
class AnswerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'text' => $this->text,
            'justification' => $this->justification,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
