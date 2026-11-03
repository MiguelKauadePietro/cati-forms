<?php

namespace App\Http\Resources\Api\V1;

use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuestionOption */
class QuestionOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'position' => $this->position];
    }
}
