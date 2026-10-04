# Documentación de Noctua

Documentación técnica completa del proyecto: qué es, cómo está construido, por qué está construido así y qué hace cada archivo.

| Documento                          | Para qué sirve                                                                                                                                                                       |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [NOCTUA.md](NOCTUA.md)             | Qué es Noctua, para quién es y qué puede hacer (visión de producto, sin tecnicismos).                                                                                                |
| [arquitectura.md](arquitectura.md) | Arquitectura general: capas, patrones, modelo de datos, multiespacio, almacenamiento, flujos principales paso a paso y decisiones de diseño. **Empieza aquí si vas a tocar código.** |
| [backend.md](backend.md)           | Recorrido archivo por archivo de todo lo que corre en el servidor: dominio, acciones, HTTP, modelos, fuentes, consola, configuración, rutas, migraciones, plantilla Typst.           |
| [frontend.md](frontend.md)         | Recorrido archivo por archivo de la interfaz Vue: páginas, componentes, editor de pliego, composables, utilidades y tipos.                                                           |
| [calidad.md](calidad.md)           | Tests (qué cubre cada archivo), análisis estático, formato, integración continua y convenciones de trabajo.                                                                          |
| [operacion.md](operacion.md)       | Instalación, variables de entorno, binarios externos, comandos, dónde se guarda cada archivo en disco y solución de problemas.                                                       |
| [spike-0.md](spike-0.md)           | Resultados de la fase 0: mediciones que validaron Typst y FPDI antes de construir nada.                                                                                              |

## Mapa rápido del repositorio

```
noctua/
├── app/
│   ├── Domain/            Núcleo sin framework: imposición, impresión, libro, tipografía
│   │   ├── Imposition/    Qué página va en cada cara de cada hoja (PHP puro)
│   │   ├── Printing/      Geometría de la hoja, perfil de impresora, PDF (FPDI)
│   │   ├── Book/          Secuencia de páginas, folios, márgenes, diseño, extracción de texto
│   │   └── Typesetting/   Ejecución aislada de Typst y capas de cada página
│   ├── Actions/           Casos de uso (crear libro, imprimir, quitar texto, imponer…)
│   ├── Http/              Controladores, peticiones validadas, middleware
│   ├── Models/            Eloquent: User, Publisher, Book, Font
│   ├── Fonts/             Biblioteca de fuentes: catálogo, subidas, inspección
│   ├── Policies/          Quién puede ver, editar o borrar un libro
│   ├── Console/Commands/  Comandos artisan (imponer, calibración, catálogo de fuentes)
│   ├── Support/           Utilidades (paleta de acento)
│   └── Providers/         Registro de servicios (binarios, singletons)
├── resources/
│   ├── js/                Interfaz Vue 3 + Inertia + TypeScript
│   ├── typst/capas.typ    Plantilla Typst de fondos, textos y folios
│   ├── css/app.css        Tailwind 4 y variables del tema
│   └── views/app.blade.php  Única vista HTML (raíz de Inertia)
├── routes/                web.php (libros, fuentes, imposición), settings.php (cuenta y espacio)
├── database/              Migraciones, factorías, seeders
├── config/noctua.php      Binarios externos y catálogo de fuentes
├── lang/                  Traducciones al español
├── tests/                 Pest (PHP) y Vitest (tests/js)
└── docs/                  Esta documentación
```
