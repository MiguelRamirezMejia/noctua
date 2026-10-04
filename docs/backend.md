# Backend: archivo por archivo

Recorrido por todo el código de servidor. Para la visión de conjunto, ver [arquitectura.md](arquitectura.md).

Convenciones del código PHP:

- `declare(strict_types=1)` no hace falta: PHPStan a nivel máximo vigila los tipos.
- Clases de dominio `final readonly`, con validación en el constructor.
- Comentarios y mensajes en español. Los nombres de clases y métodos van en inglés; las rutas y los textos visibles, en español.
- Unidades explícitas en el nombre: `widthMm`, `sizePt`, `creepMmPerSheet`.

---

## 1. Dominio (`app/Domain`)

El núcleo. No usa Laravel (lo comprueba `tests/Unit/ArchTest.php`).

### 1.1 `Domain/Imposition`: qué página va en cada sitio

Lógica pura, sin dependencias. Responde a una pregunta: _para un libro de N páginas en cuadernillos de k hojas, qué página va en cada cara de cada hoja_.

| Archivo                     | Qué hace                                                                                                                                                                                                                                                               |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ImpositionSpec.php`        | Parámetros de una imposición concreta: número de páginas, hojas por cuadernillo (1–8), último cuadernillo acortado o relleno, creep en mm por hoja. Valida los rangos. `pagesPerSignature()` = 4 × hojas.                                                              |
| `ImpositionSettings.php`    | Los mismos ajustes **sin** número de páginas (lo que elige el usuario). `forPageCount(n)` crea el `ImpositionSpec`.                                                                                                                                                    |
| `Imposer.php`               | El algoritmo. `plan(spec)` divide en cuadernillos y, para cada hoja i, aplica la fórmula frente izq = 4n−2i+2, der = 2i−1; dorso izq = 2i, der = 4n−2i+1. Las páginas que exceden el documento quedan en blanco (`null`). Calcula el creep acumulado por hoja anidada. |
| `ImpositionPlan.php`        | Resultado: lista de `Signature`. `sheets()`, `sheetCount()`, `paddedPageCount()`, `blankPageCount()`.                                                                                                                                                                  |
| `Signature.php`             | Un cuadernillo: número, primera página, hojas. `pageCount()`, `lastPage()`.                                                                                                                                                                                            |
| `Sheet.php`                 | Una hoja física (índice 1 = exterior) con su frente y su dorso.                                                                                                                                                                                                        |
| `SheetSide.php`             | Una cara de la hoja: `Slot` izquierdo y derecho. `pages()`.                                                                                                                                                                                                            |
| `Slot.php`                  | Media hoja: número de página (o `null` si es blanco) y desplazamiento de creep.                                                                                                                                                                                        |
| `InvalidImpositionSpec.php` | Excepción para parámetros inválidos.                                                                                                                                                                                                                                   |

### 1.2 `Domain/Printing`: geometría y PDF

| Archivo              | Qué hace                                                                                                                                                                                                                                                                                                                           |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Size.php`           | Tamaño en mm. `letterLandscape()` (279,4 × 215,9), `halfLetter()`, `isLandscape()`, `equals(otro, tolerancia)`.                                                                                                                                                                                                                    |
| `Offset.php`         | Corrección de registro X/Y en mm de una cara impresa.                                                                                                                                                                                                                                                                              |
| `PrinterProfile.php` | Calibración de una impresora: offset del frente, offset del dorso y zona no imprimible (5 mm por defecto). `offsetFor(Side)`.                                                                                                                                                                                                      |
| `Side.php`           | Enum `Front` / `Back`.                                                                                                                                                                                                                                                                                                             |
| `PageFit.php`        | Cómo se adapta la página a la media hoja: `Actual` (tamaño real; falla si no cabe), `Fit` (escala sin recortes, respetando la zona no imprimible) o `Fill` (llena la media hoja recortando). `label()` en español.                                                                                                                 |
| `PrintOptions.php`   | Opciones de salida: marcas de doblez y `PageFit`.                                                                                                                                                                                                                                                                                  |
| `SheetLayout.php`    | Geometría 2-up. Calcula la **escala** según `PageFit` y, para cada cara, devuelve `Placement`: lomo pegado al doblez (desplazado por el offset de esa cara), página centrada en vertical, creep hacia el lomo y **recorte** a la media hoja para que nunca invada la contigua. Lanza `PageDoesNotFit` si en modo `Actual` no cabe. |
| `Placement.php`      | Página colocada: número, x, y, ancho, alto y `Clip`.                                                                                                                                                                                                                                                                               |
| `Clip.php`           | Rectángulo de recorte en mm.                                                                                                                                                                                                                                                                                                       |
| `PageDoesNotFit.php` | Excepción: la página no cabe o el PDF no se puede leer.                                                                                                                                                                                                                                                                            |

