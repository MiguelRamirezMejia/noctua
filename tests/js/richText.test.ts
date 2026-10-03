import { describe, expect, it } from 'vite-plus/test';
import { docToSpans, ptToPx, spansToDoc, toHex } from '@/lib/richText';
import type { TextSpan } from '@/types/book';

const span = (text: string, extra: Partial<TextSpan> = {}): TextSpan => ({
    text,
    bold: false,
    italic: false,
    color: null,
    ...extra,
});

describe('richText', () => {
    it('convierte fragmentos en párrafos de TipTap y vuelve sin perder estilos', () => {
        const spans = [
            span('Había una vez un '),
            span('dragón', { bold: true, color: '#e11d48' }),
            span(' muy rápido.\nFin.', { italic: true }),
        ];

        const doc = spansToDoc(spans);

        expect(doc.content).toHaveLength(2);
        expect(doc.content?.[0].content?.[1]).toEqual({
            type: 'text',
            text: 'dragón',
            marks: [
                { type: 'bold' },
                { type: 'textStyle', attrs: { color: '#e11d48' } },
            ],
        });
        expect(docToSpans(doc)).toEqual(spans);
    });

    it('une fragmentos contiguos con el mismo estilo', () => {
        const doc = {
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        { type: 'text', text: 'Ho' },
                        { type: 'text', text: 'la' },
                    ],
                },
            ],
        };

        expect(docToSpans(doc)).toEqual([span('Hola')]);
    });

    it('conserva líneas vacías', () => {
        const spans = [span('Uno\n\nDos')];

        expect(docToSpans(spansToDoc(spans))).toEqual(spans);
    });

    it('normaliza colores rgb() a #rrggbb', () => {
        expect(toHex('rgb(225, 29, 72)')).toBe('#e11d48');
        expect(toHex('#ABCDEF')).toBe('#abcdef');
    });

    it('convierte puntos tipográficos a píxeles según la escala', () => {
        expect(ptToPx(72, 1)).toBeCloseTo(25.4);
    });
});
