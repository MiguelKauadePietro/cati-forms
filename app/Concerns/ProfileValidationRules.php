<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /** @return array<string, array<int, Unique|string>> */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')->ignore($userId)],
        ];
    }
}
