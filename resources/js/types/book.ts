import type { PageFit, PageFitOption } from '@/types/imposition';

export type BookPage = {
    id: string;
    /** Página del PDF de origen, o null si es un blanco. */
    source: number | null;
    folio: boolean;
    design: PageDesign;
};

export type TextSpan = {
    text: string;
    bold: boolean;
    italic: boolean;
    /** null = color del cuadro. */
    color: string | null;
};

export type TextAlign = 'left' | 'center' | 'right' | 'justify';

export type VerticalAlign = 'top' | 'middle' | 'bottom';

type ElementBox = {
    id: string;
    /** mm desde la esquina superior izquierda de la página de origen. */
    x: number;
    y: number;
    width: number;
    height: number;
};

export type TextElement = ElementBox & {
    type: 'text';
    font: string;
    size_pt: number;
    line_height: number;
    color: string;
    align: TextAlign;
    valign: VerticalAlign;
    fill: string | null;
    spans: TextSpan[];
};

export type RectElement = ElementBox & {
    type: 'rect';
    fill: string;
    opacity: number;
};

export type PageElement = TextElement | RectElement;

export type PageBackground = {
    color: string;
    mode: 'behind' | 'tint';
};

export type PageDesign = {
    background: PageBackground | null;
    elements: PageElement[];
    /** La página se usa sin su texto original (convertido en cuadros editables). */
    hide_source_text: boolean;
    /** Esta página no lleva el marco decorativo del libro. */
    hide_border?: boolean;
};

export type FolioPosition = 'bottom-center' | 'bottom-outer' | 'top-outer';

export type FolioSettings = {
    enabled: boolean;
    first_numbered_page: number;
    start_number: number;
    position: FolioPosition;
    font: string;
    size_pt: number;
    margin_mm: number;
};

export type BorderStyle =
    | 'linea'
    | 'doble'
    | 'clasico'
    | 'esquinas'
    | 'deco'
    | 'puntos'
    | 'ondas'
    | 'feston'
    | 'greca'
    | 'estrellas';

/** Marco decorativo de las páginas, con medidas tal como quedan impresas. */
export type PageBorder = {
    style: BorderStyle;
    color: string;
    width_pt: number;
    inset_mm: number;
    unit_mm: number;
};

/** Márgenes en espejo, en mm tal como quedan impresos, y marco decorativo. */
export type BookLayout = {
    margin_top_mm: number;
    margin_bottom_mm: number;
    margin_inside_mm: number;
    margin_outside_mm: number;
    border?: PageBorder | null;
};

export type EditableBook = {
    id: number;
    title: string;
    source_filename: string;
    source_page_count: number;
    width_mm: number;
    height_mm: number;
    pages: BookPage[];
    folios: FolioSettings;
    layout: BookLayout;
};

export type BookSummary = {
    id: number;
    title: string;
    page_count: number;
    source_page_count: number;
    size: string;
    cover_url: string;
    updated_at: string | null;
};

export type Option<T extends string> = { value: T; label: string };

export type PrintDefaults = Partial<{
    sheets_per_signature: number;
    short_last_signature: boolean;
    creep_mm: number;
    front_offset_x: number;
    front_offset_y: number;
    back_offset_x: number;
    back_offset_y: number;
    unprintable_margin_mm: number;
    fold_marks: boolean;
    page_fit: PageFit;
}>;

export type { PageFitOption };
