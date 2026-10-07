# Frontend: archivo por archivo

La interfaz es una SPA servida por Laravel con **Inertia**: cada ruta del servidor devuelve `Inertia::render('carpeta/Pagina', props)` y Vue monta `resources/js/pages/carpeta/Pagina.vue` con esas props. No hay API REST ni gestor de estado global: el estado de cada pantalla vive en la página (normalmente en un `useForm` de Inertia) y se envía al guardar.

Pila: Vue 3.5 (`<script setup lang="ts">`), TypeScript, Tailwind CSS 4, componentes **shadcn-vue** (sobre reka-ui) en `components/ui`, iconos `@lucide/vue`, TipTap 3 para el texto enriquecido, `vue-sonner` para avisos. Compilación con Vite 8 / vite-plus.

---

## 1. Arranque y estilos

| Archivo                                     | Qué hace                                                                                                                                                                                                                                  |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app.ts`                                    | Crea la app Inertia: resuelve las páginas de `pages/`, elige el layout según la ruta (autenticación, ajustes o aplicación), aplica el tema guardado (`initializeTheme`) y conecta los avisos flash del servidor (`initializeFlashToast`). |
| `css/app.css`                               | Tailwind 4 con las variables del tema (`--primary`, `--background`, `--ring`…) en claro y en oscuro. El color de acento las sobrescribe desde `<html style>`.                                                                             |
| `types/vue-shims.d.ts`, `types/global.d.ts` | Tipos de los `.vue` y de las props compartidas de Inertia (`auth`, `appearance`, `sidebarOpen`…).                                                                                                                                         |

## 2. Rutas tipadas (generadas)

`routes/` y `actions/` los genera **Wayfinder** (`php artisan wayfinder:generate --with-form`) a partir de las rutas y los controladores de Laravel. Se importan así: `import { update } from '@/routes/books'`. No se editan a mano y no están en git.

---

## 3. Páginas (`pages/`)

### Libros

| Archivo           | Qué hace                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `books/Index.vue` | Lista de libros del espacio activo con portada, páginas y fecha; zona para subir un PDF (arrastrar o elegir) con progreso; borrar.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `books/Edit.vue`  | **Editor del libro**, la pantalla central. Mantiene en un `useForm` las páginas, los folios y los márgenes. Secciones: **Páginas** (cuadrícula de pliegos con miniaturas: la 1 sola a la derecha y luego pares; reordenar arrastrando, quitar, devolver, duplicar, insertar blancos, quitar el número en una página); **Márgenes del libro** (plantillas y valores en mm, en espejo); **Marco decorativo** (galería de los diez diseños con vista previa real, color, grosor, distancia al borde y tamaño de los adornos); **Numeración** (desde qué página, número inicial, posición, fuente, tamaño, margen); **Imprimir** (cuadernillos, ajuste, offsets, marcas; genera el PDF impuesto); y el botón de cada pliego que abre el **editor de pliego** (`SpreadEditor`). Muestra los folios y los diseños sobre las miniaturas. Avisa si hay cambios sin guardar. |

### Fuentes, imposición e inicio

| Archivo                 | Qué hace                                                                                                                                                                                             |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `fonts/Index.vue`       | Catálogo por categorías con muestra de cada familia, fuentes subidas por el espacio (subir TTF/OTF, borrar) y errores claros si la fuente no sirve.                                                  |
| `imposition/Create.vue` | Imposición rápida: subir cualquier PDF, elegir opciones (cuadernillo, ajuste, creep, offsets, marcas) y descargar la hoja de calibración o el documento de prueba. Lista las imposiciones recientes. |
| `imposition/Show.vue`   | Resultado: resumen, descarga y vista de pliegos (qué página va en cada cara de cada hoja) con `SheetFace`.                                                                                           |
| `Dashboard.vue`         | Inicio: saludo, primeros pasos con progreso, libros recientes y accesos a las herramientas.                                                                                                          |
| `Welcome.vue`           | Portada pública: qué es Noctua, cómo funciona y accesos para entrar o registrarse.                                                                                                                   |

### Autenticación (`pages/auth/`)

`Login.vue`, `Register.vue` (con el tipo de cuenta: «Para mí» o «Para una editorial» y su nombre), `ForgotPassword.vue`, `ResetPassword.vue`, `VerifyEmail.vue`, `ConfirmPassword.vue`, `TwoFactorChallenge.vue`. Usan `AuthBrandLayout` y están en español.

### Ajustes (`pages/settings/`)

| Archivo          | Qué hace                                                                                                   |
| ---------------- | ---------------------------------------------------------------------------------------------------------- |
| `Profile.vue`    | Nombre, correo y borrar la cuenta (`DeleteUser`).                                                          |
| `Security.vue`   | Contraseña, verificación en dos pasos (`ManageTwoFactor`) y llaves de acceso (`ManagePasskeys`).           |
| `Appearance.vue` | Modo claro/oscuro/sistema, colores de acento predefinidos, color propio y vista previa en vivo.            |
| `Workspace.vue`  | Espacio activo: nombre, miembros con su rol, invitar por correo, cambiar rol, quitar; crear una editorial. |

---

## 4. Editor de pliego (`components/book/editor/`)

El componente más complejo. Muestra las dos páginas enfrentadas en grande y permite editar cada una.

```
SpreadEditor.vue
 ├── barra superior: cerrar, pliego anterior/siguiente, + Texto, + Rectángulo,
 │                   deshacer/rehacer, Vista exacta, Guardar
 ├── escenario: dos PageCanvas (o las imágenes de la vista exacta)
 └── inspector lateral: fondo de página, texto original, propiedades del elemento
       PageCanvas.vue  (una página: imagen + fondo + elementos arrastrables)
         └── RichTextEditor.vue  (TipTap dentro del cuadro en edición)
