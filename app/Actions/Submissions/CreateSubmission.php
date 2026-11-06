<?php

namespace App\Actions\Submissions;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

class CreateSubmission
{
    /** @param list<array{question_id: int, text?: string|null, option_ids?: list<int>, justification?: string|null}> $answers */
    public function handle(Form $form, array $answers, ?string $contactEmail = null): Submission
    {
        return DB::transaction(function () use ($form, $answers, $contactEmail): Submission {
            $submission = $form->submissions()->create([
                'contact_email' => $form->request_contact_email ? $contactEmail : null,
            ]);

            foreach ($answers as $input) {
                $answer = $submission->answers()->create([
                    'question_id' => $input['question_id'],
                    'text' => $input['text'] ?? null,
                    'justification' => $input['justification'] ?? null,
                ]);
                $answer->options()->attach($input['option_ids'] ?? []);
            }

            return $submission;
        });
    }
}
