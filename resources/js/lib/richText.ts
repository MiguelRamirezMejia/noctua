import type { JSONContent } from '@tiptap/vue-3';
import type { TextSpan } from '@/types/book';

/**
 * Fragmentos del cuadro → documento de TipTap. Cada "\n" abre un párrafo.
 */
export function spansToDoc(spans: TextSpan[]): JSONContent {
    const paragraphs: JSONContent[] = [{ type: 'paragraph', content: [] }];

    for (const span of spans) {
        const parts = span.text.split('\n');

        parts.forEach((part, i) => {
            if (i > 0) {
                paragraphs.push({ type: 'paragraph', content: [] });
            }

            if (part === '') {
                return;
            }

            const marks: JSONContent['marks'] = [];
            if (span.bold) {
                marks.push({ type: 'bold' });
            }
            if (span.italic) {
                marks.push({ type: 'italic' });
            }
            if (span.color) {
                marks.push({ type: 'textStyle', attrs: { color: span.color } });
            }

            paragraphs[paragraphs.length - 1].content!.push({
                type: 'text',
                text: part,
                ...(marks.length > 0 ? { marks } : {}),
            });
        });
    }

    return { type: 'doc', content: paragraphs };
}

/**
 * Documento de TipTap → fragmentos, uniendo los contiguos con el mismo estilo.
 */
export function docToSpans(doc: JSONContent): TextSpan[] {
    const spans: TextSpan[] = [];
    const push = (span: TextSpan) => {
        const last = spans[spans.length - 1];

        if (
            last &&
            last.bold === span.bold &&
            last.italic === span.italic &&
            last.color === span.color
        ) {
            last.text += span.text;
        } else {
            spans.push(span);
        }
    };

    (doc.content ?? []).forEach((paragraph, p) => {
        if (p > 0) {
            // El salto de párrafo se une al fragmento anterior sin cambiar estilos.
            const last = spans[spans.length - 1];
            if (last) {
                last.text += '\n';
            } else {
                spans.push({
                    text: '\n',
                    bold: false,
                    italic: false,
                    color: null,
                });
            }
        }

        for (const node of paragraph.content ?? []) {
            if (node.type === 'hardBreak') {
                push({ text: '\n', bold: false, italic: false, color: null });
                continue;
            }

            if (node.type !== 'text' || !node.text) {
                continue;
            }

            const marks = node.marks ?? [];
            const style = marks.find((m) => m.type === 'textStyle');
            const color = style?.attrs?.color;

            push({
                text: node.text,
                bold: marks.some((m) => m.type === 'bold'),
                italic: marks.some((m) => m.type === 'italic'),
                color:
                    typeof color === 'string' && color !== ''
                        ? toHex(color)
                        : null,
            });
        }
    });

    return spans;
}

/** El navegador puede devolver el color como rgb(); se guarda siempre como #rrggbb. */
export function toHex(color: string): string {
    const match = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);

    if (!match) {
        return color.toLowerCase();
    }

    return (
        '#' +
        match
            .slice(1, 4)
            .map((n) => Number(n).toString(16).padStart(2, '0'))
            .join('')
    );
}

/** Tamaño en píxeles de pantalla de un cuerpo en puntos, a la escala dada (px por mm). */
export const ptToPx = (pt: number, pxPerMm: number) =>
    ((pt * 25.4) / 72) * pxPerMm;

/**
 * Copia profunda de datos del editor. structuredClone() no acepta los proxies
 * reactivos de Vue; estos datos son solo texto, números y booleanos.
 */
export const cloneData = <T>(value: T): T =>
    JSON.parse(JSON.stringify(value)) as T;
