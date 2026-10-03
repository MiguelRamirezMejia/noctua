<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Printing\Clip;
use App\Domain\Printing\Size;
use setasign\Fpdi\Fpdi;

/**
 * FPDI sin fuentes de no determinismo: mismo documento de entrada → mismos bytes.
 */
final class DeterministicFpdi extends Fpdi
{
    private const float POINTS_PER_MM = 72 / 25.4;

    public function __construct(private readonly Size $sheet)
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
     * Ejecuta el dibujo recortado al rectángulo dado (mm desde la esquina superior izquierda).
     *
     * @param  callable(): void  $draw
     */
    public function clipped(Clip $clip, callable $draw): void
    {
        $this->_out(sprintf(
            'q %.3F %.3F %.3F %.3F re W n',
            $clip->xMm * self::POINTS_PER_MM,
            ($this->sheet->heightMm - $clip->yMm) * self::POINTS_PER_MM,
            $clip->widthMm * self::POINTS_PER_MM,
            -$clip->heightMm * self::POINTS_PER_MM,
        ));

        try {
            $draw();
        } finally {
            $this->_out('Q');
        }
    }

    /** @var array<string, int> modo de fusión => número de objeto del ExtGState */
    private array $blendStates = [];

    /**
     * Rectángulo de color con fusión «multiplicar»: tiñe lo que hay debajo
     * (el blanco toma el color y lo oscuro se mantiene).
     *
     * @param  array{int, int, int}  $rgb
     */
    public function multiplyRect(float $xMm, float $yMm, float $widthMm, float $heightMm, array $rgb): void
    {
        $this->blendStates['Multiply'] ??= 0;
        $index = array_search('Multiply', array_keys($this->blendStates), true) + 1;

        $this->_out(sprintf(
            'q /GS%d gs %.3F %.3F %.3F rg %.3F %.3F %.3F %.3F re f Q',
            $index,
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $xMm * self::POINTS_PER_MM,
            ($this->sheet->heightMm - $yMm) * self::POINTS_PER_MM,
            $widthMm * self::POINTS_PER_MM,
            -$heightMm * self::POINTS_PER_MM,
        ));
    }

    protected function _putresources(): void
    {
        foreach (array_keys($this->blendStates) as $mode) {
            $this->_newobj();
            $this->blendStates[$mode] = is_int($this->n) ? $this->n : 0;
            $this->_put("<</Type /ExtGState /BM /{$mode}>>");
            $this->_put('endobj');
        }

        parent::_putresources();
    }

    protected function _putresourcedict(): void
    {
        parent::_putresourcedict();

        if ($this->blendStates === []) {
            return;
        }

        $this->_put('/ExtGState <<');
        $i = 1;
        foreach ($this->blendStates as $object) {
            $this->_put('/GS'.$i++." {$object} 0 R");
        }
        $this->_put('>>');
    }

    /**
     * Las fuentes estándar de FPDF usan Windows-1252.
     */
    public static function latin1(string $utf8): string
    {
        return mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
    }
}
