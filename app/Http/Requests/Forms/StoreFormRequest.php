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
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'questions' => ['sometimes', 'array', 'list'],
            'questions.*' => ['required', 'array:type,prompt'],
            'questions.*.type' => ['required', 'string', 'in:discursive'],
            'questions.*.prompt' => ['required', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'questions.array' => 'As perguntas devem ser uma lista.',
            'questions.list' => 'As perguntas devem estar em uma lista ordenada.',
            'questions.*.array' => 'Cada pergunta deve conter somente tipo e enunciado.',
            'questions.*.required' => 'Preencha a pergunta.',
            'questions.*.type.required' => 'Informe o tipo da pergunta.',
            'questions.*.type.string' => 'O tipo da pergunta deve ser um texto.',
            'questions.*.type.in' => 'Somente perguntas discursivas são aceitas nesta etapa.',
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
