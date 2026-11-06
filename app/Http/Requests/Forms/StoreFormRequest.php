<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'request_contact_email' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'array', 'list'],
            'questions.*' => ['required', 'array:type,prompt,options'],
            'questions.*.type' => ['required', 'string', 'in:discursive,multiple_choice,single_choice_with_justification'],
            'questions.*.prompt' => ['required', 'string', 'max:10000'],
            'questions.*.options' => ['sometimes', 'array', 'list'],
            'questions.*.options.*' => ['required', 'array:label'],
            'questions.*.options.*.label' => ['required', 'string', 'max:10000'],
        ];

        $questions = $this->input('questions');
        if (is_array($questions)) {
            foreach ($questions as $index => $question) {
                if (! is_int($index) || ! is_array($question)) {
                    continue;
                }
                if (in_array($question['type'] ?? null, ['multiple_choice', 'single_choice_with_justification'], true)) {
                    $rules["questions.$index.options"] = ['required', 'array', 'list', 'min:1'];
                } elseif (($question['type'] ?? null) === 'discursive') {
                    $rules["questions.$index.options"] = ['sometimes', 'array', 'list', 'max:0'];
                }
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'request_contact_email.boolean' => 'Informe se o formulário deve solicitar e-mail de contato.',
            'questions.*.options.required' => 'Adicione pelo menos uma alternativa.',
            'questions.*.options.min' => 'Adicione pelo menos uma alternativa.',
            'questions.*.options.array' => 'As alternativas devem ser uma lista.',
            'questions.*.options.list' => 'As alternativas devem estar em uma lista ordenada.',
            'questions.*.options.max' => 'Perguntas discursivas não aceitam alternativas.',
            'questions.*.options.*.required' => 'Preencha a alternativa.',
            'questions.*.options.*.array' => 'Cada alternativa deve conter somente o texto.',
            'questions.*.options.*.label.required' => 'Informe o texto da alternativa.',
            'questions.*.options.*.label.string' => 'A alternativa deve ser um texto.',
            'questions.*.options.*.label.max' => 'A alternativa deve ter no máximo 10.000 caracteres.',
            'questions.array' => 'As perguntas devem ser uma lista.',
            'questions.list' => 'As perguntas devem estar em uma lista ordenada.',
            'questions.*.array' => 'Cada pergunta deve conter somente tipo, enunciado e alternativas.',
            'questions.*.required' => 'Preencha a pergunta.',
            'questions.*.type.required' => 'Informe o tipo da pergunta.',
            'questions.*.type.string' => 'O tipo da pergunta deve ser um texto.',
            'questions.*.type.in' => 'O tipo de pergunta não é aceito.',
            'questions.*.prompt.required' => 'Informe o enunciado da pergunta.',
            'questions.*.prompt.string' => 'O enunciado deve ser um texto.',
            'questions.*.prompt.max' => 'O enunciado deve ter no máximo 10.000 caracteres.',
            'title.required' => 'Informe o título do formulário.',
            'title.string' => 'O título deve ser um texto.',
            'title.max' => 'O título deve ter no máximo 255 caracteres.',
            'description.required' => 'Informe a descrição do formulário.',
            'description.string' => 'A descrição deve ser um texto.',
            'description.max' => 'A descrição deve ter no máximo 10.000 caracteres.',
        ];
    }
}