```

| Archivo              | Qué hace                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `SpreadEditor.vue`   | Orquesta el pliego. **Selección y edición** (clic selecciona, doble clic edita el texto). **Historial** propio de deshacer/rehacer por pliego (instantáneas JSON con retardo). **Crear y modificar** textos y rectángulos: duplicar, borrar, traer al frente o enviar al fondo. **Estilo**: fuente (agrupadas por categoría), cuerpo, interlineado, color, alineación horizontal y vertical, relleno; negrita, cursiva y color se aplican a la selección si se está escribiendo o a todo el cuadro si no. **Cambios para todo el libro**: aplicar el fondo o la fuente a todas las páginas. **Fondo de página** (detrás o teñido) y casilla para quitar el **marco decorativo** en la página. **Texto original editable**: llama a `texto-editable`, añade los cuadros, oculta el texto original y muestra las fuentes sustituidas; «Volver al texto original» lo deshace. **Vista exacta**: pide al servidor el pliego compuesto, con retardo tras cada cambio. **Teclado**: Supr/Retroceso borra, Ctrl+Z deshace, Ctrl+Y o Ctrl+Mayús+Z rehace, flechas mueven 1 mm (5 mm con Mayús), Esc deselecciona. **Escala** automática para que el pliego llene el espacio (`ResizeObserver`). |
| `PageCanvas.vue`     | Una página a escala de pantalla (píxeles por mm). Capas como en la impresión: fondo «detrás», imagen de la página (encajada sin deformar, dentro del marco de márgenes), tinte con `mix-blend-multiply`, elementos en el mismo marco y folio. Arrastrar y redimensionar con 8 tiradores (el movimiento se divide por la escala de pantalla y la del marco). El cuadro en edición muestra el `RichTextEditor`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `RichTextEditor.vue` | TipTap con StarterKit y TextStyle/Color. Convierte los spans en documento y de vuelta (`lib/richText.ts`) en cada cambio. Expone el editor para que la barra aplique negrita, cursiva o color a la selección. Esc termina la edición.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |

## 5. Otros componentes de libro e imposición

| Archivo                    | Qué hace                                                                                                                                                                                                                           |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `book/PageCard.vue`        | Miniatura de una página en la cuadrícula: imagen, número, folio y diseño a escala usando unidades de contenedor (`cqw`/`cqh`), marco de márgenes y botones al pasar el ratón (quitar, duplicar, blanco antes/después, sin número). |
| `imposition/SheetFace.vue` | Dibujo de una cara de hoja con sus dos medias páginas numeradas (o «blanco»).                                                                                                                                                      |

## 6. Componentes de la aplicación (`components/`)

| Archivo                                                                                                                             | Qué hace                                                                                                                      |
| ----------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `AppShell.vue`, `AppContent.vue`, `AppSidebar.vue`, `AppSidebarHeader.vue`, `AppHeader.vue`                                         | Estructura: barra lateral con navegación (Inicio, Libros, Fuentes, Imposición rápida), cabecera con migas de pan y contenido. |
| `NavMain.vue`, `NavFooter.vue`, `NavUser.vue`, `UserMenuContent.vue`, `UserInfo.vue`                                                | Navegación principal, pie de la barra lateral y menú de usuario (ajustes, cerrar sesión).                                     |
| `WorkspaceSwitcher.vue`                                                                                                             | Selector de espacio en la barra lateral: espacio activo, cambiar a otro, crear una editorial.                                 |
| `ThemeToggle.vue`                                                                                                                   | Botón rápido para alternar claro/oscuro.                                                                                      |
| `AppLogo.vue`, `AppLogoIcon.vue`                                                                                                    | Marca de Noctua (búho sobre un libro, SVG).                                                                                   |
| `Breadcrumbs.vue`, `Heading.vue`, `TextLink.vue`, `InputError.vue`, `AlertError.vue`, `PasswordInput.vue`, `PlaceholderPattern.vue` | Piezas pequeñas de interfaz.                                                                                                  |
| `AppearanceTabs.vue`                                                                                                                | Selector de modo (plantilla original).                                                                                        |
| `DeleteUser.vue`                                                                                                                    | Diálogo para borrar la cuenta con contraseña.                                                                                 |
| `ManageTwoFactor.vue`, `TwoFactorSetupModal.vue`, `TwoFactorRecoveryCodes.vue`                                                      | Activar y gestionar la verificación en dos pasos.                                                                             |
| `ManagePasskeys.vue`, `PasskeyItem.vue`, `PasskeyRegister.vue`, `PasskeyVerify.vue`                                                 | Llaves de acceso (WebAuthn).                                                                                                  |
| `ui/`                                                                                                                               | Componentes base de shadcn-vue (botón, diálogo, input, select, sidebar…). Generados; se modifican poco.                       |

## 7. Layouts (`layouts/`)

| Archivo                                       | Qué hace                                                                                                                                                                                  |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AppLayout.vue` → `app/AppSidebarLayout.vue`  | Layout de la aplicación con barra lateral (`AppHeaderLayout.vue` es la alternativa con cabecera).                                                                                         |
| `AuthLayout.vue` → `auth/AuthBrandLayout.vue` | Pantallas de acceso: panel de marca con el color de acento y ventajas de Noctua, y el formulario. `AuthCardLayout`, `AuthSimpleLayout` y `AuthSplitLayout` son variantes de la plantilla. |
| `settings/Layout.vue`                         | Ajustes con menú lateral: Perfil, Seguridad, Espacio de trabajo, Apariencia.                                                                                                              |