#### `Domain/Printing/Pdf`

| Archivo                 | Qué hace                                                                                                                                                                                                                                                                                                                                                                               |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DeterministicFpdi.php` | Subclase de FPDI que produce siempre los mismos bytes: fecha fija (época Unix) en `_putinfo`, márgenes cero, sin salto automático. Añade `clipped(Clip, fn)` (recorte con `q re W n … Q`), `multiplyRect()` (rectángulo con fusión _Multiply_ vía ExtGState, para los tintes) y `latin1()` (convierte UTF-8 a la codificación de las fuentes base de FPDF).                            |
| `PdfComposer.php`       | Arma el **PDF del libro** página a página: importa las páginas de origen (o su versión sin texto), las encaja en el marco de márgenes con `fitInto()` (sin deformarlas si su tamaño es otro), apila capa «debajo» → página → tinte → capa «encima». `pageSize()` devuelve el tamaño de página **más frecuente** del PDF; `pageCount()` el número de páginas.                           |
| `PdfImposer.php`        | Genera el **PDF impuesto**: por cada hoja, una página para el frente y otra para el dorso (dúplex automático, volteo por el borde corto). Usa `Imposer` para el orden y `SheetLayout` para colocar y recortar; dibuja las marcas de doblez si se piden.                                                                                                                                |
| `CalibrationSheet.php`  | Hoja de calibración de dos caras: reglas de 100 mm (detectan si el driver escala), escalas de 1–10 mm en los bordes (miden la zona no imprimible), cruces de registro simétricas (al trasluz coinciden si frente y dorso están alineados) y retícula milimetrada en el dorso para leer la desviación. Aplica los offsets del perfil, así sirve también para comprobar una calibración. |
| `TestDocument.php`      | PDF de prueba con números grandes, cabeza marcada y lado del lomo indicado, para comprobar orden y orientación después de doblar. Lo usan los tests y la herramienta «documento de prueba».                                                                                                                                                                                            |

### 1.3 `Domain/Book`: el libro editado

| Archivo                   | Qué hace                                                                                                                                                                                                                                                                                                                              |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PageItem.php`            | Una página del libro: id estable, página de origen (o `null` = blanco), si lleva folio y su `PageDesign`. `isBlank()`.                                                                                                                                                                                                                |
| `PageSequence.php`        | El orden final de las páginas. `fromSource(n)` crea la secuencia 1..n. Valida ids únicos y páginas de origen existentes. `count()`, `removedSourcePages()` (las que el usuario quitó, para poder devolverlas) y `folios(FolioSettings)` (qué número va en cada posición).                                                             |
| `InvalidPageSequence.php` | Excepción de secuencia o numeración inválida.                                                                                                                                                                                                                                                                                         |
| `FolioSettings.php`       | Numeración: activada, desde qué posición, número inicial, posición, fuente, cuerpo (4–48 pt) y margen (0–60 mm), **tal como quedan impresos**.                                                                                                                                                                                        |
| `FolioPosition.php`       | Enum `bottom-center`, `bottom-outer`, `top-outer` con `label()`.                                                                                                                                                                                                                                                                      |
| `Folio.php`               | Un número a estampar: posición en el libro, texto y `isRecto()` (página impar = derecha).                                                                                                                                                                                                                                             |
| `BookLayout.php`          | Márgenes del libro en espejo (superior, inferior, interior/lomo, exterior). `frame(page, printScale, position)` calcula el `ContentFrame`: cuánto reducir y dónde colocar el contenido para dejar esos márgenes en el papel; el interior cambia de lado según la página sea par o impar. `hasMargins()`, `toArray()` y `fromArray()`. |
| `ContentFrame.php`        | Transformación del contenido: escala y desplazamiento (x, y) en mm. `isIdentity()`.                                                                                                                                                                                                                                                   |

