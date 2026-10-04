<?php

namespace App\Domain\Book\Design;

/**
 * Lo que Noctua añade a una página: un fondo y elementos encima.
 */
final readonly class PageDesign
{
    public const int MAX_ELEMENTS = 200;

    /**
     * @param  list<Element>  $elements  En orden de apilado (el último queda encima).
     */
    public function __construct(
        public ?PageBackground $background = null,
        public array $elements = [],
        /** Usar la página de origen sin su texto (el texto pasa a cuadros editables). */
        public bool $hideSourceText = false,
    ) {
        if (count($elements) > self::MAX_ELEMENTS) {
            throw new InvalidPageDesign('Demasiados elementos en una página (máximo '.self::MAX_ELEMENTS.').');
        }

        $ids = [];
        foreach ($elements as $element) {
            if ($element->id() === '' || isset($ids[$element->id()])) {
                throw new InvalidPageDesign("Identificador de elemento vacío o repetido: «{$element->id()}».");
            }
            $ids[$element->id()] = true;
        }
    }

    public function isEmpty(): bool
    {
        return $this->background === null && $this->elements === [] && ! $this->hideSourceText;
    }

    /**
     * @return list<TextBox>
     */
    public function textBoxes(): array
    {
        return array_values(array_filter($this->elements, fn (Element $e): bool => $e instanceof TextBox));
    }

    /**
     * @return array{background: array{color: string, mode: string}|null, elements: list<array<string, mixed>>, hide_source_text: bool}
     */
    public function toArray(): array
    {
        return [
            'background' => $this->background === null ? null : [
                'color' => $this->background->color->hex,
                'mode' => $this->background->mode->value,
            ],
            'elements' => array_map(fn (Element $e): array => $e->toArray(), $this->elements),
            'hide_source_text' => $this->hideSourceText,
        ];
    }

    /**
     * Construye el diseño a partir de datos guardados o recibidos del editor.
     */
    public static function fromArray(mixed $data): self
    {
        if ($data === null) {
            return new self;
        }

        if (! is_array($data)) {
            throw new InvalidPageDesign('Diseño de página no válido.');
        }

        $background = null;
        if (is_array($data['background'] ?? null)) {
            $background = new PageBackground(
                new Color(self::string($data['background'], 'color', '#ffffff')),
                BackgroundMode::tryFrom(self::string($data['background'], 'mode', 'behind'))
                    ?? throw new InvalidPageDesign('Modo de fondo desconocido.'),
            );
        }

        $elements = [];
        foreach (is_array($data['elements'] ?? null) ? $data['elements'] : [] as $element) {
            if (! is_array($element)) {
                throw new InvalidPageDesign('Elemento no válido.');
            }

            $elements[] = self::element($element);
        }

        return new self($background, $elements, ($data['hide_source_text'] ?? false) === true);
    }

    /**
     * @param  array<mixed>  $e
     */
    private static function element(array $e): Element
    {
        $id = self::string($e, 'id', '');
        $box = new Box(self::float($e, 'x'), self::float($e, 'y'), self::float($e, 'width'), self::float($e, 'height'));

        return match ($e['type'] ?? null) {
            'text' => new TextBox(
                id: $id,
                box: $box,
                spans: self::spans($e['spans'] ?? []),
                font: self::string($e, 'font', 'Libertinus Serif'),
                sizePt: self::float($e, 'size_pt', 12.0),
                lineHeight: self::float($e, 'line_height', 1.3),
                color: new Color(self::string($e, 'color', '#000000')),
                align: TextAlign::tryFrom(self::string($e, 'align', 'left')) ?? throw new InvalidPageDesign('Alineación desconocida.'),
                verticalAlign: VerticalAlign::tryFrom(self::string($e, 'valign', 'top')) ?? throw new InvalidPageDesign('Alineación vertical desconocida.'),
                fill: is_string($e['fill'] ?? null) ? new Color($e['fill']) : null,
            ),
            'rect' => new RectangleShape(
                id: $id,
                box: $box,
                fill: new Color(self::string($e, 'fill', '#ffffff')),
                opacity: self::float($e, 'opacity', 1.0),
            ),
            default => throw new InvalidPageDesign('Tipo de elemento desconocido.'),
        };
    }

    /**
     * @return list<TextSpan>
     */
    private static function spans(mixed $spans): array
    {
        if (! is_array($spans)) {
            throw new InvalidPageDesign('Texto no válido.');
        }

        $result = [];
        foreach ($spans as $span) {
            if (! is_array($span) || ! is_string($span['text'] ?? null)) {
                throw new InvalidPageDesign('Fragmento de texto no válido.');
            }

            $result[] = new TextSpan(
                text: $span['text'],
                bold: ($span['bold'] ?? false) === true,
                italic: ($span['italic'] ?? false) === true,
                color: is_string($span['color'] ?? null) ? new Color($span['color']) : null,
            );
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function string(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : throw new InvalidPageDesign("Valor no válido en «{$key}».");
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function float(array $data, string $key, ?float $default = null): float
    {
        $value = $data[$key] ?? $default;

        return is_int($value) || is_float($value) ? (float) $value : throw new InvalidPageDesign("Valor numérico no válido en «{$key}».");
    }
}
