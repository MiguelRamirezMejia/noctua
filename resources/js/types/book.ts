import type { PageFit, PageFitOption } from '@/types/imposition';

export type BookPage = {
    id: string;
    /** Página del PDF de origen, o null si es un blanco. */
    source: number | null;
    folio: boolean;
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

export type EditableBook = {
    id: number;
    title: string;
    source_filename: string;
    source_page_count: number;
    width_mm: number;
    height_mm: number;
    pages: BookPage[];
    folios: FolioSettings;
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

export type FolioFont = { family: string; url: string | null };

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