#### `Domain/Book/Design`: lo que se añade a una página

| Archivo                               | Qué hace                                                                                                                                                                            |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PageDesign.php`                      | Diseño de una página: fondo opcional, lista de elementos y `hideSourceText`. `isEmpty()`, `textBoxes()`, `toArray()` y `fromArray()` (valida tipos, ids únicos, colores y fuentes). |
| `Element.php`                         | Interfaz común de los elementos: `id()` y `toArray()`.                                                                                                                              |
| `TextBox.php`                         | Cuadro de texto: `Box`, spans, fuente, cuerpo, interlineado, color, alineación horizontal y vertical, relleno opcional. `plainText()`.                                              |
| `TextSpan.php`                        | Fragmento con estilo propio (negrita, cursiva, color). Los saltos de línea van como `\n` dentro del texto.                                                                          |
| `RectangleShape.php`                  | Rectángulo de color con opacidad (para decorar o tapar algo del original).                                                                                                          |
| `Box.php`                             | Posición y tamaño en mm.                                                                                                                                                            |
| `Color.php`                           | Color `#rrggbb` validado. `rgb()` devuelve los tres componentes.                                                                                                                    |
| `PageBackground.php`                  | Fondo de página: color y `BackgroundMode`.                                                                                                                                          |
| `BackgroundMode.php`                  | `behind` (detrás de la página: se ve donde el original no pinta) o `tint` (teñido por multiplicación).                                                                              |
| `TextAlign.php` / `VerticalAlign.php` | Alineaciones: izquierda, centro, derecha, justificado / arriba, centro, abajo.                                                                                                      |
| `InvalidPageDesign.php`               | Excepción de diseño inválido.                                                                                                                                                       |

#### `Domain/Book/Extraction`: del texto del PDF a cuadros editables

| Archivo                | Qué hace                                                                                                                                                                                                                                                                                                                                                                             |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `TextLine.php`         | Una línea extraída: texto, caja en puntos, fragmentos con estilo, cuerpo y familia si se conocen. `size()` (si no hay cuerpo, lo estima por la altura), `center()`, `isBold()`, `styledRuns()` y `transformed(escala, dx, dy)` para llevarla a una página de otro tamaño.                                                                                                            |
| `TextRun.php`          | Fragmento de línea con estilo uniforme (negrita, cursiva, color).                                                                                                                                                                                                                                                                                                                    |
| `TextBlockBuilder.php` | Agrupa líneas en párrafos (mismo cuerpo, estilo y familia; solapadas en horizontal; espaciado constante) y crea un `TextBox` por párrafo en el mismo lugar. Detecta la alineación: justificado, párrafo de libro con sangría, centrado, derecha o izquierda. Deja holgura horizontal (15 %) porque la fuente sustituta puede ser más ancha. Conserva los saltos de línea originales. |
| `FontMatcher.php`      | Elige la fuente para una familia del PDF: la misma si está disponible en el espacio (comparando sin espacios ni mayúsculas) o la más parecida: manuscritas → Andika / Patrick Hand / Nunito; serif → Libertinus Serif / EB Garamond / Lora; resto → Open Sans / Nunito / Lato / Source Sans 3. `baseName()` limpia nombres como `ABCDEF+Verdana-BoldItalic` → `Verdana`.             |
| `FontMatch.php`        | Resultado: familia elegida y si es exacta.                                                                                                                                                                                                                                                                                                                                           |

