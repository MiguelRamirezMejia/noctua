<?php

namespace App\Http;

use App\Domain\Printing\PageFit;

/**
 * Últimas opciones de impresión recordadas en la sesión, con tipos correctos
 * aunque se hubieran guardado como texto.
 */
final class PrintDefaults
{
    public const string SESSION_KEY = 'imposition.defaults';

    /**
     * @return array{sheets_per_signature: int, short_last_signature: bool, creep_mm: float, front_offset_x: float, front_offset_y: float, back_offset_x: float, back_offset_y: float, unprintable_margin_mm: float, fold_marks: bool, page_fit: string}
     */
    public static function from(mixed $stored, PageFit $defaultFit = PageFit::Actual): array
    {
        $s = is_array($stored) ? $stored : [];
        $int = fn (string $k, int $d): int => is_numeric($s[$k] ?? null) ? (int) $s[$k] : $d;
        $float = fn (string $k, float $d): float => is_numeric($s[$k] ?? null) ? (float) $s[$k] : $d;
        $bool = fn (string $k, bool $d): bool => array_key_exists($k, $s) ? filter_var($s[$k], FILTER_VALIDATE_BOOLEAN) : $d;
        $fit = PageFit::tryFrom(is_string($s['page_fit'] ?? null) ? $s['page_fit'] : '') ?? $defaultFit;

        return [
            'sheets_per_signature' => max(1, min(8, $int('sheets_per_signature', 4))),
            'short_last_signature' => $bool('short_last_signature', true),
            'creep_mm' => $float('creep_mm', 0.0),
            'front_offset_x' => $float('front_offset_x', 0.0),
            'front_offset_y' => $float('front_offset_y', 0.0),
            'back_offset_x' => $float('back_offset_x', 0.0),
            'back_offset_y' => $float('back_offset_y', 0.0),
            'unprintable_margin_mm' => $float('unprintable_margin_mm', 5.0),
            'fold_marks' => $bool('fold_marks', true),
            'page_fit' => $fit->value,
        ];
    }
}
