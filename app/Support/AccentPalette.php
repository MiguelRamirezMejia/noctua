<?php

namespace App\Support;

/**
 * Variables CSS del tema a partir del color de acento elegido por el usuario:
 * color principal, su texto (blanco o negro según contraste), anillo de foco y
 * fondos de hover, que se mezclan con el fondo y se adaptan al modo oscuro.
 * La misma lógica está en resources/js/lib/accent.ts.
 */
final class AccentPalette
{
    /** Colores sugeridos en los ajustes de apariencia. */
    public const array PRESETS = [
        '#2563eb' => 'Azul',
        '#4f46e5' => 'Índigo',
        '#7c3aed' => 'Violeta',
        '#db2777' => 'Rosa',
        '#dc2626' => 'Rojo',
        '#c2410c' => 'Terracota',
        '#d97706' => 'Ámbar',
        '#059669' => 'Esmeralda',
        '#0d9488' => 'Verde azulado',
    ];

    public static function isValid(?string $hex): bool
    {
        return $hex !== null && preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
    }

    /**
     * @return array<string, string> variable => valor
     */
    public static function variables(string $hex): array
    {
        $hex = strtolower($hex);
        $foreground = self::readableOn($hex);

        return [
            '--primary' => $hex,
            '--primary-foreground' => $foreground,
            '--ring' => $hex,
            '--sidebar-primary' => $hex,
            '--sidebar-primary-foreground' => $foreground,
            '--sidebar-ring' => $hex,
            '--accent' => "color-mix(in oklab, {$hex} 14%, var(--background))",
            '--sidebar-accent' => "color-mix(in oklab, {$hex} 16%, var(--sidebar))",
        ];
    }

    /**
     * Declaraciones para el atributo style del <html>.
     */
    public static function style(?string $hex): string
    {
        if (! self::isValid($hex)) {
            return '';
        }

        return implode(' ', array_map(
            fn (string $name, string $value): string => "{$name}: {$value};",
            array_keys(self::variables((string) $hex)),
            array_values(self::variables((string) $hex)),
        ));
    }

    /**
     * Texto blanco o casi negro según la luminancia relativa (WCAG) del fondo.
     */
    public static function readableOn(string $hex): string
    {
        $channel = function (string $part): float {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel(substr($hex, 1, 2)) + 0.7152 * $channel(substr($hex, 3, 2)) + 0.0722 * $channel(substr($hex, 5, 2));

        // Contraste con blanco frente a contraste con negro.
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#111111';
    }
}