### 1.4 `Domain/Typesetting`: Typst

| Archivo                 | Qué hace                                                                                                                                                                                                                                                                                                     |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Typst/TypstRunner.php` | Ejecuta Typst aislado: `compile` con `--root` limitado, `--ignore-system-fonts`, `--creation-timestamp 0`, rutas de fuentes y entradas (`--input clave=valor`). `fontFamilies(dir)` usa `typst fonts` para saber con qué nombre de familia ve Typst un archivo (puede no coincidir con el nombre comercial). |
| `Typst/PageLayers.php`  | Prepara el JSON de todas las páginas (tamaño, fondos, elementos, folios, marcos) y compila `resources/typst/capas.typ` una vez por capa: `debajo` o `encima`. `needsUnder()` y `needsOver()` evitan compilar una capa vacía.                                                                                 |
| `TypesettingFailed.php` | Excepción con el error de Typst (por ejemplo, una fuente inexistente).                                                                                                                                                                                                                                       |

---

## 2. Acciones (`app/Actions`)

Casos de uso. Cada una tiene un único método público principal.

### `Actions/Book`

| Archivo                 | Qué hace                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `CreateBookFromPdf.php` | Crea un libro desde un PDF subido: cuenta páginas, toma el tamaño de página más frecuente, crea la secuencia inicial y los folios por defecto, lo asigna al usuario y a su espacio activo, guarda `origen.pdf` y genera las miniaturas. Todo en una transacción: si algo falla, borra lo creado.                                                                                                                                                                                                      |
| `RenderThumbnails.php`  | Miniaturas JPEG de todas las páginas con `pdftoppm` (editor de páginas).                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `RenderPageImage.php`   | Imagen de una página a 110 ppp para el editor de pliego, en caché. Con `textless` usa la página sin texto.                                                                                                                                                                                                                                                                                                                                                                                            |
| `ExtractPageText.php`   | Extrae el texto de una página: posiciones fiables por línea con `pdftotext -bbox-layout`, y cuerpo, familia, color, negrita y cursiva con `pdftohtml -xml -hidden` (`-hidden` incluye la capa invisible de OCR de los escaneos). Une ambas fuentes de datos emparejando el texto.                                                                                                                                                                                                                     |
| `TextlessPage.php`      | Página sin su texto, en caché (`sin-texto/{n}.pdf`). Primero quita el texto vectorial con Ghostscript (`-dFILTERTEXT`, PDF 1.4 sin flujos de objetos, imágenes sin recomprimir). Después compara, palabra por palabra, la tinta antes y después a 72 ppp. Las palabras que no cambiaron están dentro de una imagen (escaneo): rasteriza la página a 300 ppp, borra esas líneas pintándolas con la mediana del color de alrededor y guarda la imagen (PNG en grises o JPEG) como PDF del mismo tamaño. |
| `PrintBook.php`         | `compose()`: calcula la escala de impresión y los marcos de márgenes, genera las capas Typst, pide las páginas sin texto necesarias y compone el PDF del libro (también para la vista exacta, con `only`). `handle()`: compone y luego impone con `ImposeUploadedPdf`.                                                                                                                                                                                                                                |

### `Actions/Imposition`

| Archivo                 | Qué hace                                                                                                                                                                                                               |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ImposeUploadedPdf.php` | Guarda el PDF fuente en `imposiciones/editorial-{id}/{ulid}/fuente.pdf`, lo impone con `PdfImposer` en `impuesto.pdf` y escribe `meta.json`. Se usa para la imposición rápida y como último paso de imprimir un libro. |
| `StoredImposition.php`  | Una imposición guardada: id, ajustes, impresora, opciones, nombre, páginas y fecha. `find()`, `recent()` (lee `meta.json`), `path(archivo)`, `toMeta()`. Provisional hasta que exista una tabla `builds`.              |

