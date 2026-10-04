<?php

namespace App\Domain\Book\Extraction;

/**
 * Elige entre las fuentes disponibles la que corresponde a una fuente del PDF:
 * la misma si está (por ejemplo, porque el usuario la subió) o la más parecida.
 */
final readonly class FontMatcher
{
    private const array SERIF = ['times', 'georgia', 'garamond', 'baskerville', 'book', 'serif', 'minion', 'palatino', 'cambria', 'caslon', 'merriweather', 'lora', 'roman', 'bodoni', 'didot', 'charter', 'crimson'];

    private const array HAND = ['comic', 'chalk', 'marker', 'hand', 'script', 'brush', 'kid', 'school'];

    /**
     * @param  list<string>  $available  Familias disponibles.
     */
    public function __construct(
        private array $available,
    ) {}

    public function match(?string $pdfFamily): FontMatch
    {
        $base = self::baseName($pdfFamily ?? '');
        $key = self::key($base);

        foreach ($this->available as $family) {
            if ($key !== '' && self::key($family) === $key) {
                return new FontMatch($family, true);
            }
        }

        $lower = strtolower($base);
        $candidates = match (true) {
            $this->contains($lower, self::HAND) => ['Andika', 'Patrick Hand', 'Nunito'],
            $this->contains($lower, self::SERIF) => ['Libertinus Serif', 'EB Garamond', 'Lora'],
            default => ['Open Sans', 'Nunito', 'Lato', 'Source Sans 3'],
        };

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $this->available, true)) {
                return new FontMatch($candidate, false);
            }
        }

        return new FontMatch($this->available[0] ?? 'Libertinus Serif', false);
    }

    /**
     * «ABCDEF+Verdana-BoldItalic» → «Verdana».
     */
    public static function baseName(string $family): string
    {
        $name = preg_replace('/^[A-Z]{6}\+/', '', trim($family)) ?? $family;
        $name = preg_split('/[-,]/', $name)[0] ?? $name;
        $name = preg_replace('/(Bold|Italic|Oblique|Regular|Medium|Light|Black|Semibold|MT|PS)+$/i', '', $name) ?? $name;

        return trim((string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $name));
    }

    private static function key(string $family): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $family));
    }

    /**
     * @param  list<string>  $words
     */
    private function contains(string $haystack, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }
}
