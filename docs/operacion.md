# Operación: instalar, configurar y mantener

## 1. Requisitos

| Programa    | Versión | Para qué                                                                                          | Windows (nativo)                        | Linux (Debian/Ubuntu)                                           |
| ----------- | ------- | ------------------------------------------------------------------------------------------------- | --------------------------------------- | --------------------------------------------------------------- |
| PHP         | 8.4     | Servidor (extensiones: curl, fileinfo, **gd**, intl, mbstring, openssl, pdo_sqlite, sqlite3, zip) | `winget install PHP.PHP.8.4`            | `apt install php8.4 php8.4-{gd,intl,mbstring,sqlite3,zip,curl}` |
| Composer    | 2       | Dependencias PHP                                                                                  | `winget install Composer.Composer`      | instalador oficial                                              |
| Node        | 22      | Compilar la interfaz                                                                              | `winget install OpenJS.NodeJS.LTS`      | nodesource o nvm                                                |
| Typst       | 0.15.1  | Capas de texto, fondos y folios                                                                   | `winget install Typst.Typst`            | binario de GitHub en `/usr/local/bin`                           |
| Poppler     | 25.x    | Miniaturas, imágenes, extracción de texto                                                         | `winget install oschwartz10612.Poppler` | `apt install poppler-utils`                                     |
| Ghostscript | 10.x    | Quitar el texto de las páginas                                                                    | instalador AGPL de ghostscript.com      | `apt install ghostscript`                                       |

Ghostscript tiene licencia AGPL: es el programa que pide instalar «GPL Ghostscript». Solo hace falta para «Hacer editable el texto».

En Windows, PHP necesita el certificado raíz para descargar las fuentes: `curl.cainfo` y `openssl.cafile` en `php.ini` apuntando a un `cacert.pem`.

## 2. Instalación

```bash
composer setup
```

Hace, en orden: `composer install`, copia `.env.example` a `.env`, genera la clave, migra la base de datos SQLite, **descarga el catálogo de fuentes** (`noctua:fonts:install`), `npm install` y `npm run build`.

## 3. Arrancar

```bash
php artisan serve --port=8010     # http://127.0.0.1:8010
```

Para desarrollar con recarga en caliente: `composer dev` (servidor, cola, logs y Vite a la vez).

El puerto 8010 es el que se usa en este equipo porque el 8000 lo ocupa otro programa. `APP_URL` debe coincidir.

## 4. Variables de entorno propias (`.env`)

| Variable                 | Por defecto | Qué es                                   |
| ------------------------ | ----------- | ---------------------------------------- |
| `NOCTUA_TYPST`           | `typst`     | Ruta al binario de Typst.                |
| `NOCTUA_PDFTOPPM`        | `pdftoppm`  | Poppler: páginas a imagen.               |
| `NOCTUA_PDFTOTEXT`       | `pdftotext` | Poppler: texto con posiciones.           |
| `NOCTUA_PDFTOHTML`       | `pdftohtml` | Poppler: texto con estilos.              |
| `NOCTUA_GHOSTSCRIPT`     | `gs`        | Ghostscript (`gswin64c.exe` en Windows). |
| `NOCTUA_PROCESS_TIMEOUT` | `120`       | Segundos máximos por proceso externo.    |
| `APP_LOCALE`             | `es`        | Idioma de la interfaz y de los mensajes. |
| `QUEUE_CONNECTION`       | `database`  | Cola (preparada para trabajos largos).   |

**En Windows hay que poner rutas absolutas**, porque winget no siempre deja los binarios en el PATH del servidor web. Por ejemplo:

```dotenv
NOCTUA_TYPST=C:/Users/<usuario>/AppData/Local/Microsoft/WinGet/Packages/Typst.Typst_…/typst-x86_64-pc-windows-msvc/typst.exe
NOCTUA_PDFTOPPM=C:/Users/<usuario>/AppData/Local/Microsoft/WinGet/Packages/oschwartz10612.Poppler_…/poppler-25.07.0/Library/bin/pdftoppm.exe
NOCTUA_GHOSTSCRIPT=C:/Users/<usuario>/AppData/Local/Programs/gs/bin/gswin64c.exe
```

## 5. Comandos útiles

| Comando                                                                 | Qué hace                                     |
| ----------------------------------------------------------------------- | -------------------------------------------- |
| `php artisan noctua:fonts:install [--family=…] [--force]`               | Descarga o actualiza el catálogo de fuentes. |
| `php artisan noctua:impose libro.pdf [salida.pdf] --sheets=4 --fit=fit` | Impone un PDF sin pasar por la web.          |
| `php artisan noctua:calibration calibracion.pdf --back-offset=0.5,-0.3` | Hoja de calibración con offsets.             |
| `php artisan noctua:test-document prueba.pdf --pages=16`                | Documento de prueba numerado.                |
| `php artisan wayfinder:generate --with-form`                            | Regenera las rutas tipadas del frontend.     |
| `php artisan migrate`                                                   | Aplica migraciones nuevas.                   |

## 6. Dónde está cada cosa en disco

