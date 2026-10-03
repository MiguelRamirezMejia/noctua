import type { FontFamilyInfo } from '@/types/fonts';

const loaded = new Set<string>();

/**
 * Registra en el navegador las variantes de las familias indicadas, con el
 * mismo nombre que usa Typst, para que la vista previa use las mismas fuentes.
 */
export function loadFontFaces(families: FontFamilyInfo[]): void {
    for (const family of families) {
        for (const face of family.faces) {
            if (loaded.has(face.url)) {
                continue;
            }

            loaded.add(face.url);
            const fontFace = new FontFace(family.family, `url(${face.url})`, {
                weight: String(face.weight),
                style: face.italic ? 'italic' : 'normal',
            });
            void fontFace
                .load()
                .then((f) => document.fonts.add(f))
                .catch(() => loaded.delete(face.url));
        }
    }
}
