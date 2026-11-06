<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateForm
{
    /** @param list<array{type: string, prompt: string, options?: list<array{label: string}>}> $questions */
    public function handle(User $user, string $title, string $description, array $questions = [], bool $requestContactEmail = false): Form
    {
        return DB::transaction(function () use ($user, $title, $description, $questions, $requestContactEmail): Form {
            $form = $user->forms()->create([
                'title' => $title,
                'description' => $description,
                'request_contact_email' => $requestContactEmail,
            ]);

            foreach ($questions as $index => $question) {
                $createdQuestion = $form->questions()->create([
                    'type' => $question['type'],
                    'prompt' => $question['prompt'],
                    'position' => $index + 1,
                ]);
                foreach ($question['options'] ?? [] as $optionIndex => $option) {
                    $createdQuestion->options()->create([
                        'label' => $option['label'],
                        'position' => $optionIndex + 1,
                    ]);
                }
            }

            return $form->load('questions.options');
        });
    }
}
