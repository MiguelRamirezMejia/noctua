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
    | Fuentes para folios
    |--------------------------------------------------------------------------
    |
    | Familias disponibles para numerar páginas. Los archivos están en
    | public/fonts/folios (también los usa el navegador para la vista previa).
    | Libertinus Serif viene incluida en Typst.
    |
    */

    'folio_fonts' => [
        'Libertinus Serif' => null,
        'EB Garamond' => 'EBGaramond-Regular.ttf',
        'Crimson Pro' => 'CrimsonPro-Regular.ttf',
        'Lora' => 'Lora-Regular.ttf',
        'Nunito' => 'Nunito-Regular.ttf',
        'Andika' => 'Andika-Regular.ttf',
    ],

];