### `Actions/Publishers` y `Actions/Fortify`

| Archivo                          | Qué hace                                                                                                                                                                         |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Publishers/CreatePublisher.php` | `handle(owner, name, personal)` crea un espacio con slug único, añade al creador como propietario y lo deja activo. `personalFor(user)` devuelve o crea su espacio personal.     |
| `Fortify/CreateNewUser.php`      | Registro: valida nombre, correo, contraseña y tipo de cuenta (`personal` o `publisher`); crea el usuario, su espacio personal y, si toca, la editorial. Todo en una transacción. |
| `Fortify/ResetUserPassword.php`  | Restablece la contraseña (plantilla de Fortify).                                                                                                                                 |

---

## 3. Fuentes (`app/Fonts`)

| Archivo             | Qué hace                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `FontLibrary.php`   | Punto único para las fuentes. `families(publisher)`: las incluidas en Typst, las del catálogo y las subidas por el espacio. `catalogFor()` agrupa por categoría para la interfaz. `has()` valida que una fuente exista para el espacio. `typstPaths()` da las carpetas que se pasan a Typst. `register()` inspecciona el archivo, lo guarda con un nombre normalizado y registra la variante con el nombre de familia **que reporta Typst**, para que coincida al compilar. Catálogo en `fonts/catalogo`, subidas en `fonts/editoriales/{id}`. |
| `FontInspector.php` | Lee familia, peso y estilo de un TTF/OTF con php-font-lib. Rechaza WOFF/WOFF2 (no sirven para imprimir) y las fuentes cuya licencia prohíbe incrustarlas (`OS/2 fsType` restringido).                                                                                                                                                                                                                                                                                                                                                          |
| `FontFace.php`      | Datos de una variante leída del archivo.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `InvalidFont.php`   | Excepción con un mensaje para el usuario.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |

---

## 4. HTTP (`app/Http`)

### 4.1 Controladores

| Archivo                             | Rutas                                                                                           | Qué hace                                                                                                                                                                                                                                             |
| ----------------------------------- | ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DashboardController.php`           | `GET /dashboard`                                                                                | Inicio: libros recientes del espacio con portada, número de libros y los «primeros pasos» (subir un libro, editarlo, imprimirlo), que se marcan solos.                                                                                               |
| `BookController.php`                | `GET/POST /libros`, `GET/PUT/DELETE /libros/{book}`, miniaturas, `POST /libros/{book}/imprimir` | Lista de libros del espacio, subida, editor (`books/Edit` con páginas, folios, márgenes, fuentes y ajustes de impresión recordados), guardar, borrar (y sus archivos), servir miniaturas e imprimir. Al imprimir recuerda las opciones en la sesión. |
| `BookPreviewController.php`         | `GET …/paginas/{n}/imagen`, `POST …/paginas/{n}/texto-editable`, `POST …/vista-previa`          | Endpoints JSON del editor de pliego: imagen de página (con o sin texto), conversión del texto original en cuadros (encajados en la página del libro) y vista exacta de un pliego sin guardar.                                                        |
| `FontController.php`                | `/fuentes`                                                                                      | Catálogo y fuentes del espacio; subir (valida con `FontInspector`), borrar y servir el archivo para que el navegador pinte la fuente en el editor.                                                                                                   |
| `ImpositionController.php`          | `/imposicion`                                                                                   | Imposición rápida de cualquier PDF: formulario, imponer, ver los pliegos (qué página va en cada cara) y descargar.                                                                                                                                   |
| `PrintingToolsController.php`       | `/imposicion/calibracion`, `/imposicion/documento-prueba`                                       | Descarga la hoja de calibración y el documento de prueba.                                                                                                                                                                                            |
| `PublisherController.php`           | `/settings/espacio…`, `/espacios`, `/espacio-actual`                                            | Ver y renombrar el espacio, crear editoriales, cambiar de espacio, invitar miembros por correo, cambiar su rol y quitarlos (solo propietarios; nunca dejar el espacio sin propietario).                                                              |
| `Settings/AppearanceController.php` | `/settings/appearance`                                                                          | Modo claro/oscuro/sistema y color de acento (con colores predefinidos).                                                                                                                                                                              |
| `Settings/ProfileController.php`    | `/settings/profile`                                                                             | Nombre, correo y borrar la cuenta.                                                                                                                                                                                                                   |
| `Settings/SecurityController.php`   | `/settings/security`, `/settings/password`                                                      | Contraseña, verificación en dos pasos y llaves de acceso.                                                                                                                                                                                            |
| `Controller.php`                    | —                                                                                               | Clase base vacía.                                                                                                                                                                                                                                    |

