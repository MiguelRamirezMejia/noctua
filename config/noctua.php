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
    ],

    'process_timeout' => (int) env('NOCTUA_PROCESS_TIMEOUT', 120),

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
