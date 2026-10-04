<?php

namespace App\Domain\Book;

use App\Domain\Book\Design\Color;

/**
 * Marco decorativo alrededor de las páginas del libro. Como los folios, va en
 * la página completa (no se reduce con los márgenes) y sus medidas se
 * expresan tal como quedan impresas.
 */
final readonly class PageBorder
{
    public const float MIN_WIDTH_PT = 0.2;

    public const float MAX_WIDTH_PT = 4.0;

    public const float MIN_INSET_MM = 2.0;

    public const float MAX_INSET_MM = 40.0;

    public const float MIN_UNIT_MM = 3.0;

    public const float MAX_UNIT_MM = 15.0;

    /**
     * @param  float  $widthPt  Grosor del trazo.
     * @param  float  $insetMm  Distancia al borde de la página.
     * @param  float  $unitMm  Tamaño de los adornos (ondas, festón, greca, estrellas…).
     */
    public function __construct(
        public BorderStyle $style = BorderStyle::Line,
        public Color $color = new Color('#5b3a1e'),
        public float $widthPt = 0.8,
        public float $insetMm = 8.0,
        public float $unitMm = 6.0,
    ) {
        if ($widthPt < self::MIN_WIDTH_PT || $widthPt > self::MAX_WIDTH_PT) {
            throw new InvalidPageSequence('El grosor del marco debe estar entre '.self::MIN_WIDTH_PT.' y '.self::MAX_WIDTH_PT.' pt.');
        }

        if ($insetMm < self::MIN_INSET_MM || $insetMm > self::MAX_INSET_MM) {
            throw new InvalidPageSequence('La distancia del marco al borde debe estar entre '.self::MIN_INSET_MM.' y '.self::MAX_INSET_MM.' mm.');
        }

        if ($unitMm < self::MIN_UNIT_MM || $unitMm > self::MAX_UNIT_MM) {
            throw new InvalidPageSequence('El tamaño de los adornos debe estar entre '.self::MIN_UNIT_MM.' y '.self::MAX_UNIT_MM.' mm.');
        }
    }

    /**
     * Datos para Typst, en unidades de la página de origen: al imprimir, la
     * página se escala y el marco con ella.
     *
     * @return array{style: string, color: string, width_pt: float, inset_mm: float, unit_mm: float}
     */
    public function forPage(float $printScale): array
    {
        return [
            'style' => $this->style->value,
            'color' => $this->color->hex,
            'width_pt' => $this->widthPt / $printScale,
            'inset_mm' => $this->insetMm / $printScale,
            'unit_mm' => $this->unitMm / $printScale,
        ];
    }

    /**
     * @return array{style: string, color: string, width_pt: float, inset_mm: float, unit_mm: float}
     */
    public function toArray(): array
    {
        return $this->forPage(1.0);
    }

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data)) {
            return null;
        }

        $number = fn (string $key, float $default): float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : $default;
        $style = is_string($data['style'] ?? null) ? BorderStyle::tryFrom($data['style']) : null;
        $color = is_string($data['color'] ?? null) ? $data['color'] : '#5b3a1e';

        return new self(
            $style ?? BorderStyle::Line,
            new Color($color),
            $number('width_pt', 0.8),
            $number('inset_mm', 8.0),
            $number('unit_mm', 6.0),
        );
    }
}
