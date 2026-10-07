<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Binarios externos
    |--------------------------------------------------------------------------
    |
    | Rutas a Typst y a pdftoppm (Poppler). Por defecto se buscan en el PATH;
    | en Windows conviene fijar la ruta absoluta en el .env.
    |
    */

    'binaries' => [
        'typst' => env('NOCTUA_TYPST', 'typst'),
        'pdftoppm' => env('NOCTUA_PDFTOPPM', 'pdftoppm'),
        'pdftotext' => env('NOCTUA_PDFTOTEXT', 'pdftotext'),
        'pdftohtml' => env('NOCTUA_PDFTOHTML', 'pdftohtml'),
        'ghostscript' => env('NOCTUA_GHOSTSCRIPT', 'gs'),
    ],

    'process_timeout' => (int) env('NOCTUA_PROCESS_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Administración
    |--------------------------------------------------------------------------
    |
    | Correos de quienes administran esta instalación (separados por comas):
    | ven el registro de actividad de todas las cuentas.
    |
    */

    'admins' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('NOCTUA_ADMINS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Modo de prueba
    |--------------------------------------------------------------------------
    |
    | Con NOCTUA_DEMO=true la portada ofrece «Probar Noctua»: una cuenta
    | temporal, sin registro, que se borra con todo lo suyo al caducar o al
    | cerrar sesión.
    |
    */

    'demo' => [
        'enabled' => (bool) env('NOCTUA_DEMO', false),
        'ttl_hours' => (int) env('NOCTUA_DEMO_TTL_HOURS', 6),
        'max_books' => (int) env('NOCTUA_DEMO_MAX_BOOKS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de fuentes
    |--------------------------------------------------------------------------
    |
    | Familias libres (SIL Open Font License) que `php artisan noctua:fonts:install`
    | descarga de Google Fonts en regular, negrita, cursiva y negrita cursiva
    | (las variantes que existan). Libertinus Serif viene incluida en Typst.
    |
    */

    'font_catalog' => [
        // Serif para texto de libro
        'EB Garamond' => 'Serif',
        'Cormorant Garamond' => 'Serif',
        'Crimson Pro' => 'Serif',
        'Lora' => 'Serif',
        'Libre Baskerville' => 'Serif',
        'Merriweather' => 'Serif',
        'Source Serif 4' => 'Serif',
        'Literata' => 'Serif',
        'Alegreya' => 'Serif',
        'Spectral' => 'Serif',
        'PT Serif' => 'Serif',
        'Noto Serif' => 'Serif',
        'Playfair Display' => 'Serif',
        'Cardo' => 'Serif',
        // Sans
        'Nunito' => 'Sans',
        'Open Sans' => 'Sans',
        'Lato' => 'Sans',
        'Montserrat' => 'Sans',
        'Raleway' => 'Sans',
        'Poppins' => 'Sans',
        'Work Sans' => 'Sans',
        'Source Sans 3' => 'Sans',
        'Atkinson Hyperlegible' => 'Sans',
        'Quicksand' => 'Sans',
        'Comfortaa' => 'Sans',
        'Fredoka' => 'Sans',
        // Lectura infantil y manuscritas
        'Andika' => 'Infantil y manuscrita',
        'Baloo 2' => 'Infantil y manuscrita',
        'Patrick Hand' => 'Infantil y manuscrita',
        'Schoolbell' => 'Infantil y manuscrita',
        'Kalam' => 'Infantil y manuscrita',
        'Caveat' => 'Infantil y manuscrita',
        'Indie Flower' => 'Infantil y manuscrita',
        'Gloria Hallelujah' => 'Infantil y manuscrita',
        'Shadows Into Light' => 'Infantil y manuscrita',
        'Dancing Script' => 'Infantil y manuscrita',
        'Great Vibes' => 'Infantil y manuscrita',
        // Títulos y decorativas
        'Cinzel' => 'Títulos',
        'Amatic SC' => 'Títulos',
        'Pacifico' => 'Títulos',
        'Lobster' => 'Títulos',
        'Bangers' => 'Títulos',
        'Chewy' => 'Títulos',
        'Luckiest Guy' => 'Títulos',
    ],

];