## 8. Composables (`composables/`)

| Archivo               | Qué hace                                                                                                                                                                                                 |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `useFontFaces.ts`     | `loadFontFaces(familias)` registra en el navegador (`FontFace`) las variantes que sirve `/fuentes/{id}/archivo`, **con el mismo nombre que usa Typst**, para que el editor pinte con las fuentes reales. |
| `useThemeSettings.ts` | Modo y acento de la cuenta: aplica al instante en el navegador y guarda en el servidor.                                                                                                                  |
| `useAppearance.ts`    | Modo claro/oscuro/sistema: clase `dark`, cookie y escucha de cambios del sistema.                                                                                                                        |
| `useTwoFactorAuth.ts` | Estado y llamadas de la configuración de 2FA (QR, clave, códigos).                                                                                                                                       |
| `useCurrentUrl.ts`    | Saber si un enlace corresponde a la página actual (resaltar en la navegación).                                                                                                                           |
| `useInitials.ts`      | Iniciales del nombre para los avatares.                                                                                                                                                                  |

## 9. Utilidades (`lib/`)

| Archivo         | Qué hace                                                                                                                                                                                                                                                                                                                |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `richText.ts`   | `spansToDoc` / `docToSpans`: spans ↔ documento TipTap (cada `\n` es un párrafo; une fragmentos contiguos con el mismo estilo). `toHex` normaliza colores `rgb()`. `ptToPx` convierte un cuerpo en puntos a píxeles de pantalla. `cloneData` copia profunda segura con proxies de Vue (`structuredClone` no los acepta). |
| `layout.ts`     | `contentFrame()`: marco de márgenes en espejo, **mismo cálculo** que `BookLayout::frame()` en PHP. `IDENTITY_FRAME`.                                                                                                                                                                                                    |
| `border.ts`     | `DEFAULT_BORDER` y `borderPreviewUrl()`: URL del SVG del marco (lo dibuja el servidor con Typst), usada en las miniaturas, en el editor de pliego y en la galería de diseños.                                                                                                                                           |
| `accent.ts`     | `accentVariables`, `readableOn` y `applyAccent`: **misma lógica** que `AccentPalette` en PHP, para la vista previa al instante.                                                                                                                                                                                         |
| `flashToast.ts` | Muestra como aviso los mensajes flash que manda el servidor.                                                                                                                                                                                                                                                            |
| `utils.ts`      | `cn()` (combinar clases de Tailwind) y `toUrl()`.                                                                                                                                                                                                                                                                       |

