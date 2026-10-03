export type FontFaceInfo = {
    id: number;
    weight: number;
    italic: boolean;
    url: string;
};

export type FontFamilyInfo = {
    /** Nombre con el que Typst y el navegador identifican la familia. */
    family: string;
    /** Nombre para mostrar. */
    label: string;
    category: string;
    own: boolean;
    faces: FontFaceInfo[];
};
