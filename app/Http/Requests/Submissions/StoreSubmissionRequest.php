<?php

namespace App\Http\Requests\Submissions;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function formModel(): Form
    {
        $form = $this->route('form');
        assert($form instanceof Form);

        return $form;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $form = $this->formModel()->loadMissing('questions.options');
        $questions = $form->questions->keyBy('id');
        $rules = [
            'contact_email' => $form->request_contact_email ? ['nullable', 'string', 'email:rfc', 'max:254'] : ['prohibited', 'nullable', 'string'],
            'answers' => ['present', 'array', 'list', 'size:'.$questions->count()],
            'answers.*' => ['required', 'array:question_id,text,option_ids,justification'],
            'answers.*.question_id' => ['required', 'integer:strict', 'distinct', Rule::in($questions->keys()->all())],
        ];

        $answers = $this->input('answers');
        if (! is_array($answers)) {
            return $rules;
        }

        foreach ($answers as $index => $answer) {
            if (! is_int($index) || ! is_array($answer)) {
                continue;
            }
            $id = $answer['question_id'] ?? null;
            if (! is_int($id)) {
                continue;
            }
            $question = $questions->get((int) $id);
            if (! $question) {
                continue;
            }

            $prefix = 'answers.'.$index;
            $discursive = $question->type === 'discursive';
            $single = $question->type === 'single_choice_with_justification';
            $rules[$prefix.'.text'] = $discursive ? ['required', 'string', 'max:10000'] : ['prohibited', 'nullable', 'string'];
            $rules[$prefix.'.justification'] = $single ? ['required', 'string', 'max:10000'] : ['prohibited', 'nullable', 'string'];
            $rules[$prefix.'.option_ids'] = $discursive ? ['prohibited', 'nullable', 'array', 'list'] : ['required', 'array', 'list', 'min:1'];
            if (! $discursive) {
                if ($single) {
                    $rules[$prefix.'.option_ids'][] = 'size:1';
                }
                $rules[$prefix.'.option_ids.*'] = ['required', 'integer:strict', 'distinct', Rule::in($question->options->pluck('id')->all())];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answers.present' => 'Envie as respostas do formulário.',
            'answers.size' => 'Responda todas as perguntas do formulário, uma única vez.',
            'answers.*.question_id.in' => 'A pergunta não pertence a este formulário.',
            'answers.*.question_id.distinct' => 'Cada pergunta deve ser respondida uma única vez.',
            'answers.*.text.required' => 'Preencha a resposta.',
            'answers.*.justification.required' => 'Informe a justificativa da escolha.',
            'answers.*.option_ids.required' => 'Selecione pelo menos uma alternativa.',
            'answers.*.option_ids.size' => 'Selecione somente uma alternativa.',
            'answers.*.option_ids.*.in' => 'A alternativa não pertence a esta pergunta.',
            'answers.*.option_ids.*.distinct' => 'Não repita a mesma alternativa.',
            'contact_email.email' => 'Informe um e-mail válido ou deixe o campo vazio.',
            'contact_email.prohibited' => 'Este formulário não solicita e-mail de contato.',
        ];
    }
}
