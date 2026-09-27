<?php

namespace App\Domain\Imposition;

/**
 * Calcula el orden de páginas por hoja.
 *
 * En un cuadernillo de 4n páginas, para la hoja i ∈ [1..n] (1 = exterior):
 *   Frente: izq = 4n − 2i + 2, der = 2i − 1
 *   Dorso:  izq = 2i,          der = 4n − 2i + 1
 *
 * El dorso asume volteo por el borde corto de la hoja apaisada: la página del
 * dorso queda detrás de la del frente al girar la hoja como la página de un libro.
 */
final class Imposer
{
    public function plan(ImpositionSpec $spec): ImpositionPlan
    {
        $signatures = [];
        $firstPage = 1;

        while ($firstPage <= $spec->pageCount) {
            $sheets = $spec->sheetsPerSignature;
            $remaining = $spec->pageCount - $firstPage + 1;

            if ($spec->shortLastSignature && $remaining < 4 * $sheets) {
                $sheets = intdiv($remaining + 3, 4);
            }

            $signatures[] = $this->signature(count($signatures) + 1, $firstPage, $sheets, $spec);
            $firstPage += 4 * $sheets;
        }

        return new ImpositionPlan($spec, $signatures);
    }

    private function signature(int $number, int $firstPage, int $n, ImpositionSpec $spec): Signature
    {
        $sheets = [];

        for ($i = 1; $i <= $n; $i++) {
            // La hoja exterior no se desplaza; cada hoja anidada acumula un paso de creep.
            $creep = ($i - 1) * $spec->creepMmPerSheet;
            $slot = function (int $local) use ($firstPage, $spec, $creep): Slot {
                $page = $firstPage + $local - 1;

                return new Slot($page <= $spec->pageCount ? $page : null, $creep);
            };

            $sheets[] = new Sheet(
                signature: $number,
                index: $i,
                front: new SheetSide($slot(4 * $n - 2 * $i + 2), $slot(2 * $i - 1)),
                back: new SheetSide($slot(2 * $i), $slot(4 * $n - 2 * $i + 1)),
            );
        }

        return new Signature($number, $firstPage, $sheets);
    }
}
