<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $question_id
 * @property string|null $text
 * @property string|null $justification
 */
#[Fillable(['question_id', 'text', 'justification'])]
class Answer extends Model
{
    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsToMany<QuestionOption, $this> */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(QuestionOption::class, 'answer_options');
    }
}
