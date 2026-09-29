export type SlotPages = [number | null, number | null];

export type ImpositionDefaults = {
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
};

export type PageFit = 'actual' | 'fit' | 'fill';

export type PageFitOption = { value: PageFit; label: string };

export type RecentImposition = {
    id: string;
    name: string;
    created_at: string;
    page_count: number;
};

export type StoredImposition = {
    id: string;
    original_name: string;
    created_at: string;
    page_count: number;
    settings: {
        sheets_per_signature: number;
        short_last_signature: boolean;
        creep_mm: number;
    };
    printer: {
        front_offset_x: number;
        front_offset_y: number;
        back_offset_x: number;
        back_offset_y: number;
        unprintable_margin_mm: number;
    };
    options: { fold_marks: boolean; page_fit: PageFit };
};

export type ImpositionSummary = {
    page_count: number;
    blank_pages: number;
    signatures: number;
    sheets: number;
    faces: number;
};

export type SignaturePlan = {
    number: number;
    first_page: number;
    last_page: number;
    sheets: {
        index: number;
        front: SlotPages;
        back: SlotPages;
    }[];
};
