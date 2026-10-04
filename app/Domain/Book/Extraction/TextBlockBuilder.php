<?php

namespace App\Domain\Book\Extraction;

use App\Domain\Book\Design\Box;
use App\Domain\Book\Design\Color;
use App\Domain\Book\Design\TextAlign;
use App\Domain\Book\Design\TextBox;
use App\Domain\Book\Design\TextSpan;
use App\Domain\Book\Design\VerticalAlign;

/**
 * Agrupa las líneas extraídas de una página en párrafos y los convierte en
 * cuadros de texto editables que ocupan el mismo lugar.
 */
final readonly class TextBlockBuilder
{
    private const float PT_TO_MM = 25.4 / 72;

    /** Tolerancia (pt) para considerar alineados dos bordes o centros. */
    private const float ALIGN_TOLERANCE = 3.0;

    /** Holgura horizontal: la fuente sustituta puede ser más ancha que la original. */
    private const float WIDTH_SLACK = 0.15;

    public function __construct(
        private FontMatcher $fonts,
    ) {}

    /**
     * @param  list<TextLine>  $lines
     * @return list<TextBox>
     */
    public function build(array $lines, float $pageWidthPt, float $pageHeightPt, string $idPrefix = 'txt'): array
    {
        $lines = array_values(array_filter($lines, fn (TextLine $l): bool => trim($l->text) !== ''));
        usort($lines, fn (TextLine $a, TextLine $b): int => [$a->yMin, $a->xMin] <=> [$b->yMin, $b->xMin]);

        $boxes = [];
        foreach ($this->group($lines) as $i => $block) {
            $boxes[] = $this->box($block, $pageWidthPt, $pageHeightPt, "{$idPrefix}-".($i + 1));
        }

        return $boxes;
    }

    /**
     * @param  list<TextLine>  $lines  Ordenadas de arriba abajo.
     * @return list<non-empty-list<TextLine>>
     */
    private function group(array $lines): array
    {
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            if ($current !== [] && $this->continues($current, $line)) {
                $current[] = $line;

                continue;
            }

            if ($current !== []) {
                $blocks[] = $current;
            }
            $current = [$line];
        }

        if ($current !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * ¿La línea sigue el párrafo? Mismo cuerpo y estilo, solapada en horizontal
     * y con el mismo espaciado que las anteriores.
     *
     * @param  non-empty-list<TextLine>  $block
     */
    private function continues(array $block, TextLine $line): bool
    {
        $last = $block[count($block) - 1];
        $size = $last->size();
        $gap = $line->yMin - $last->yMin;

        if (abs($line->size() - $size) > 0.5 || $line->isBold() !== $last->isBold() || $line->family !== $last->family) {
            return false;
        }

        if ($gap <= 0 || $gap > 2.6 * $size) {
            return false;
        }

        $blockMin = min(array_map(fn (TextLine $l): float => $l->xMin, $block));
        $blockMax = max(array_map(fn (TextLine $l): float => $l->xMax, $block));
        if ($line->xMax < $blockMin || $line->xMin > $blockMax) {
            return false;
        }

        if (count($block) >= 2) {
            $pitch = $last->yMin - $block[count($block) - 2]->yMin;

            return abs($gap - $pitch) <= 0.35 * $size;
        }

        return true;
    }

    /**
     * @param  non-empty-list<TextLine>  $block
     */
    private function box(array $block, float $pageWidthPt, float $pageHeightPt, string $id): TextBox
    {
        $first = $block[0];
        $size = $first->size();
        $count = count($block);
        $pitch = $count > 1 ? ($block[$count - 1]->yMin - $first->yMin) / ($count - 1) : 1.2 * $size;
        $lineHeight = max(0.8, min(4.0, $pitch / $size));
        $align = $this->alignment($block, $pageWidthPt);

        $left = min(array_map(fn (TextLine $l): float => $l->xMin, $block));
        $right = max(array_map(fn (TextLine $l): float => $l->xMax, $block));
        $slack = ($right - $left) * self::WIDTH_SLACK;
        [$left, $right] = match ($align) {
            TextAlign::Center => [$left - $slack / 2, $right + $slack / 2],
            TextAlign::Right => [$left - $slack, $right],
            default => [$left, $right + $slack],
        };
        $left = max(0.0, $left);
        $right = min($pageWidthPt, $right);

        // La primera línea de un cuadro con interlineado L ocupa L × cuerpo; el
        // texto queda centrado en esa franja.
        $glyphHeight = $first->yMax - $first->yMin;
        $top = max(0.0, $first->yMin - ($lineHeight * $size - $glyphHeight) / 2);
        $height = min($pageHeightPt - $top, $count * $lineHeight * $size + 0.2 * $size);

        $match = $this->fonts->match($first->family);
        $color = $this->dominantColor($block);

        return new TextBox(
            id: $id,
            box: new Box(
                round($left * self::PT_TO_MM, 2),
                round($top * self::PT_TO_MM, 2),
                round(($right - $left) * self::PT_TO_MM, 2),
                round(max($height, $size) * self::PT_TO_MM, 2),
            ),
            spans: $this->spans($block, $color),
            font: $match->family,
            sizePt: round($size, 2),
            lineHeight: round($lineHeight, 2),
            color: new Color($color),
            align: $align,
            verticalAlign: VerticalAlign::Top,
        );
    }

    /**
     * @param  non-empty-list<TextLine>  $block
     */
    private function alignment(array $block, float $pageWidthPt): TextAlign
    {
        $lefts = self::spread(array_map(fn (TextLine $l): float => $l->xMin, $block));
        $rights = self::spread(array_map(fn (TextLine $l): float => $l->xMax, $block));
        $centers = self::spread(array_map(fn (TextLine $l): float => $l->center(), $block));
        $t = self::ALIGN_TOLERANCE;

        if (count($block) === 1) {
            return abs($block[0]->center() - $pageWidthPt / 2) < 0.03 * $pageWidthPt ? TextAlign::Center : TextAlign::Left;
        }

        // Justificado: bordes izquierdos alineados y derechos también, salvo la última línea.
        if ($lefts <= $t && count($block) >= 3) {
            $rightsButLast = array_map(fn (TextLine $l): float => $l->xMax, $block);
            array_pop($rightsButLast);

            if (self::spread($rightsButLast) <= $t) {
                return TextAlign::Justify;
            }
        }

        // Párrafo de libro: primera línea con sangría y el resto a la izquierda.
        // Sin esto, un párrafo corto de dos líneas que acaban en el margen
        // derecho parecería alineado a la derecha.
        $leftsButFirst = array_map(fn (TextLine $l): float => $l->xMin, array_slice($block, 1));
        if ($leftsButFirst !== [] && self::spread($leftsButFirst) <= $t) {
            $indent = $block[0]->xMin - min($leftsButFirst);
            $rightsButLast = array_map(fn (TextLine $l): float => $l->xMax, array_slice($block, 0, -1));

            if ($indent > $t && $indent < 0.2 * ($block[0]->xMax - $block[0]->xMin) && $rightsButLast !== []) {
                return self::spread($rightsButLast) <= $t ? TextAlign::Justify : TextAlign::Left;
            }
        }

        return match (true) {
            $centers <= $t && $lefts > $t => TextAlign::Center,
            $rights <= $t && $lefts > $t => TextAlign::Right,
            default => TextAlign::Left,
        };
    }

    /**
     * @param  non-empty-list<float>  $values
     */
    private static function spread(array $values): float
    {
        return max($values) - min($values);
    }

    /**
     * @param  non-empty-list<TextLine>  $block
     * @return list<TextSpan>
     */
    private function spans(array $block, string $boxColor): array
    {
        $spans = [];
        foreach ($block as $i => $line) {
            $runs = $line->styledRuns();
            foreach ($runs as $r => $run) {
                $text = $r === count($runs) - 1 ? rtrim($run->text) : $run->text;
                if ($i < count($block) - 1 && $r === count($runs) - 1) {
                    $text .= "\n";
                }

                $color = $run->color !== null && strtolower($run->color) !== $boxColor ? strtolower($run->color) : null;
                $last = array_pop($spans);

                if ($last !== null && $last->bold === $run->bold && $last->italic === $run->italic && $last->color?->hex === $color) {
                    $spans[] = new TextSpan($last->text.$text, $last->bold, $last->italic, $last->color);
                } else {
                    if ($last !== null) {
                        $spans[] = $last;
                    }

                    $spans[] = new TextSpan($text, $run->bold, $run->italic, $color === null ? null : new Color($color));
                }
            }
        }

        return $spans;
    }

    /**
     * @param  non-empty-list<TextLine>  $block
     */
    private function dominantColor(array $block): string
    {
        $counts = [];
        foreach ($block as $line) {
            foreach ($line->styledRuns() as $run) {
                $color = strtolower($run->color ?? '#000000');
                $counts[$color] = ($counts[$color] ?? 0) + mb_strlen($run->text);
            }
        }
        arsort($counts);

        $color = (string) array_key_first($counts);

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#000000';
    }
}
