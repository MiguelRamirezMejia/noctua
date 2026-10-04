import type { BookLayout } from '@/types/book';

export type ContentFrame = { scale: number; x: number; y: number };

export const IDENTITY_FRAME: ContentFrame = { scale: 1, x: 0, y: 0 };

/**
 * Dónde va el contenido de una página con márgenes en espejo, en mm de la
 * página de origen. Mismo cálculo que App\Domain\Book\BookLayout::frame().
 *
 * @param printScale Escala con la que la página llega al papel (los márgenes son impresos).
 * @param position Posición en el libro: las impares van a la derecha (lomo a la izquierda).
 */
export function contentFrame(
    layout: BookLayout,
    widthMm: number,
    heightMm: number,
    printScale: number,
    position: number,
): ContentFrame {
    const top = layout.margin_top_mm / printScale;
    const bottom = layout.margin_bottom_mm / printScale;
    const inside = layout.margin_inside_mm / printScale;
    const outside = layout.margin_outside_mm / printScale;

    if (top + bottom + inside + outside <= 0) {
        return IDENTITY_FRAME;
    }

    const availableWidth = Math.max(1, widthMm - inside - outside);
    const availableHeight = Math.max(1, heightMm - top - bottom);
    const scale = Math.min(
        1,
        availableWidth / widthMm,
        availableHeight / heightMm,
    );
    const left = position % 2 === 1 ? inside : outside;

    return {
        scale,
        x: left + (availableWidth - widthMm * scale) / 2,
        y: top + (availableHeight - heightMm * scale) / 2,
    };
}
