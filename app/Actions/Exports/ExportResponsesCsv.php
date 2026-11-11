<?php

namespace App\Actions\Exports;

use App\Models\Form;

class ExportResponsesCsv
{
    /** @param resource $output */
    public function write(Form $form, $output): void
    {
        $form->loadMissing('questions');
        fwrite($output, "\xEF\xBB\xBF");
        $headers = ['Formulário ID', 'Formulário', 'Envio ID', 'Enviado em (UTC)'];
        if ($form->request_contact_email) {
            $headers[] = 'E-mail de contato';
        }
        foreach ($form->questions as $question) {
            $headers[] = 'P'.$question->position.' — '.$question->prompt;
            if ($question->type === 'single_choice_with_justification') {
                $headers[] = 'P'.$question->position.' — Justificativa';
            }
        }
        $this->row($output, $headers);

        // ASVS V5.3.4: ORM-scoped reads keep identifiers out of raw SQL.
        foreach ($form->submissions()->with(['answers.options' => fn ($query) => $query->orderBy('position')])->lazyById(200) as $submission) {
            $row = [(string) $form->id, $form->title, (string) $submission->id, $submission->created_at?->toISOString() ?? ''];
            if ($form->request_contact_email) {
                $row[] = $submission->contact_email ?? '';
            }
            $answers = $submission->answers->keyBy('question_id');
            foreach ($form->questions as $question) {
                $answer = $answers->get($question->id);
                $row[] = $question->type === 'discursive'
                    ? ($answer->text ?? '')
                    : ($answer?->options->pluck('label')->implode("\n") ?? '');
                if ($question->type === 'single_choice_with_justification') {
                    $row[] = $answer->justification ?? '';
                }
            }
            $this->row($output, $row);
        }
    }

    /**
     * @param  resource  $output
     * @param  list<string>  $values
     */
    private function row($output, array $values): void
    {
        // OWASP CSV Injection: protect every cell, including metadata; fputcsv escapes quotes and delimiters.
        $safe = array_map(static function (string $value): string {
            return preg_match('/^[\p{Z}\p{C}\s]*[=+\-@＝＋－＠]|^[\t\r\n]/u', $value) === 1 ? "'".$value : $value;
        }, $values);
        fputcsv($output, $safe, ',', '"', '', "\r\n");
    }
}