### 4.2 Peticiones validadas (`Requests`)

| Archivo                    | Qué hace                                                                                                                                                                                                                                       |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `BookContentRequest.php`   | Contenido editable del libro (páginas con diseño, folios, márgenes). Valida y construye `sequence()`, `folioSettings()` y `bookLayout()`. Si el dominio rechaza algo, lo convierte en error de validación. La usan «guardar» y «vista exacta». |
| `PrintSettingsRequest.php` | Opciones de impresión: hojas por cuadernillo, último acortado, creep, offsets, zona no imprimible, ajuste de página y marcas de doblez. `settings()`, `printer()`, `options()`, `defaults()`.                                                  |
| `ImposeRequest.php`        | Lo anterior más el PDF subido (imposición rápida).                                                                                                                                                                                             |
| `Settings/*Request.php`    | Validación de perfil, contraseña, borrar cuenta y 2FA (plantilla).                                                                                                                                                                             |

### 4.3 Middleware y utilidades

| Archivo                                 | Qué hace                                                                                                                                                 |
| --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Middleware/EnsureUserHasPublisher.php` | En cada petición autenticada garantiza un espacio activo válido del que el usuario sea miembro.                                                          |
| `Middleware/HandleAppearance.php`       | Comparte con la vista Blade el modo y el estilo del acento, del usuario o de la cookie, para que la página llegue con su tema.                           |
| `Middleware/HandleInertiaRequests.php`  | Props compartidas por todas las páginas: nombre de la app, usuario, espacio activo y su rol, lista de espacios, apariencia y estado de la barra lateral. |
| `PrintDefaults.php`                     | Normaliza las opciones de impresión guardadas en sesión (que pueden llegar como texto) a tipos correctos.                                                |

---

## 5. Modelos (`app/Models`)

| Archivo                   | Qué hace                                                                                                                                                                                                                                                                           |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `User.php`                | Usuario con Fortify (2FA, passkeys). `publishers()`, `currentPublisher()`, `publisher()` (espacio activo), `belongsToPublisher()`, `roleIn()`, `switchPublisher()`. Campos `appearance` y `accent_color`.                                                                          |
| `Publisher.php`           | Espacio de trabajo (inquilino). `members()` con el rol en el pivote, `books()`, `fonts()`, `roleOf(user)`.                                                                                                                                                                         |
| `PublisherMembership.php` | Pivote `publisher_user` con el rol como enum.                                                                                                                                                                                                                                      |
| `Book.php`                | Libro. Rutas de sus archivos (`directory()`, `sourcePath()`, `thumbnailPath()`, `pageImagePath()`, `textlessPagePath()`), `pageSize()`, y conversión JSON ↔ dominio: `sequence()` / `setSequence()`, `folioSettings()` / `setFolioSettings()`, `bookLayout()` / `setBookLayout()`. |
| `Font.php`                | Variante de fuente (familia, peso, cursiva). Sin `publisher_id` = catálogo. `scopeAvailableTo(publisher)`: catálogo más las del espacio.                                                                                                                                           |

`App\Enums\PublisherRole`: `owner` / `editor` con `label()` en español.

`App\Policies\BookPolicy`: ver y editar = ser miembro del espacio del libro; borrar = haberlo creado o ser propietario.

---

## 6. Consola (`app/Console/Commands`)

| Comando                                  | Archivo                         | Qué hace                                                                                                                                                                                                                      |
| ---------------------------------------- | ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `noctua:impose {source} {output?}`       | `ImposeCommand.php`             | Impone un PDF desde la terminal. Opciones `--sheets`, `--short-last`, `--creep`, `--front-offset`, `--back-offset`, `--unprintable`, `--fit`, `--no-fold-marks`. Muestra una tabla con los cuadernillos.                      |
| `noctua:calibration {output}`            | `CalibrationCommand.php`        | Genera la hoja de calibración, con offsets opcionales.                                                                                                                                                                        |
| `noctua:test-document {output} --pages=` | `TestDocumentCommand.php`       | Genera el documento de prueba.                                                                                                                                                                                                |
| `noctua:fonts:install`                   | `InstallFontCatalogCommand.php` | Descarga de Google Fonts las 44 familias del catálogo (`config/noctua.php`) en regular, negrita, cursiva y negrita cursiva. Reintenta ante cortes de red. `--family=` para unas concretas, `--force` para volver a descargar. |
| —                                        | `ParsesPrintingOptions.php`     | Trait para leer opciones de consola con tipos (`floatOption`, offsets «x,y»…).                                                                                                                                                |

---

## 7. Proveedores y soporte

| Archivo                                                              | Qué hace                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| -------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Providers/AppServiceProvider.php`                                   | Registra como singletons los servicios que llaman a binarios externos, con sus rutas de `config/noctua.php`: `TypstRunner`, `PageLayers`, `TextlessPage`, `ExtractPageText`, `RenderPageImage`, `RenderThumbnails`. Configura los valores por defecto: fechas inmutables (`CarbonImmutable`), comandos destructivos de base de datos prohibidos en producción y contraseñas exigentes en producción (12 caracteres, mayúsculas, números, símbolos, no filtradas). |
| `Providers/FortifyServiceProvider.php`                               | Conecta Fortify con las páginas Inertia de autenticación y con las acciones de registro y contraseña. Limita los intentos de login y 2FA.                                                                                                                                                                                                                                                                                                                         |
| `Support/AccentPalette.php`                                          | A partir del color de acento calcula las variables CSS del tema: color principal, texto legible encima (blanco o negro por contraste WCAG), anillo de foco y fondos de hover. Misma lógica que `resources/js/lib/accent.ts`.                                                                                                                                                                                                                                      |
| `Concerns/PasswordValidationRules.php`, `ProfileValidationRules.php` | Reglas de validación reutilizadas en el registro y los ajustes.                                                                                                                                                                                                                                                                                                                                                                                                   |

