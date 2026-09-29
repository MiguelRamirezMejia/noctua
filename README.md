# Noctua

Composición e impresión de libros cosidos a mano por cuadernillos: del manuscrito al PDF impuesto en hojas carta 2-up.

## Requisitos (Windows, nativo)

| Herramienta | Versión | Instalación                                                                                                       |
| ----------- | ------- | ----------------------------------------------------------------------------------------------------------------- |
| PHP         | 8.4     | `winget install PHP.PHP.8.4` (extensiones: curl, fileinfo, gd, intl, mbstring, openssl, pdo_sqlite, sqlite3, zip) |
| Composer    | 2.x     |                                                                                                                   |
| Node        | 22      |                                                                                                                   |
| Typst       | 0.15.1  | `winget install Typst.Typst`                                                                                      |
| Poppler     | 25.x    | `winget install oschwartz10612.Poppler` (tests de PDF y regresión visual)                                         |

En local la cola usa el driver `database`; Horizon y Redis quedan para producción.

```bash
composer setup
composer dev
```

## Libros desde un PDF ya maquetado

En **Libros** se sube el PDF (Canva, Word, InDesign…) y se edita sin tocar el original: reordenar (arrastrando o con botones), quitar y devolver páginas, duplicar, insertar blancos y numerar páginas (desde qué página, con qué número, posición, tipo de letra, tamaño y margen; se puede quitar el número de páginas concretas). Al imprimir, Noctua compone el libro con Typst (folios) y FPDI y lo impone por cuadernillos. Las páginas más grandes que media carta se ajustan sin recortes.

En Windows, fija en el `.env` las rutas absolutas de `NOCTUA_TYPST` y `NOCTUA_PDFTOPPM`.

## Imposición desde la consola (fase 1)

```bash
# PDF de prueba con páginas numeradas en media carta
php artisan noctua:test-document prueba.pdf --pages=16

# Imposición 2-up por cuadernillos de 4 hojas
php artisan noctua:impose prueba.pdf --sheets=4

# Hoja de calibración de la impresora
php artisan noctua:calibration calibracion.pdf --back-offset=0.5,-0.3
```

Opciones de `noctua:impose`: `--sheets` (1–8), `--short-last`, `--creep` (mm por hoja), `--front-offset` / `--back-offset` (X,Y en mm), `--unprintable` (mm), `--no-fold-marks`.

Imprime siempre a **tamaño real** y en dúplex con **volteo por el borde corto**.

## Calidad

```bash
composer test        # Pint, PHPStan (nivel max) y Pest
npm run check        # lint y formato del frontend
npm run types:check  # vue-tsc
```

## Arquitectura

- `app/Domain/Imposition`: orden de páginas por hoja y cuadernillo. PHP puro, sin dependencias.
- `app/Domain/Printing`: geometría de la hoja, perfil de impresora y generación del PDF con FPDI/FPDF (salida determinista).
- `docs/spike-0.md`: hallazgos del spike de Typst y FPDI.
