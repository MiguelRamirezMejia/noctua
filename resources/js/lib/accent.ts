/**
 * Variables CSS del tema a partir del color de acento. Misma lógica que
 * App\Support\AccentPalette (servidor), para aplicar el cambio al instante.
 */
const VARIABLES = [
    '--primary',
    '--primary-foreground',
    '--ring',
    '--sidebar-primary',
    '--sidebar-primary-foreground',
    '--sidebar-ring',
    '--accent',
    '--sidebar-accent',
] as const;

/** Texto blanco o casi negro según la luminancia relativa (WCAG) del fondo. */
export function readableOn(hex: string): string {
    const channel = (part: string) => {
        const c = parseInt(part, 16) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const luminance =
        0.2126 * channel(hex.slice(1, 3)) +
        0.7152 * channel(hex.slice(3, 5)) +
        0.0722 * channel(hex.slice(5, 7));

    return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05
        ? '#ffffff'
        : '#111111';
}

export function accentVariables(
    color: string,
): Record<(typeof VARIABLES)[number], string> {
    const hex = color.toLowerCase();
    const foreground = readableOn(hex);

    return {
        '--primary': hex,
        '--primary-foreground': foreground,
        '--ring': hex,
        '--sidebar-primary': hex,
        '--sidebar-primary-foreground': foreground,
        '--sidebar-ring': hex,
        '--accent': `color-mix(in oklab, ${hex} 14%, var(--background))`,
        '--sidebar-accent': `color-mix(in oklab, ${hex} 16%, var(--sidebar))`,
    };
}

/** Aplica el color al documento; null vuelve al color de Noctua. */
export function applyAccent(color: string | null): void {
    if (typeof document === 'undefined') {
        return;
    }

    const style = document.documentElement.style;

    if (color === null || !/^#[0-9a-f]{6}$/i.test(color)) {
        VARIABLES.forEach((name) => style.removeProperty(name));

        return;
    }

    for (const [name, value] of Object.entries(accentVariables(color))) {
        style.setProperty(name, value);
    }
}
