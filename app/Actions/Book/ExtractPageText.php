<?php

namespace App\Actions\Book;

use App\Domain\Book\Extraction\FontMatcher;
use App\Domain\Book\Extraction\TextLine;
use App\Domain\Book\Extraction\TextRun;
use App\Models\Book;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Extrae el texto de una página con su posición y estilo combinando dos
 * herramientas de Poppler: pdftotext da posiciones fiables por línea;
 * pdftohtml da cuerpo, familia, color, negrita y cursiva.
 */
final readonly class ExtractPageText
{
    public function __construct(
        private string $pdftotext = 'pdftotext',
        private string $pdftohtml = 'pdftohtml',
        private int $timeoutSeconds = 120,
    ) {}

    /**
     * @return array{lines: list<TextLine>, width: float, height: float}
     */
    public function handle(Book $book, int $page): array
    {
        [$lines, $width, $height] = $this->positions($book->sourcePath(), $page);

        return [
            'lines' => $this->applyStyles($lines, $this->styles($book->sourcePath(), $page)),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{list<TextLine>, float, float}
     */
    private function positions(string $pdf, int $page): array
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($this->run([$this->pdftotext, '-bbox-layout', '-f', (string) $page, '-l', (string) $page, $pdf, '-']))) {
            throw new RuntimeException('No se pudo leer el texto de la página.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        $pageNode = $this->elements($xpath, '//h:page')[0] ?? null;
        $width = $pageNode !== null ? (float) $pageNode->getAttribute('width') : 612.0;
        $height = $pageNode !== null ? (float) $pageNode->getAttribute('height') : 792.0;

        $lines = [];
        foreach ($this->elements($xpath, '//h:line') as $line) {
            $words = array_map(fn (DOMElement $w): string => $w->textContent, $this->elements($xpath, 'h:word', $line));

            $lines[] = new TextLine(
                implode(' ', $words),
                (float) $line->getAttribute('xMin'),
                (float) $line->getAttribute('yMin'),
                (float) $line->getAttribute('xMax'),
                (float) $line->getAttribute('yMax'),
            );
        }

        return [$lines, $width, $height];
    }

    /**
     * Fragmentos con estilo en orden de lectura.
     *
     * @return list<array{text: string, size: float, family: string, run: TextRun}>
     */
    private function styles(string $pdf, int $page): array
    {
        $dom = new DOMDocument;
        // El XML de pdftohtml declara un DTD externo que no hace falta cargar.
        // -hidden incluye el texto invisible: la capa de OCR de los escaneos.
        if (! @$dom->loadXML($this->run([$this->pdftohtml, '-xml', '-hidden', '-zoom', '1', '-i', '-q', '-stdout', '-f', (string) $page, '-l', (string) $page, $pdf]), LIBXML_NONET)) {
            return [];
        }

        $fonts = [];
        foreach ($dom->getElementsByTagName('fontspec') as $spec) {
            $fonts[$spec->getAttribute('id')] = [
                'size' => (float) $spec->getAttribute('size'),
                'raw' => $spec->getAttribute('family'),
                'color' => strtolower($spec->getAttribute('color')),
            ];
        }

        $runs = [];
        foreach ($dom->getElementsByTagName('text') as $text) {
            $font = $fonts[$text->getAttribute('font')] ?? ['size' => 0.0, 'raw' => '', 'color' => '#000000'];
            $bold = $text->getElementsByTagName('b')->length > 0 || stripos($font['raw'], 'bold') !== false;
            $italic = $text->getElementsByTagName('i')->length > 0 || preg_match('/italic|oblique/i', $font['raw']) === 1;
            $color = preg_match('/^#[0-9a-f]{6}$/', $font['color']) === 1 ? $font['color'] : null;

            $runs[] = [
                'text' => $text->textContent,
                'size' => $font['size'],
                'family' => FontMatcher::baseName($font['raw']),
                'run' => new TextRun($text->textContent, $bold, $italic, $color),
            ];
        }

        return $runs;
    }

    /**
     * Asigna a cada línea los fragmentos con estilo cuyo texto la forma,
     * consumiéndolos en orden.
     *
     * @param  list<TextLine>  $lines
     * @param  list<array{text: string, size: float, family: string, run: TextRun}>  $styles
     * @return list<TextLine>
     */
    private function applyStyles(array $lines, array $styles): array
    {
        $normalize = fn (string $s): string => (string) preg_replace('/\s+/u', '', $s);
        $used = array_fill(0, count($styles), false);
        $result = [];

        foreach ($lines as $line) {
            $target = $normalize($line->text);
            $matched = null;

            // Secuencia de fragmentos no usados cuyo texto unido sea la línea.
            for ($start = 0; $start < count($styles) && $matched === null; $start++) {
                $first = $normalize($styles[$start]['text']);
                if ($used[$start] || $first === '' || ! str_starts_with($target, $first)) {
                    continue;
                }

                $joined = '';
                for ($end = $start; $end < count($styles) && ! $used[$end]; $end++) {
                    $joined .= $normalize($styles[$end]['text']);

                    if ($joined === $target) {
                        $matched = [$start, $end];
                        break;
                    }

                    if (! str_starts_with($target, $joined)) {
                        break;
                    }
                }
            }

            if ($matched === null) {
                $result[] = $line;

                continue;
            }

            [$start, $end] = $matched;
            $runs = [];
            for ($i = $start; $i <= $end; $i++) {
                $used[$i] = true;
                $runs[] = $styles[$i]['run'];
            }

            $result[] = new TextLine(
                $line->text,
                $line->xMin,
                $line->yMin,
                $line->xMax,
                $line->yMax,
                $runs,
                $styles[$start]['size'] > 0 ? $styles[$start]['size'] : null,
                $styles[$start]['family'] !== '' ? $styles[$start]['family'] : null,
            );
        }

        return $result;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $expression, ?DOMElement $context = null): array
    {
        $nodes = $xpath->query($expression, $context);
        $elements = [];

        foreach ($nodes === false ? [] : $nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): string
    {
        $process = new Process($command, timeout: $this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudo extraer el texto: '.trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
