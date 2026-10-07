/**
 * Guías del murciélago: una por pantalla. Cada paso señala un elemento marcado con
 * `data-guide="…"`; si no lo encuentra, el murciélago habla en el centro.
 */
export type GuideStep = {
    /** Valor de data-guide del elemento a señalar. */
    target?: string;
    title: string;
    text: string;
};

export type Guide = {
    id: string;
    title: string;
    steps: GuideStep[];
};

export const guides: Record<string, Guide> = {
    inicio: {
        id: 'inicio',
        title: 'Bienvenida',
        steps: [
            {
                title: '¡Hola! Soy el murciélago de Noctua 🦇',
                text: 'Vivo de noche entre libros y te voy a enseñar a convertir el PDF de tu libro en un libro de verdad, impreso en casa y cosido a mano.',
            },
            {
                target: 'menu',
                title: 'Todo está aquí',
                text: 'En el menú tienes tus libros, las fuentes y la imposición rápida. Puedes volver a llamarme cuando quieras con el botón del murciélago, abajo a la derecha.',
            },
            {
                target: 'primeros-pasos',
                title: 'Tus primeros pasos',
                text: 'Esta lista se marca sola a medida que avanzas: subir un libro, editarlo e imprimirlo.',
            },
            {
                target: 'nav-libros',
                title: 'Empecemos',
                text: 'Ve a «Libros» y sube tu PDF. Allí te sigo explicando.',
            },
        ],
    },
    libros: {
        id: 'libros',
        title: 'Tus libros',
        steps: [
            {
                target: 'subir-libro',
                title: 'Sube tu PDF',
                text: 'Arrastra aquí el PDF de tu libro (de Canva, Word, InDesign…) o pulsa para elegirlo. El original nunca se modifica: todo lo que hagas se aplica al imprimir.',
            },
            {
                target: 'lista-libros',
                title: 'Tu estantería',
                text: 'Aquí aparecen tus libros. Pulsa uno para abrirlo y empezar a editarlo.',
            },
        ],
    },
    editor: {
        id: 'editor',
        title: 'El editor del libro',
        steps: [
            {
                title: 'Tu libro, abierto',
                text: 'Esta es la mesa de trabajo. Te enseño lo importante en un minuto.',
            },
            {
                target: 'paginas',
                title: 'Las páginas, en pliegos',
                text: 'Ves el libro como quedará abierto: la página 1 sola a la derecha y luego de dos en dos. Arrastra una página para moverla. Al pasar el ratón aparecen botones para quitarla, duplicarla, añadir un blanco o quitarle el número.',
            },
            {
                target: 'editar-pliego',
                title: 'Editar sobre el libro abierto',
                text: 'Pasa el ratón por un pliego y pulsa «Editar» (o haz doble clic). Ahí cambias textos, tipos de letra, colores y fondos de cada página.',
            },
            {
                target: 'margenes',
                title: 'Márgenes',
                text: 'Deja aire alrededor del contenido. Son en espejo: el margen interior queda siempre del lado del lomo, donde se cose.',
            },
            {
                target: 'marco',
                title: 'Un marco bonito',
                text: 'Elige entre diez marcos decorativos: greca griega, festón, estrellas, art déco… con tu color y tu grosor.',
            },
            {
                target: 'numeracion',
                title: 'Números de página',
                text: 'Decide desde qué página se numera, con qué número empieza y dónde va. Los blancos cuentan, pero no muestran número.',
            },
            {
                target: 'imprimir',
                title: 'Imprimir por cuadernillos',
                text: 'Cuando esté listo, genera el PDF para imprimir. Imprime a tamaño real, a doble cara volteando por el borde corto, dobla cada hoja y cose. ¡Ya tienes libro!',
            },
            {
                target: 'guardar',
                title: 'No olvides guardar',
                text: 'Los cambios no se guardan solos: pulsa «Guardar» cuando termines. ¡Feliz edición!',
            },
        ],
    },
    pliego: {
        id: 'pliego',
        title: 'El editor de pliego',
        steps: [
            {
                title: 'Dos páginas, en grande',
                text: 'Aquí editas el libro abierto tal cual. Haz clic en algo para seleccionarlo y doble clic en un texto para escribir.',
            },
            {
                target: 'anadir',
                title: 'Añade cosas',
                text: 'Pon cuadros de texto nuevos o rectángulos de color (también sirven para tapar algo del original).',
            },
            {
                target: 'inspector',
                title: 'El panel de la derecha',
                text: 'Cambia aquí el fondo de la página y, si seleccionas un texto, su fuente, tamaño, color y alineación. Puedes poner palabras sueltas en negrita o de otro color.',
            },
            {
                target: 'texto-editable',
                title: 'Edita el texto original',
                text: 'Este botón convierte el texto del PDF en cuadros que puedes cambiar: palabras, tipo de letra, tamaño… También funciona con libros escaneados.',
            },
            {
                target: 'vista-exacta',
                title: 'Vista exacta',
                text: 'Muestra el pliego exactamente como saldrá impreso. Úsala para comprobar antes de imprimir.',
            },
            {
                target: 'guardar-pliego',
                title: 'Guarda',
                text: 'Ctrl+Z deshace, las flechas mueven lo seleccionado y Supr lo borra. Y cuando termines, guarda.',
            },
        ],
    },
    fuentes: {
        id: 'fuentes',
        title: 'Fuentes',
        steps: [
            {
                target: 'subir-fuentes',
                title: 'Tus propias letras',
                text: 'Sube tus fuentes en TTF u OTF. Las verás en todos los selectores de letra de tus libros.',
            },
            {
                target: 'catalogo',
                title: 'Más de 40 fuentes libres',
                text: 'Ya tienes un catálogo de fuentes para libros, infantiles, manuscritas y de títulos, listas para usar.',
            },
        ],
    },
    imposicion: {
        id: 'imposicion',
        title: 'Imposición rápida',
        steps: [
            {
                title: 'Imposición rápida',
                text: 'Aquí conviertes cualquier PDF en hojas listas para doblar en cuadernillos, sin crear un libro.',
            },
            {
                target: 'calibracion',
                title: 'Conoce tu impresora',
                text: 'Imprime una vez la hoja de calibración: con ella sabrás cuánto se desvía tu impresora y Noctua lo compensará.',
            },
        ],
    },
};

/** Pantalla (componente de Inertia) → guía que le corresponde. */
export const guideForPage: Record<string, string> = {
    Dashboard: 'inicio',
    'books/Index': 'libros',
    'books/Edit': 'editor',
    'fonts/Index': 'fuentes',
    'imposition/Create': 'imposicion',
};
