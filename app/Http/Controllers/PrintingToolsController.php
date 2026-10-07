<?php

namespace App\Http\Controllers;

use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use App\Domain\Printing\Offset;
use App\Domain\Printing\Pdf\CalibrationSheet;
use App\Domain\Printing\Pdf\TestDocument;
use App\Domain\Printing\PrinterProfile;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrintingToolsController extends Controller
{
    public function calibration(Request $request, CalibrationSheet $sheet): BinaryFileResponse
    {
        $request->validate([
            'front_offset_x' => ['nullable', 'numeric', 'between:-10,10'],
            'front_offset_y' => ['nullable', 'numeric', 'between:-10,10'],
            'back_offset_x' => ['nullable', 'numeric', 'between:-10,10'],
            'back_offset_y' => ['nullable', 'numeric', 'between:-10,10'],
        ]);

        $path = $this->temporaryPdf();
        $sheet->render($path, new PrinterProfile(
            frontOffset: new Offset($request->float('front_offset_x'), $request->float('front_offset_y')),
            backOffset: new Offset($request->float('back_offset_x'), $request->float('back_offset_y')),
        ));

        app(ActivityLog::class)->record(ActivityType::CalibrationDownloaded, 'Descargó la hoja de calibración.');

        return response()->download($path, 'calibracion.pdf')->deleteFileAfterSend();
    }

    public function testDocument(Request $request, TestDocument $document): BinaryFileResponse
    {
        $request->validate(['pages' => ['nullable', 'integer', 'between:1,400']]);

        $pages = $request->integer('pages', 16);
        $path = $this->temporaryPdf();
        $document->render($path, $pages);

        app(ActivityLog::class)->record(ActivityType::TestDocumentDownloaded, "Descargó el documento de prueba de {$pages} páginas.");

        return response()->download($path, "prueba-{$pages}-paginas.pdf")->deleteFileAfterSend();
    }

    private function temporaryPdf(): string
    {
        return tempnam(sys_get_temp_dir(), 'noctua-') ?: throw new RuntimeException('No se pudo crear un archivo temporal.');
    }
}
