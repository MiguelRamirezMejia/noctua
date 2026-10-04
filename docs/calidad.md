# Calidad: tests, análisis y convenciones

## 1. Herramientas

| Herramienta                          | Comando                      | Qué comprueba                                                   |
| ------------------------------------ | ---------------------------- | --------------------------------------------------------------- |
| **Pest 5**                           | `php artisan test`           | Tests de PHP (unidad, integración y arquitectura).              |
| **Vitest** (vía vite-plus)           | `npm run test:js`            | Tests de TypeScript (`tests/js`).                               |
| **PHPStan + Larastan, nivel máximo** | `vendor/bin/phpstan analyse` | Tipos en todo `app/`. No se usan `@phpstan-ignore` ni baseline. |
| **Pint**                             | `vendor/bin/pint`            | Formato PHP (preset Laravel).                                   |
| **vite-plus**                        | `npm run check`              | Formato y lint del frontend.                                    |
| **vue-tsc**                          | `npm run types:check`        | Tipos de TypeScript y de las plantillas Vue.                    |

Atajos de Composer:

- `composer test`: limpia la configuración, Pint en modo comprobación, PHPStan y Pest.
- `composer ci:check`: lo anterior más el lint, los tipos y los tests del frontend. Es lo que ejecuta la CI.

## 2. Integración continua (`.github/workflows/tests.yml`)

En cada push a `main` y en cada pull request, en Ubuntu:

1. PHP 8.4 y Node 22.
2. Instala `poppler-utils` y `ghostscript` (apt) y **Typst 0.15.1** (binario oficial).
3. `composer setup`: dependencias, `.env`, clave, migraciones, **catálogo de fuentes** y compilación del frontend.
4. `composer ci:check`.

Las acciones de GitHub están fijadas por hash de commit. `dependabot.yml` propone actualizaciones.

## 3. Organización de los tests

```
tests/
├── TestCase.php             Base: disco falso para TODOS los tests
├── Pest.php                 Configuración de Pest (RefreshDatabase en Feature)
├── Unit/
│   ├── ArchTest.php         Reglas de arquitectura (dominio sin Laravel)
│   └── Domain/              Dominio puro, sin base de datos ni binarios
├── Feature/                 Integración: HTTP, base de datos, binarios reales
└── js/                      Vitest
```

### Reglas importantes

- **Ningún test toca archivos reales.** `TestCase::setUp()` hace `Storage::fake('local')` y `StorageIsolationTest` lo verifica. Un test que crea un libro con el mismo id que uno real borraría sus archivos al eliminarse: ya pasó una vez y por eso existe esta regla.
- **Binarios reales, saltos explícitos.** Los tests que necesitan Typst, Poppler o Ghostscript los usan de verdad (nada de mocks del PDF). Si el binario no está, se saltan con un motivo (`->skip(fn () => ! binaryAvailable('ghostscript'), …)`). En la CI están todos instalados.
- **PDF reales y deterministas.** Los PDF de prueba se generan en el propio test (`TestDocument`, FPDF) y se comprueban con `pdftotext` o rasterizando píxeles concretos.
- Nombres de tests en español, describiendo el comportamiento.

## 4. Qué cubre cada archivo

### Unidad: dominio (`tests/Unit/Domain`)

