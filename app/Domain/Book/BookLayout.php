<?php

namespace App\Domain\Book;

use App\Domain\Printing\Size;

/**
 * Ajustes generales del libro. Los márgenes son en espejo (el interior va
 * siempre del lado del lomo) y se expresan tal como quedan impresos: el
 * contenido de cada página se reduce y se centra dentro de ellos.
 */
final readonly class BookLayout
{
    public const float MAX_MARGIN_MM = 60.0;

    public function __construct(
        public float $marginTopMm = 0.0,
        public float $marginBottomMm = 0.0,
        public float $marginInsideMm = 0.0,
        public float $marginOutsideMm = 0.0,
        /** Marco decorativo de las páginas; null = sin marco. */
        public ?PageBorder $border = null,
    ) {
        foreach ([$marginTopMm, $marginBottomMm, $marginInsideMm, $marginOutsideMm] as $margin) {
            if ($margin < 0 || $margin > self::MAX_MARGIN_MM) {
                throw new InvalidPageSequence('Los márgenes deben estar entre 0 y '.self::MAX_MARGIN_MM.' mm.');
            }
        }
    }

    public function hasMargins(): bool
    {
        return $this->marginTopMm + $this->marginBottomMm + $this->marginInsideMm + $this->marginOutsideMm > 0;
    }

    /**
     * Dónde va el contenido de una página: escala y desplazamiento en mm de la
     * página de origen.
     *
     * @param  float  $printScale  Escala con la que la página llegará al papel.
     * @param  int  $position  Posición en el libro: las impares van a la derecha (lomo a la izquierda).
     */
    public function frame(Size $page, float $printScale, int $position): ContentFrame
    {
        if (! $this->hasMargins()) {
            return new ContentFrame(1.0, 0.0, 0.0);
        }

        $top = $this->marginTopMm / $printScale;
        $bottom = $this->marginBottomMm / $printScale;
        $inside = $this->marginInsideMm / $printScale;
        $outside = $this->marginOutsideMm / $printScale;
        $recto = $position % 2 === 1;

        $availableWidth = max(1.0, $page->widthMm - $inside - $outside);
        $availableHeight = max(1.0, $page->heightMm - $top - $bottom);
        $scale = min(1.0, $availableWidth / $page->widthMm, $availableHeight / $page->heightMm);

        $left = $recto ? $inside : $outside;

        return new ContentFrame(
            $scale,
            $left + ($availableWidth - $page->widthMm * $scale) / 2,
            $top + ($availableHeight - $page->heightMm * $scale) / 2,
        );
    }

    /**
     * @return array{margin_top_mm: float, margin_bottom_mm: float, margin_inside_mm: float, margin_outside_mm: float, border: array{style: string, color: string, width_pt: float, inset_mm: float, unit_mm: float}|null}
     */
    public function toArray(): array
    {
        return [
            'margin_top_mm' => $this->marginTopMm,
            'margin_bottom_mm' => $this->marginBottomMm,
            'margin_inside_mm' => $this->marginInsideMm,
            'margin_outside_mm' => $this->marginOutsideMm,
            'border' => $this->border?->toArray(),
        ];
    }

    public static function fromArray(mixed $data): self
    {
        $d = is_array($data) ? $data : [];
        $mm = fn (string $key): float => is_numeric($d[$key] ?? null) ? (float) $d[$key] : 0.0;

        return new self($mm('margin_top_mm'), $mm('margin_bottom_mm'), $mm('margin_inside_mm'), $mm('margin_outside_mm'), PageBorder::fromArray($d['border'] ?? null));
    }
}
