<?php

namespace App\Domain\Book\Design;

final readonly class TextBox implements Element
{
    public const int MAX_LENGTH = 10000;

    /**
     * @param  list<TextSpan>  $spans
     */
    public function __construct(
        public string $id,
        public Box $box,
        public array $spans,
        public string $font = 'Libertinus Serif',
        public float $sizePt = 12.0,
        public float $lineHeight = 1.3,
        public Color $color = new Color('#000000'),
        public TextAlign $align = TextAlign::Left,
        public VerticalAlign $verticalAlign = VerticalAlign::Top,
        /** Relleno del cuadro, por ejemplo para tapar texto del PDF original. */
        public ?Color $fill = null,
    ) {
        if ($sizePt < 1 || $sizePt > 400) {
            throw new InvalidPageDesign('El tamaño de letra debe estar entre 1 y 400 pt.');
        }

        if ($lineHeight < 0.5 || $lineHeight > 5) {
            throw new InvalidPageDesign('El interlineado debe estar entre 0,5 y 5.');
        }

        if (array_sum(array_map(fn (TextSpan $s): int => mb_strlen($s->text), $spans)) > self::MAX_LENGTH) {
            throw new InvalidPageDesign('El cuadro de texto es demasiado largo.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function plainText(): string
    {
        return implode('', array_map(fn (TextSpan $s): string => $s->text, $this->spans));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'text',
            'x' => $this->box->x,
            'y' => $this->box->y,
            'width' => $this->box->width,
            'height' => $this->box->height,
            'font' => $this->font,
            'size_pt' => $this->sizePt,
            'line_height' => $this->lineHeight,
            'color' => $this->color->hex,
            'align' => $this->align->value,
            'valign' => $this->verticalAlign->value,
            'fill' => $this->fill?->hex,
            'spans' => array_map(fn (TextSpan $s): array => [
                'text' => $s->text,
                'bold' => $s->bold,
                'italic' => $s->italic,
                'color' => $s->color?->hex,
            ], $this->spans),
        ];
    }
}
