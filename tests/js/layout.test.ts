import { describe, expect, it } from 'vite-plus/test';
import { contentFrame, IDENTITY_FRAME } from '@/lib/layout';

const margins = (
    top: number,
    bottom: number,
    inside: number,
    outside: number,
) => ({
    margin_top_mm: top,
    margin_bottom_mm: bottom,
    margin_inside_mm: inside,
    margin_outside_mm: outside,
});

describe('contentFrame', () => {
    it('no transforma sin márgenes', () => {
        expect(contentFrame(margins(0, 0, 0, 0), 140, 200, 1, 1)).toEqual(
            IDENTITY_FRAME,
        );
    });

    it('coincide con el cálculo del servidor (márgenes en espejo)', () => {
        const layout = margins(10, 10, 20, 10);
        const recto = contentFrame(layout, 140, 200, 1, 1);
        const verso = contentFrame(layout, 140, 200, 1, 2);

        expect(recto.scale).toBeCloseTo(110 / 140);
        expect(recto.x).toBeCloseTo(20);
        expect(verso.x).toBeCloseTo(10);
        expect(recto.y).toBeCloseTo(10 + (180 - 200 * (110 / 140)) / 2);
    });

    it('expresa los márgenes tal como quedan impresos', () => {
        const frame = contentFrame(margins(10, 10, 10, 10), 200, 200, 0.5, 1);

        expect(frame.scale).toBeCloseTo(160 / 200);
        expect(frame.x).toBeCloseTo(20);
    });
});
