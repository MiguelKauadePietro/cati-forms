<?php

use App\Actions\Forms\CreateForm;
use App\Actions\Submissions\CreateSubmission;
use App\Models\Form;
use App\Models\User;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

function exportableForm(bool $email = true): Form
{
    return app(CreateForm::class)->handle(User::factory()->create(), 'Pesquisa ação, "CATI"', "Descrição com acentos: opinião e participação.\nSegunda linha.", [
        ['type' => 'discursive', 'prompt' => "Opinião, \"sincera\"?\nDetalhes"],
        ['type' => 'multiple_choice', 'prompt' => 'Áreas?', 'options' => [['label' => 'Desenvolvimento, web'], ['label' => 'Design "visual"']]],
        ['type' => 'single_choice_with_justification', 'prompt' => 'Formato?', 'options' => [['label' => 'Reunião online']]],
    ], $email);
}

function addExportSubmission(Form $form, string $text = "Ação, \"opinião\"; detalhes\\fim\nSegunda linha."): void
{
    app(CreateSubmission::class)->handle($form, [
        ['question_id' => $form->questions[0]->id, 'text' => $text],
        ['question_id' => $form->questions[1]->id, 'option_ids' => $form->questions[1]->options->pluck('id')->reverse()->values()->all()],
        ['question_id' => $form->questions[2]->id, 'option_ids' => [$form->questions[2]->options[0]->id], 'justification' => "Porque facilita a participação.\nTambém economiza tempo."],
    ], $form->request_contact_email ? 'participante@example.com' : null);
}

