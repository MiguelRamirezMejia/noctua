<?php

use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\PageDoesNotFit;
use App\Domain\Printing\Pdf\CalibrationSheet;
use App\Domain\Printing\Pdf\PdfImposer;
use App\Domain\Printing\Pdf\TestDocument;
use App\Domain\Printing\Size;
use Illuminate\Support\Facades\Process;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/noctua-tests-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
});

afterEach(function () {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

function pdfPages(string $path): int
{
    $pdf = new Fpdi;
    $count = $pdf->setSourceFile($path);
    $pdf->cleanUp();

    return $count;
}

/**
 * Números de página visibles en cada cara del PDF impuesto, de izquierda a derecha.
 *
 * @return list<list<int>>
 */
function facesText(string $path): array
{
    $faces = [];
    for ($face = 1; $face <= pdfPages($path); $face++) {
        $text = Process::timeout(30)->run(['pdftotext', '-layout', '-f', (string) $face, '-l', (string) $face, $path, '-'])->throw()->output();
        preg_match_all('/P.gina (\d+)/u', $text, $m);
        $faces[] = array_map(intval(...), $m[1]);
    }

    return $faces;
}

function withoutPdftotext(): bool
{
    return (new ExecutableFinder)->find('pdftotext') === null;
}

it('genera frente y dorso por hoja en carta horizontal', function () {
    (new TestDocument)->render("{$this->dir}/src.pdf", 18);

    $plan = (new PdfImposer)->impose("{$this->dir}/src.pdf", "{$this->dir}/out.pdf");

    $pdf = new Fpdi;
    $pdf->setSourceFile("{$this->dir}/out.pdf");
    $size = $pdf->getTemplateSize($pdf->importPage(1));
    $pdf->cleanUp();

    expect($plan->sheetCount())->toBe(8)
        ->and(pdfPages("{$this->dir}/out.pdf"))->toBe(16)
        ->and(round($size['width'], 1))->toBe(279.4)
        ->and(round($size['height'], 1))->toBe(215.9);
});

it('coloca las páginas en el orden de imposición', function () {
    (new TestDocument)->render("{$this->dir}/src.pdf", 8);

    (new PdfImposer)->impose("{$this->dir}/src.pdf", "{$this->dir}/out.pdf", new ImpositionSettings(sheetsPerSignature: 2));

    expect(facesText("{$this->dir}/out.pdf"))->toBe([[8, 1], [2, 7], [6, 3], [4, 5]]);
})->skip(withoutPdftotext(...), 'Requiere pdftotext (poppler-utils).');

it('deja en blanco las posiciones de relleno', function () {
    (new TestDocument)->render("{$this->dir}/src.pdf", 5);

    (new PdfImposer)->impose("{$this->dir}/src.pdf", "{$this->dir}/out.pdf", new ImpositionSettings(sheetsPerSignature: 1));

    expect(facesText("{$this->dir}/out.pdf"))->toBe([[4, 1], [2, 3], [5], []]);
})->skip(withoutPdftotext(...), 'Requiere pdftotext (poppler-utils).');

it('produce exactamente los mismos bytes en cada ejecución', function () {
    (new TestDocument)->render("{$this->dir}/src.pdf", 16);
    $imposer = new PdfImposer;

    $imposer->impose("{$this->dir}/src.pdf", "{$this->dir}/a.pdf");
    $imposer->impose("{$this->dir}/src.pdf", "{$this->dir}/b.pdf");

    expect(hash_file('sha256', "{$this->dir}/a.pdf"))->toBe(hash_file('sha256', "{$this->dir}/b.pdf"));
});

it('rechaza documentos con páginas que no caben en media hoja', function () {
    (new TestDocument)->render("{$this->dir}/a4.pdf", 4, new Size(210, 297));

    (new PdfImposer)->impose("{$this->dir}/a4.pdf", "{$this->dir}/out.pdf");
})->throws(PageDoesNotFit::class);

it('genera la hoja de calibración de dos caras de forma determinista', function () {
    (new CalibrationSheet)->render("{$this->dir}/a.pdf");
    (new CalibrationSheet)->render("{$this->dir}/b.pdf");

    expect(pdfPages("{$this->dir}/a.pdf"))->toBe(2)
        ->and(hash_file('sha256', "{$this->dir}/a.pdf"))->toBe(hash_file('sha256', "{$this->dir}/b.pdf"));
});
