# Noctua

**Noctua** es una aplicación web para preparar e imprimir libros que se cosen a mano por cuadernillos. Toma un libro (por ejemplo, un PDF diseñado en Canva), permite editarlo página a página y genera el PDF listo para imprimir en una impresora doméstica, doblar y coser.

Nació para una editorial artesanal y está pensada para crecer como servicio para otras editoriales y autores independientes.

---

## Para quién es

- **Autores y artesanos del libro** que trabajan solos: cada cuenta tiene su **espacio personal**.
- **Editoriales pequeñas** que trabajan en equipo: una **editorial** es un espacio compartido donde varios miembros editan los mismos libros.

## Qué problema resuelve

Imprimir un libro cosido a mano en una impresora casera exige:

1. Reordenar las páginas para que, al doblar las hojas y meterlas unas dentro de otras, el libro se lea en orden (**imposición**).
2. Colocar dos páginas por cara en hojas carta horizontales, con frente y dorso alineados.
3. Ajustar a la impresora concreta: zona no imprimible, desviación del dorso, escala.

Hacer esto a mano es lento y propenso a errores. Noctua lo automatiza, y además permite retocar el libro sin volver al programa original.

---

## Qué puedes hacer

### Libros desde un PDF ya maquetado

- Subir el PDF de un libro (Canva, Word, InDesign…). El original se guarda intacto; lo que edites se aplica al imprimir.
- **Organizar páginas**: reordenar arrastrando, quitar y devolver páginas, duplicar, insertar páginas en blanco.
- **Numerar páginas**: desde qué página, con qué número, posición (centrado o esquina exterior), tipo de letra, tamaño y margen; quitar el número en páginas concretas.
- Páginas más grandes que media carta (por ejemplo, carta) se **ajustan sin recortes**.

### Editor de pliego

Las dos páginas enfrentadas, en grande, como el libro abierto:

- **Cuadros de texto** con tipo de letra, tamaño, interlineado, color y alineación, y **palabras sueltas en negrita, cursiva o de otro color**.
- **Rectángulos** de color (también para tapar algo del original).
- **Fondo de color** por página: detrás de la página o teñido.
- **Texto editable**: convierte el texto original del PDF en cuadros editables, conservando posición, tamaño, alineación y estilos; las ilustraciones no se tocan.
- **Vista exacta**: muestra el pliego tal como saldrá impreso.
- Deshacer y rehacer, atajos de teclado, posición exacta en milímetros.

### Fuentes

- Catálogo de **44 familias libres** (serif para libro, sans, infantiles y manuscritas, títulos) con regular, negrita y cursiva.
- **Fuentes propias**: cada espacio sube sus TTF/OTF; Noctua rechaza las que no permiten incrustarse en un PDF.

### Impresión

- **Imposición por cuadernillos** de 1 a 8 hojas (4 a 32 páginas), con relleno de blancos o último cuadernillo acortado.
- PDF en **carta horizontal, dos páginas por cara**, frente y dorso para dúplex por el borde corto.
- **Marcas de doblez**, compensación de **creep** (desplazamiento de las hojas interiores).
- **Hoja de calibración** de la impresora: reglas de 100 mm, escalas de zona no imprimible y cruces de registro para medir la desviación del dorso.
- **Vista de pliegos** antes de imprimir: qué página va en cada hoja, frente y dorso.
- **Imposición rápida** de cualquier PDF sin crear un libro.

### Espacios de trabajo

- Espacio **personal** por cuenta y **editoriales** compartidas.
- Roles: **propietario** (administra miembros y puede borrar libros) y **editor** (trabaja en los libros).
- Selector de espacio en el menú.

---

## Cómo se usa (flujo típico)

1. Regístrate eligiendo **«Para mí»** o **«Para una editorial»**.
2. En **Libros**, sube el PDF.
3. Ordena las páginas y activa la numeración.
4. Abre el **editor de pliego** para retocar textos, fondos o tipografía.
5. Imprime la **hoja de calibración** una vez para conocer tu impresora.
6. **Genera el PDF para imprimir**, revisa los pliegos y descárgalo.
7. Imprime a tamaño real, en dúplex por el borde corto; dobla, anida y cose.

---

## Cómo está construido

| Capa                        | Tecnología                                                                          |
| --------------------------- | ----------------------------------------------------------------------------------- |
| Backend                     | Laravel 13, PHP 8.4                                                                 |
| Frontend                    | Vue 3 (Composition API) + TypeScript + Inertia.js + Tailwind CSS 4                  |
| Editor de texto enriquecido | TipTap 3                                                                            |
| Composición tipográfica     | Typst (capas de texto, fondos y números de página)                                  |
| PDF e imposición            | FPDI + FPDF (salida determinista)                                                   |
| Análisis de PDF             | Poppler (miniaturas, extracción de texto)                                           |
| Quitar texto de páginas     | Ghostscript                                                                         |
| Base de datos               | SQLite en local; PostgreSQL previsto en producción                                  |
| Calidad                     | Pest (más de 350 tests), Vitest, PHPStan a nivel máximo, Pint, CI en GitHub Actions |

### Arquitectura del dominio

El núcleo está separado del framework:

- **`Domain/Imposition`**: orden de páginas por hoja y cuadernillo. PHP puro, sin dependencias.
- **`Domain/Printing`**: geometría de la hoja, perfil de impresora, composición e imposición del PDF.
- **`Domain/Book`**: secuencia de páginas, numeración, diseño de página (fondos, textos, rectángulos) y extracción de texto.
- **`Domain/Typesetting`**: ejecución aislada de Typst (raíz limitada, sin fuentes del sistema, tiempo máximo).

Principios: el PDF original nunca se modifica (la edición son datos), la salida es determinista (mismo libro → mismo PDF) y cada espacio de trabajo está aislado.

---

## Requisitos para ejecutarlo

PHP 8.4, Composer, Node 22, Typst 0.15, Poppler 25 y Ghostscript 10. Instrucciones detalladas en el [README](../README.md).

```bash
composer setup                     # dependencias, base de datos, fuentes y compilación
php artisan serve --port=8010      # abre http://127.0.0.1:8010
```

---

## Hoja de ruta

Hecho:

- Imposición, calibración e impresión.
- Libros desde PDF: organización de páginas, numeración, editor de pliego, texto editable.
- Catálogo y subida de fuentes.
- Espacios personales y editoriales.
- Márgenes del libro en espejo y cambios para todas las páginas (fondo, tipo de letra).
- Apariencia por cuenta: modo claro, oscuro o del sistema y color de acento.
- Interfaz completa en español, con identidad propia, portada e inicio con primeros pasos.

Más adelante:

- Libros escritos desde cero en Noctua (editor de manuscrito con maquetación automática en Typst).
- Marcas de costura, dúplex manual guiado, impresión por rangos.
- Servicio en la nube para otras editoriales: suscripciones, almacenamiento S3, colaboración en tiempo real, EPUB y cubiertas con cálculo de lomo.