| Archivo                         | Cubre                                                                                                                                                                                                                                                                                                    |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Imposition/ImposerTest.php`    | Orden de imposición (una hoja = 4\|1 y 2\|3; cuadernillo de cuatro hojas), cada página exactamente una vez, lectura en orden al doblar y anidar (simulación física), relleno hasta múltiplo de 4n, último cuadernillo acortado, numeración de cuadernillos, creep acumulado, especificaciones inválidas. |
| `Printing/SheetLayoutTest.php`  | Media carta a sangre a ambos lados del doblez, páginas pequeñas pegadas al lomo y centradas, blancos omitidos, creep hacia el lomo, offset de cada cara, `Fit` sin recortes en la zona imprimible, `Fill`, recorte a la media hoja, páginas que no caben, encaje sin deformar de páginas de otro tamaño. |
| `Book/PageSequenceTest.php`     | Secuencia inicial, páginas quitadas, secuencias inválidas, numeración (desactivada, desde una página, blancos y páginas sin folio, recto y verso), validación de folios.                                                                                                                                 |
| `Book/BookLayoutTest.php`       | Márgenes en espejo: sin márgenes no transforma, reduce y centra, márgenes expresados como quedan impresos, valores inválidos, guardar y leer.                                                                                                                                                            |
| `Book/PageDesignTest.php`       | Diseño completo de ida y vuelta, ausencia de diseño, diseños inválidos.                                                                                                                                                                                                                                  |
| `Book/TextBlockBuilderTest.php` | Agrupación de una página real de Canva, posición con holgura, elección de fuente, separación de párrafos, alineaciones, párrafos con sangría, palabras en negrita o de color, nombres de fuente de PDF.                                                                                                  |
| `ArchTest.php`                  | Imposición sin dependencias, dominio sin Laravel, sin helpers de depuración.                                                                                                                                                                                                                             |

### Integración (`tests/Feature`)

| Archivo                                                                                      | Cubre                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Books/BookEditorTest.php`                                                                   | Ciclo completo del libro: crear desde PDF con miniaturas, aislamiento entre usuarios, guardar orden/blancos/folios, validación, imprimir con folios estampados, fondos y textos con fragmentos en negrita, espacios en los bordes de los fragmentos, imágenes de página, vista exacta, texto editable, **página escaneada** (borrado en la imagen), **tamaño de página más frecuente**, impresión sin el texto original, márgenes, fuentes no disponibles, borrar con sus archivos. |
| `Printing/PdfImposerTest.php`                                                                | Frente y dorso por hoja, orden de imposición en el PDF, blancos, **mismos bytes en cada ejecución**, páginas que no caben, hoja de calibración determinista.                                                                                                                                                                                                                                                                                                                        |
| `Printing/ImpositionPagesTest.php`                                                           | Pantallas de imposición rápida: formulario, imponer y ver pliegos, recordar opciones (con su tipo y corrigiendo las antiguas guardadas como texto), aislamiento, identificadores maliciosos, validación, páginas demasiado grandes, carta ajustada, descargas.                                                                                                                                                                                                                      |
| `Printing/CommandsTest.php`                                                                  | Comandos `noctua:test-document`, `noctua:impose` y `noctua:calibration`; errores de configuración sin excepciones.                                                                                                                                                                                                                                                                                                                                                                  |
| `Fonts/FontLibraryTest.php`                                                                  | Registrar leyendo familia/peso/estilo, fuentes propias visibles solo en su espacio, reemplazo de variante, archivos no imprimibles (WOFF, no fuentes, licencia restringida), borrar, catálogo.                                                                                                                                                                                                                                                                                      |
| `Workspaces/WorkspaceTest.php`                                                               | Registro personal y para editorial, nombre obligatorio, libros compartidos entre miembros y aislados del resto, cambio de espacio, props compartidas, miembros por correo, espacio personal sin miembros, protección del último propietario.                                                                                                                                                                                                                                        |
| `Settings/AppearanceTest.php`                                                                | Guardar modo y acento, página ya pintada con el tema, volver al color de Noctua, validación, texto legible sobre el acento.                                                                                                                                                                                                                                                                                                                                                         |
| `DashboardPageTest.php`                                                                      | Primeros pasos y libros recientes del espacio.                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `StorageIsolationTest.php`                                                                   | El disco es falso en los tests.                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `Auth/*`, `Settings/ProfileUpdateTest.php`, `Settings/SecurityTest.php`, `DashboardTest.php` | Autenticación, verificación de correo, 2FA, contraseña y perfil (de la plantilla, adaptados).                                                                                                                                                                                                                                                                                                                                                                                       |

### Frontend (`tests/js`)

| Archivo            | Cubre                                                            |
| ------------------ | ---------------------------------------------------------------- |
| `richText.test.ts` | Spans ↔ documento TipTap, unión de fragmentos, colores.          |
| `layout.test.ts`   | `contentFrame` con los **mismos valores** que `BookLayoutTest`.  |
| `accent.test.ts`   | Variables del acento y texto legible, igual que `AccentPalette`. |

## 5. Convenciones de trabajo

- **Commits directamente en `main`**, sin ramas. Mensajes en español, en imperativo, con un cuerpo que explique el porqué.
- Antes de cada commit: Pint, PHPStan, Pest, `npm run check` y `npm run types:check`. **No se sube nada que no pase.**
- Revisar que ningún archivo haya quedado vacío tras ediciones con scripts (`find app tests resources routes -type f -empty`).
- Cada arreglo de un error va con un test que lo reproduce.
- Si un cálculo existe en PHP y en TypeScript, se testea en los dos con los mismos valores.
- Después de cambiar rutas o controladores: `php artisan wayfinder:generate --with-form`.
- Después de cambiar el frontend, para verlo con `php artisan serve`: `npm run build` (o `composer dev` para recarga en caliente).