Ver [arquitectura.md §7](arquitectura.md#7-almacenamiento-en-disco). En resumen, todo bajo `storage/app/private/`:

- `libros/{usuario}/{libro}/origen.pdf`: el PDF original. **No borrar.**
- `libros/…/miniaturas`, `paginas`, `sin-texto`: cachés. Se pueden borrar sin perder nada; se regeneran.
- `imposiciones/editorial-{id}/{ulid}/impuesto.pdf`: **el PDF listo para imprimir** que se descarga.
- `fonts/catalogo` y `fonts/editoriales/{id}`: fuentes.

Base de datos local: `database/database.sqlite`.

## 7. Imprimir correctamente

1. Una vez por impresora: imprime la **hoja de calibración** en dúplex por el borde corto, mira al trasluz las cruces de registro y anota la desviación del dorso. Introdúcela como offset del dorso.
2. Genera el PDF del libro desde «Imprimir».
3. En el diálogo de impresión: **tamaño real (100 %)**, carta, horizontal, **dúplex por el borde corto**.
4. Dobla cada hoja por la mitad, anida las hojas de cada cuadernillo y cose.

## 8. Solución de problemas

| Síntoma                                                      | Causa probable                                  | Solución                                                                                                           |
| ------------------------------------------------------------ | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| «No se pudo generar la imagen de la página»                  | Poppler no encontrado                           | Revisa `NOCTUA_PDFTOPPM` (ruta absoluta en Windows).                                                               |
| «Hacer editable el texto» falla o tarda                      | Ghostscript no instalado o sin carpeta temporal | Instala Ghostscript y revisa `NOCTUA_GHOSTSCRIPT`. Noctua ya le pasa `TEMP`/`TMP` propios.                         |
| El texto editable no aparece y la página se queda igual      | PDF escaneado sin capa OCR                      | Sin OCR no hay texto que extraer: pasa el PDF por un OCR antes de subirlo.                                         |
| Una página muy pequeña o deformada                           | PDF con páginas de varios tamaños               | El libro toma el tamaño más frecuente; las demás se encajan centradas. Revisa el tamaño en la cabecera del editor. |
| Error de Typst sobre una fuente                              | La fuente no está instalada para el espacio     | Instala el catálogo (`noctua:fonts:install`) o sube la fuente en «Fuentes».                                        |
| Descarga de fuentes con «cURL error 60»                      | PHP sin certificados raíz                       | Configura `curl.cainfo` y `openssl.cafile` en `php.ini`.                                                           |
| Descarga de fuentes interrumpida                             | Corte de red                                    | Vuelve a ejecutar el comando: solo descarga lo que falta.                                                          |
| Frente y dorso desalineados al imprimir                      | Falta calibrar                                  | Hoja de calibración y offset del dorso.                                                                            |
| El contenido sale reducido                                   | El driver escala                                | Imprime a tamaño real; comprueba que las reglas de la hoja de calibración midan 100 mm.                            |
| No se ven los cambios del frontend                           | Assets sin compilar                             | `npm run build` o usa `composer dev`.                                                                              |
| Una vista falla con «Unable to locate file in Vite manifest» | Lo mismo                                        | `npm run build`.                                                                                                   |

## 9. Producción (previsto)

- PostgreSQL en lugar de SQLite.
- Almacenamiento S3 para `libros/` e `imposiciones/` (todo pasa ya por `Storage`).
- Trabajadores de cola para imprimir libros grandes en segundo plano.
- `APP_DEBUG=false`. Las contraseñas exigentes y la prohibición de comandos destructivos se activan solas en producción.

## 10. Modo de prueba (compartir sin guardar nada)

Con `NOCTUA_DEMO=true`, la portada muestra **«Probar Noctua sin cuenta»**:

- Crea una cuenta temporal (`users.demo_expires_at`) con su propio espacio, aislada de todo lo demás.
- Se puede usar todo: subir PDF, editar, marcos, vista exacta, generar el PDF para imprimir.
- No se puede cambiar la cuenta ni el espacio (correo, contraseña, miembros, editoriales). Máximo `NOCTUA_DEMO_MAX_BOOKS` libros (3 por defecto).
- **Al salir** («Salir y borrar») se borra la cuenta con sus libros, fuentes, imposiciones y archivos. Si no sale, caduca a las `NOCTUA_DEMO_TTL_HOURS` horas (6 por defecto) y se borra al crear la siguiente cuenta de prueba, con `php artisan noctua:demo:purge` o con el programador (`php artisan schedule:work`, cada hora).

Para compartirlo por internet se usa un túnel (por ejemplo, Cloudflare Tunnel). Mientras esté compartido:

- **`APP_DEBUG=false`**: con `true`, un error mostraría rutas y configuración a cualquiera.
- **Contraseñas fuertes** en las cuentas reales: la aplicación es accesible desde fuera.
- Comparte la dirección solo con personas de confianza y cierra el túnel al terminar. Noctua procesa los PDF subidos con Poppler y Ghostscript, que reciben archivos de desconocidos.
- `php artisan serve` atiende una petición a la vez: sirve para una o dos personas probando, no para muchas.