---

## 8. Configuración, rutas y arranque

| Archivo               | Qué hace                                                                                                                                                                                                                                                                                                  |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `bootstrap/app.php`   | Arranque: rutas, middleware web (`HandleAppearance`, `EnsureUserHasPublisher`, `HandleInertiaRequests`), cookies sin cifrar (`appearance`, `sidebar_state`) y **excepción de TrimStrings** para el texto de los spans (los espacios en los bordes de un fragmento son parte del texto: «un » + «dragón»). |
| `config/noctua.php`   | Rutas a los binarios (`NOCTUA_TYPST`, `NOCTUA_PDFTOPPM`, `NOCTUA_PDFTOTEXT`, `NOCTUA_PDFTOHTML`, `NOCTUA_GHOSTSCRIPT`), tiempo máximo de los procesos (`NOCTUA_PROCESS_TIMEOUT`) y catálogo de 44 familias por categoría.                                                                                 |
| `config/*.php`        | Configuración estándar de Laravel; `app.locale` es `es`.                                                                                                                                                                                                                                                  |
| `routes/web.php`      | Portada, inicio, libros, editor y sus endpoints, fuentes, imposición rápida y herramientas. Todo tras `auth` y `verified`. URLs en español (`/libros`, `/fuentes`, `/imposicion`).                                                                                                                        |
| `routes/settings.php` | Perfil, seguridad, espacio y miembros, cambio de espacio, apariencia y `.well-known/passkey-endpoints`.                                                                                                                                                                                                   |
| `routes/console.php`  | Comando de ejemplo de Laravel.                                                                                                                                                                                                                                                                            |

