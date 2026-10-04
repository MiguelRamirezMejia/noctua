<?php

namespace App\Domain\Book\Extraction;

/**
 * Línea de texto del PDF de origen, en puntos desde la esquina superior izquierda.
 */
final readonly class TextLine
{
    /**
     * @param  list<TextRun>  $runs  Fragmentos con estilo; si está vacío se usa $text sin estilo.
     */
    public function __construct(
        public string $text,
        public float $xMin,
        public float $yMin,
        public float $xMax,
        public float $yMax,
        public array $runs = [],
        /** Cuerpo en puntos si el PDF lo indica; si no, se estima por la altura. */
        public ?float $sizePt = null,
        /** Familia tal como figura en el PDF (sin prefijo de subconjunto). */
        public ?string $family = null,
    ) {}

    public function size(): float
    {
        return $this->sizePt ?? max(1.0, ($this->yMax - $this->yMin) / 1.2);
    }

    public function center(): float
    {
        return ($this->xMin + $this->xMax) / 2;
    }

    public function isBold(): bool
    {
        return $this->runs !== [] && array_filter($this->runs, fn (TextRun $r): bool => ! $r->bold && trim($r->text) !== '') === [];
    }

    /**
     * @return list<TextRun>
     */
    public function styledRuns(): array
    {
        return $this->runs !== [] ? $this->runs : [new TextRun($this->text)];
    }
}
