# Spike Fase 0 — Typst + FPDI

Fecha: 2026-09-27 · Entorno: Windows 11 nativo, PHP 8.4.25, Typst 0.15.1, FPDI 2.6.8 + FPDF 1.9, Poppler 25.07.

Código en `spike/` (desechable; el código definitivo va en `app/Domain`).

## Resultados

| Medida | Resultado |
|---|---|
| Libro completo, 326 páginas media carta, PDF | **0,86–0,95 s** |
| Capítulo aislado (15 págs.), PDF | ~190 ms (la mayor parte es arranque del proceso y carga de fuentes) |
| Capítulo aislado, SVG por página | ~240 ms (15 archivos) |
| Capítulo aislado, PNG 96 ppi | ~290 ms |
| Posiciones de bloques (`typst eval` + `query`) | ~190 ms capítulo · ~830 ms libro |
| Capítulo aislado vs. mismas páginas en el libro | **317 páginas, 0 píxeles distintos** (tras reiniciar notas por capítulo) |
| Determinismo Typst (`--creation-timestamp 0`) | hash idéntico entre ejecuciones |
| Determinismo FPDF | idéntico tras sobrescribir `_putinfo()` |
| Imposición 2-up, libro de 326 págs. (21 cuadernillos × 4 hojas) | 0,55 s, 168 caras carta |
| Precisión de colocación FPDI (render 600 dpi) | error ≤ 0,03 mm (< 1 px) |

## Hallazgos

1. **La compilación incremental por capítulo es exacta** si los capítulos abren en impar y el contador de notas se reinicia por capítulo. Con numeración continua de notas, el capítulo dependería de los anteriores → habría que pasar `footnote-start` como input (igual que `start` de página).
2. **Typst es mucho más rápido de lo previsto**: 326 páginas en < 1 s. El objetivo de preview < 1 s se cumple con margen compilando solo el capítulo tocado; la compilación completa queda para el PDF final y el índice.
3. **Sincronización editor↔preview viable sin sidecar**: anclas `metadata` con `here().position()` + `typst eval 'query(<blk>)…'` devuelven `{id, page, y}`. Se ejecuta en paralelo a la compilación. Descarto el sidecar Rust por ahora.
4. **`typst query` está obsoleto en 0.15** → usar `typst eval '<expr>' --in archivo.typ`.
5. **Guionado español correcto** en la muestra (`enton-ces`, `incompren-sibles`, `ortoti-pográfica`); falta revisión con texto real y casos límite (prefijos, `rr`, palabras con guion).
6. **Páginas en blanco de apertura** llevaban cornisa y folio: corregido con marca `<chapter-end>`.
7. **FPDF** fuerza `CreationDate = time()` en `_enddoc()`: se sobrescribe `_putinfo()` con fecha UTC fija. FPDF no escribe `/ID`, así que no hay más fuentes de aleatoriedad. Confirma la elección FPDF sobre TCPDF.
8. **Plan de imposición** verificado: n=4 → hoja 1 `16|1` / `2|15` … hoja 4 `10|7` / `8|9`; n=1 → `4|1` / `2|3`.

## Trampas de entorno (Windows)

- Git Bash reescribe argumentos tipo `/data/x.json` como rutas Windows → `MSYS_NO_PATHCONV=1`. No afecta a `Process` de Laravel.
- winget instaló PHP ZTS (el manifiesto NTS daba 404). Irrelevante para CLI.
- Poppler en Windows avisa `No display font for 'Symbol'` al rasterizar Helvetica y `Suspects object is wrong type` en PDFs de Typst: ruido de Poppler, no afecta al PDF. Para regresión visual en CI (Linux) conviene instalar fuentes base.

## Pendiente de validar por ti (impresión física)

- `spike/out/fpdi/calibracion.pdf`: imprimir a **tamaño real**, medir la regla de 100 mm, contar las marcas de borde visibles (zona no imprimible) y comprobar que "DORSO" sale derecho con volteo por borde corto.
- `spike/out/fpdi/book-imposed.pdf` (primeras 8 caras = cuadernillo 1): imprimir, doblar y verificar el orden 1–16.

## No validado aún

- Viudas/huérfanas: los costes están activos (`text.costs`) pero no se revisó visualmente.
- Fuentes subidas por el usuario (métricas, `smcp`).
- Imágenes y tablas.