### Migraciones (`database/migrations`)

| Migración                           | Qué hace                                                                                                                                                                                    |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `0001_…_create_users/cache/jobs`    | Tablas estándar de Laravel (usuarios, sesiones, caché, colas).                                                                                                                              |
| `2024_…_create_passkeys_table`      | Llaves de acceso (WebAuthn).                                                                                                                                                                |
| `2025_…_add_two_factor_columns`     | Columnas de 2FA.                                                                                                                                                                            |
| `2026_09_29_…_create_books_table`   | Libros: título, archivo, número y tamaño de página, `pages` y `folios` (JSON).                                                                                                              |
| `2026_10_03_…_create_fonts_table`   | Fuentes: familia, peso, cursiva, categoría, familia de catálogo, ruta, nombre original, sha256.                                                                                             |
| `2026_10_04_…_introduce_publishers` | Crea `publishers` y `publisher_user`, añade `current_publisher_id` a usuarios y `publisher_id` a libros y fuentes, y **migra los datos existentes**: un espacio por usuario con sus libros. |
| `…_add_personal_to_publishers`      | Marca de espacio personal.                                                                                                                                                                  |
| `…_add_layout_to_books`             | Márgenes del libro (JSON).                                                                                                                                                                  |
| `…_add_appearance_to_users`         | Modo y color de acento.                                                                                                                                                                     |

Factorías: `UserFactory` (crea también el espacio personal), `PublisherFactory`, `BookFactory`. `DatabaseSeeder` crea un usuario de ejemplo (`test@example.com`).

---

## 9. Plantilla Typst (`resources/typst/capas.typ`)

Recibe por `sys.inputs` el JSON de todas las páginas y la capa a dibujar.

- Página del tamaño del libro, sin márgenes y transparente (`fill: none`).
- **Capa `debajo`**: rectángulo a página completa con el color de fondo «detrás».
- **Capa `encima`**: cada elemento dentro de la transformación del marco de márgenes (`place` + `scale` sin reflujo), cuadros de texto como `block` con su tamaño, relleno, alineación, interlineado al estilo CSS (`leading = (line_height − 1,15) × cuerpo`) y spans con peso, estilo y color; los `\n` son `linebreak()`. Los rectángulos llevan opacidad. Los folios van a página completa (no se reducen con los márgenes) en la posición elegida, alternando el lado exterior entre recto y verso.

---

## 10. Traducciones (`lang/`)

- `lang/es.json`: cadenas sueltas de Laravel y Fortify.
- `lang/es/validation.php`: mensajes de validación y nombres de atributos en español.
- `lang/es/auth.php`, `passwords.php`, `pagination.php`: autenticación, restablecimiento y paginación.

## 11. Vista raíz

`resources/views/app.blade.php`: el único HTML. Pone la clase `dark` y las variables del acento en `<html>` desde el servidor, un script mínimo que aplica el modo del sistema antes de pintar (evita el parpadeo), las fuentes de la interfaz y el punto de montaje de Inertia.
