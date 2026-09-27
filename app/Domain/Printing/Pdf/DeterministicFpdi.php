<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Printing\Size;
use setasign\Fpdi\Fpdi;

/**
 * FPDI sin fuentes de no determinismo: mismo documento de entrada → mismos bytes.
 */
final class DeterministicFpdi extends Fpdi
{
    public function __construct(Size $sheet)
    {
        $short = min($sheet->widthMm, $sheet->heightMm);
        $long = max($sheet->widthMm, $sheet->heightMm);

        parent::__construct($sheet->isLandscape() ? 'L' : 'P', 'mm', [$short, $long]);

        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        $this->SetCreator('Noctua');
    }

    /**
     * FPDF fija CreationDate = time() en _enddoc() y la formatea con la zona horaria
     * del proceso; se sustituye por el epoch formateado en UTC.
     */
    protected function _putinfo(): void
    {
        $this->CreationDate = 0;
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            parent::_putinfo();
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    /**
     * Las fuentes estándar de FPDF usan Windows-1252.
     */
    public static function latin1(string $utf8): string
    {
        return mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
    }
}