> Los cálculos duplicados en PHP y TypeScript (`layout.ts` ↔ `BookLayout`, `accent.ts` ↔ `AccentPalette`) tienen tests en los dos lados con los mismos valores esperados. Si cambias uno, cambia el otro.

## 10. Tipos (`types/`)

| Archivo                              | Qué hace                                                                                                                                                       |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `book.ts`                            | `BookPage`, `PageDesign`, `PageElement` (`TextElement` / `RectElement`), `TextSpan`, `FolioSettings`, `BookLayout`, alineaciones. Reflejan el JSON de `books`. |
| `fonts.ts`                           | `FontFamilyInfo` y sus variantes (para `loadFontFaces` y los selectores).                                                                                      |
| `imposition.ts`                      | Ajustes, impresora, plan y caras de hoja para la imposición.                                                                                                   |
| `auth.ts`                            | `User`, `Workspace` (espacio con rol), `Passkey`.                                                                                                              |
| `navigation.ts`, `ui.ts`, `index.ts` | Elementos de navegación, migas de pan y reexportaciones.                                                                                                       |

## 11. Guía con el murciélago (`components/guide/`)

| Archivo                   | Qué hace                                                                                                                                                                                                                       |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `GuideBat.vue`            | El murciélago de Noctua en SVG animado con CSS: flota batiendo las alas (más rápido cuando habla), parpadea, mueve la boca, inclina la cabeza en cada paso y mira hacia lo que señala. Respeta «reducir movimiento».           |
| `GuideTour.vue`           | La guía en pantalla: ilumina el elemento del paso (`data-guide="…"`) con un foco, coloca al murciélago y el bocadillo al lado, escribe el texto letra a letra y responde al teclado (flechas, Intro, Esc) antes que el editor. |
| `GuideLauncher.vue`       | Botón flotante «¿Te guío?» y apertura automática la primera vez que se entra en cada pantalla.                                                                                                                                 |
| `lib/guide.ts`            | Las guías y sus pasos: inicio, libros, editor del libro, editor de pliego, fuentes e imposición. Para añadir un paso: marcar el elemento con `data-guide` y añadirlo aquí.                                                     |
| `composables/useGuide.ts` | Estado único de la guía (cuál y en qué paso) y qué guías ya se vieron (`localStorage`).                                                                                                                                        |
