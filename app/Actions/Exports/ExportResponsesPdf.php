<?php

namespace App\Actions\Exports;

use App\Models\Form;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExportResponsesPdf
{
    public function handle(Form $form): string
    {
        $form->loadMissing('questions');
        $options = new Options;
        // CWE-200/CWE-79: no remote assets, PHP or PDF JavaScript; Blade escapes all participant data.
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(base_path('vendor/dompdf/dompdf/lib'));
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('exports.responses', [
            'form' => $form,
            'submissions' => $form->submissions()
                ->with(['answers.options' => fn ($query) => $query->orderBy('position')])->lazyById(100),
        ])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }
}