function exportedCsvRows(string $csv): array
{
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

function exportPdfText(string $pdf): string
{
    $binary = (new ExecutableFinder)->find('pdftotext');
    if (! $binary) {
        test()->markTestSkipped('Optional pdftotext is required to verify extracted PDF text.');
    }
    $process = new Process([$binary, '-enc', 'UTF-8', '-', '-']);
    $process->setInput($pdf)->mustRun();

    return $process->getOutput();
}

test('CSV includes every submission and all question types only from the requested form', function () {
    $form = exportableForm();
    $other = exportableForm();
    addExportSubmission($other, 'EXCLUDED_OTHER_FORM');
    foreach (range(1, 13) as $index) {
        addExportSubmission($form);
    }
    $this->actingAs(User::factory()->create());
    $response = $this->get('/api/v1/forms/'.$form->id.'/exports/csv?page=2&creator_id=999')
        ->assertOk()->assertDownload('formulario-'.$form->id.'-respostas.csv')
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $csv = $response->streamedContent();
    expect($csv)->not->toContain('EXCLUDED_OTHER_FORM');
    $rows = exportedCsvRows($csv);
    expect($rows)->toHaveCount(14);
    expect($rows[0])->toBe(['Formulário ID', 'Formulário', 'Envio ID', 'Enviado em (UTC)', 'E-mail de contato', "P1 — Opinião, \"sincera\"?\nDetalhes", 'P2 — Áreas?', 'P3 — Formato?', 'P3 — Justificativa']);
    expect($rows[1])->toBe([
        (string) $form->id, $form->title, (string) $form->submissions()->first()->id,
        $form->submissions()->first()->created_at->toISOString(), 'participante@example.com',
        "Ação, \"opinião\"; detalhes\\fim\nSegunda linha.", "Desenvolvimento, web\nDesign \"visual\"", 'Reunião online',
        "Porque facilita a participação.\nTambém economiza tempo.",
    ]);
    expect(array_column(array_slice($rows, 1), 2))->toBe($form->submissions()->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all());
});

test('CSV neutralizes formula cells without creating extra columns or changing stored answers', function (string $text) {
    $form = exportableForm();
    $form->update(['title' => '=FORMULA()']);
    $form->questions[1]->options[0]->update(['label' => '+DANGEROUS']);
    $form->questions[2]->options[0]->update(['label' => '@DANGEROUS']);
    addExportSubmission($form, $text);
    $submission = $form->submissions()->sole();
    $submission->update(['contact_email' => '+person@example.com']);
    $submission->answers()->where('question_id', $form->questions[2]->id)->update(['justification' => '-DANGEROUS']);
    $csv = $this->actingAs(User::factory()->create())->get('/api/v1/forms/'.$form->id.'/exports/csv')->assertOk()->streamedContent();
    $rows = exportedCsvRows($csv);
    expect($rows[1])->toHaveCount(9);
    expect($rows[1][1])->toBe("'=FORMULA()");
    expect($rows[1][4])->toBe("'+person@example.com");
    expect($rows[1][5])->toBe("'".$text);
    expect($rows[1][6])->toStartWith("'+DANGEROUS");
    expect($rows[1][7])->toBe("'@DANGEROUS");
    expect($rows[1][8])->toBe("'-DANGEROUS");
    expect($submission->answers()->where('question_id', $form->questions[0]->id)->sole()->text)->toBe($text);
})->with(['=1+1', '+SUM(1,2)', '-1+2', '@SUM(1,2)', "\t=1+1", "\r=1+1", "\n=1+1", '  =1+1', "\u{FEFF}=1+1", '＝1+1', '=1+1";=2+2']);

test('PDF download is a real multi-page PDF containing all answers and only this form', function () {
    $form = exportableForm();
    $other = exportableForm();
    addExportSubmission($other, 'EXCLUDED_OTHER_FORM');
    addExportSubmission($form, "<script>alert('literal')</script>\n".str_repeat("Resposta extensa com acentuação.\n", 130).'END_LONG_ANSWER');
    addExportSubmission($form, 'LAST_SUBMISSION');
    $response = $this->actingAs(User::factory()->create())->get('/api/v1/forms/'.$form->id.'/exports/pdf')
        ->assertOk()->assertDownload('formulario-'.$form->id.'-respostas.pdf')
        ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $pdf = $response->getContent();
    expect($pdf)->toStartWith('%PDF-')->toContain('%%EOF');
    expect(preg_match_all('~/Type\s*/Page\b~', $pdf))->toBeGreaterThanOrEqual(3);
    $text = exportPdfText($pdf);
    $normalized = preg_replace('/\s+/u', ' ', $text);
    foreach ([$form->title, 'Descrição com acentos: opinião e participação.', 'Segunda linha.', 'Opinião, "sincera"?', 'Áreas?', 'Formato?', 'Desenvolvimento, web', 'Design "visual"', 'Reunião online', 'Justificativa:', 'Porque facilita a participação.', 'participante@example.com', 'END_LONG_ANSWER', 'LAST_SUBMISSION', "<script>alert('literal')</script>"] as $expected) {
        expect($normalized)->toContain($expected);
    }
    foreach ($form->submissions as $submission) {
        expect($normalized)->toContain('Envio #'.$submission->id);
    }
    expect($text)->not->toContain('EXCLUDED_OTHER_FORM');
});

test('PDF template escapes HTML and never treats participant input as resources', function () {
    $form = exportableForm();
    addExportSubmission($form, '<img src="file:///etc/passwd"><script>alert(1)</script>');
    $html = view('exports.responses', ['form' => $form, 'submissions' => $form->submissions()->with('answers.options')->get()])->render();
    expect($html)->toContain('&lt;img src=', '&lt;script&gt;')->not->toContain('<img ', '<script>');
});

test('exports require authentication even when the visitor knows the public link', function (string $format) {
    $form = exportableForm();
    addExportSubmission($form);
    $this->get('/api/v1/forms/'.$form->id.'/exports/'.$format)->assertUnauthorized()
        ->assertJsonStructure(['message'])->assertHeaderMissing('Content-Disposition')->assertHeaderMissing('Location');
    $this->get('/api/v1/public/forms/'.$form->public_id.'/exports/'.$format)->assertNotFound();
})->with(['csv', 'pdf']);

test('missing forms return JSON 404 rather than a download', function (string $format) {
    $this->actingAs(User::factory()->create())->get('/api/v1/forms/999999/exports/'.$format)
        ->assertNotFound()->assertJsonStructure(['message'])->assertHeaderMissing('Content-Disposition');
})->with(['csv', 'pdf']);

test('empty forms export headers or a readable PDF without contact email when disabled', function () {
    $form = exportableForm(false);
    $this->actingAs(User::factory()->create());
    $rows = exportedCsvRows($this->get('/api/v1/forms/'.$form->id.'/exports/csv')->assertOk()->streamedContent());
    expect($rows)->toHaveCount(1);
    expect($rows[0])->toHaveCount(8)->not->toContain('E-mail de contato');
    $pdf = $this->get('/api/v1/forms/'.$form->id.'/exports/pdf')->assertOk()->getContent();
    expect($pdf)->toStartWith('%PDF-');
    $text = exportPdfText($pdf);
    expect($text)->toContain('Nenhuma resposta recebida.')->not->toContain('E-mail de contato:');
});

test('exports support submissions without questions and optional missing email', function () {
    $form = User::factory()->create()->forms()->create(['title' => 'Sem perguntas', 'description' => 'Descrição', 'request_contact_email' => true]);
    $form->submissions()->create();
    $this->actingAs(User::factory()->create());
    $rows = exportedCsvRows($this->get('/api/v1/forms/'.$form->id.'/exports/csv')->assertOk()->streamedContent());
    expect($rows)->toHaveCount(2);
    expect($rows[1])->toHaveCount(5);
    expect($rows[1][4])->toBe('');
    $text = exportPdfText($this->get('/api/v1/forms/'.$form->id.'/exports/pdf')->assertOk()->getContent());
    expect($text)->toContain('Envio sem perguntas.')->not->toContain('E-mail de contato:');
});
