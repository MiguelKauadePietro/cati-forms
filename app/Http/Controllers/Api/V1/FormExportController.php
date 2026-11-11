<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exports\ExportResponsesCsv;
use App\Actions\Exports\ExportResponsesPdf;
use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormExportController extends Controller
{
    public function csv(Form $form, ExportResponsesCsv $export): StreamedResponse
    {
        // RN02/RN10: auth middleware grants equal access, independent of authorship.
        return response()->streamDownload(function () use ($form, $export): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Não foi possível iniciar a exportação CSV.');
            }
            try {
                $export->write($form, $output);
            } finally {
                fclose($output);
            }
        }, 'formulario-'.$form->id.'-respostas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pdf(Form $form, ExportResponsesPdf $export): Response
    {
        return response($export->handle($form), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="formulario-'.$form->id.'-respostas.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
