import { preview } from '@/routes/borders';
import type { PageBorder } from '@/types/book';

export const DEFAULT_BORDER: PageBorder = {
    style: 'linea',
    color: '#5b3a1e',
    width_pt: 0.8,
    inset_mm: 8,
    unit_mm: 6,
};

/**
 * URL del SVG del marco para una página: lo dibuja el servidor con la misma
 * plantilla de Typst que al imprimir, así la vista previa es exacta.
 */
export function borderPreviewUrl(
    border: PageBorder,
    pageWidthMm: number,
    pageHeightMm: number,
    printScale: number,
): string {
    return preview.url({
        query: {
            style: border.style,
            color: border.color,
            width_pt: border.width_pt,
            inset_mm: border.inset_mm,
            unit_mm: border.unit_mm,
            page_width_mm: Math.round(pageWidthMm * 100) / 100,
            page_height_mm: Math.round(pageHeightMm * 100) / 100,
            print_scale: Math.round(printScale * 10000) / 10000,
        },
    });
}
