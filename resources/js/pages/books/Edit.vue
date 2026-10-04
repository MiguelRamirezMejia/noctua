<script setup lang="ts">
import { Form, Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    PencilRuler,
    Printer,
    RotateCcw,
    Save,
    Trash2,
    Undo2,
} from '@lucide/vue';
import { computed, onMounted, ref, toRaw } from 'vue';
import BookController from '@/actions/App/Http/Controllers/BookController';
import SpreadEditor from '@/components/book/editor/SpreadEditor.vue';
import PageCard from '@/components/book/PageCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { borderPreviewUrl, DEFAULT_BORDER } from '@/lib/border';
import { contentFrame } from '@/lib/layout';
import { cloneData } from '@/lib/richText';
import { loadFontFaces } from '@/composables/useFontFaces';
import { Label } from '@/components/ui/label';
import {
    destroy,
    edit,
    index,
    editableText,
    pageImage,
    preview,
    update,
} from '@/routes/books';
import type {
    BookLayout,
    BookPage,
    BorderStyle,
    EditableBook,
    FolioPosition,
    FolioSettings,
    PageDesign,
    Option,
    PageFitOption,
    PrintDefaults,
} from '@/types/book';
import type { FontFamilyInfo } from '@/types/fonts';

const props = defineProps<{
    book: EditableBook;
    thumbnailBase: string;
    fonts: FontFamilyInfo[];
    positions: Option<FolioPosition>[];
    pageFits: PageFitOption[];
    printDefaults: PrintDefaults;
    previewScale: number;
    borderStyles: Option<BorderStyle>[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Libros', href: index() },
        { title: props.book.title, href: edit(props.book.id) },
    ],
});

const form = useForm<{
    title: string;
    pages: BookPage[];
    folios: FolioSettings;
    layout: BookLayout;
}>({
    title: props.book.title,
    pages: cloneData(props.book.pages),
    folios: { ...props.book.folios },
    layout: {
        ...props.book.layout,
        border: props.book.layout.border
            ? { ...props.book.layout.border }
            : null,
    },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

// --- Fuentes para la vista previa ---
onMounted(() => loadFontFaces(props.fonts));

// --- Miniaturas ---
const thumbnailUrl = (source: number | null): string | null =>
    source === null ? null : props.thumbnailBase.replace(/\/0$/, `/${source}`);

/** Si el texto original se reemplazó por cuadros editables, la miniatura va sin él. */
function pageThumbnail(page: BookPage): string | null {
    if (page.source !== null && page.design.hide_source_text) {
        return `${pageImageBase.replace(/\/0\/imagen$/, `/${page.source}/imagen`)}?sin_texto=1`;
    }

    return thumbnailUrl(page.source);
}

// --- Pliegos: la página 1 va sola a la derecha, luego pares izquierda|derecha ---
const spreads = computed(() => {
    const result: { left: number | null; right: number | null }[] = [
        { left: null, right: 0 },
    ];

    for (let i = 1; i < form.pages.length; i += 2) {
        result.push({
            left: i,
            right: i + 1 < form.pages.length ? i + 1 : null,
        });
    }

    return result;
});

// --- Folios ---
const folioTexts = computed(() => {
    const f = form.folios;

    return form.pages.map((page, index) => {
        const position = index + 1;

        if (
            !f.enabled ||
            position < f.first_numbered_page ||
            page.source === null ||
            !page.folio
        ) {
            return null;
        }

        return String(f.start_number + position - f.first_numbered_page);
    });
});

// --- Edición de páginas ---
const emptyDesign = (): PageDesign => ({
    background: null,
    elements: [],
    hide_source_text: false,
    hide_border: false,
});

const uid = (prefix: string) =>
    `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;

function move(index: number, delta: number) {
    const target = index + delta;

    if (target < 0 || target >= form.pages.length) {
        return;
    }

    const [page] = form.pages.splice(index, 1);
    form.pages.splice(target, 0, page);
}

function duplicate(index: number) {
    const page = form.pages[index];
    // Copia profunda: el diseño de la copia se edita por separado.
    form.pages.splice(index + 1, 0, {
        ...cloneData(page),
        id: uid(`p${page.source}`),
    });
}

function insertBlank(index: number) {
    form.pages.splice(index + 1, 0, {
        id: uid('b'),
        source: null,
        folio: true,
        design: emptyDesign(),
    });
}

function remove(index: number) {
    if (form.pages.length > 1) {
        form.pages.splice(index, 1);
    }
}

function toggleFolio(index: number) {
    form.pages[index].folio = !form.pages[index].folio;
}

const removedSources = computed(() => {
    const used = new Set(form.pages.map((p) => p.source));

    return Array.from(
        { length: props.book.source_page_count },
        (_, i) => i + 1,
    ).filter((p) => !used.has(p));
});

/** Devuelve una página quitada a su sitio natural según el orden del PDF. */
function restore(source: number) {
    const before = form.pages.findIndex(
        (p) => p.source !== null && p.source > source,
    );
    const page = {
        id: uid(`p${source}`),
        source,
        folio: true,
        design: emptyDesign(),
    };

    if (before === -1) {
        form.pages.push(page);
    } else {
        form.pages.splice(before, 0, page);
    }
}

function resetToSource() {
    form.pages = Array.from(
        { length: props.book.source_page_count },
        (_, i) => ({
            id: `p${i + 1}`,
            source: i + 1,
            folio: true,
            design: emptyDesign(),
        }),
    );
}

// --- Arrastrar y soltar ---
const dragIndex = ref<number | null>(null);

function dropOn(index: number) {
    if (dragIndex.value === null || dragIndex.value === index) {
        return;
    }

    const [page] = form.pages.splice(dragIndex.value, 1);
    form.pages.splice(index, 0, page);
    dragIndex.value = null;
}

// --- Guardar ---
// --- Márgenes del libro ---
const marginFields = [
    { key: 'margin_top_mm', label: 'Superior' },
    { key: 'margin_bottom_mm', label: 'Inferior' },
    { key: 'margin_inside_mm', label: 'Interior (lomo)' },
    { key: 'margin_outside_mm', label: 'Exterior' },
] as const;

const marginPresets = [
    { label: 'Sin márgenes', mm: 0 },
    { label: 'Estrechos', mm: 8 },
    { label: 'Normales', mm: 15 },
    { label: 'Amplios', mm: 22 },
] as const;

const isPreset = (mm: number) =>
    marginFields.every((f) => form.layout[f.key] === mm);

function applyPreset(mm: number) {
    for (const field of marginFields) {
        form.layout[field.key] = mm;
    }
}

// --- Marco decorativo ---
const borderColors = [
    '#5b3a1e',
    '#1f2937',
    '#7c2d12',
    '#14532d',
    '#1e3a8a',
    '#701a75',
    '#b45309',
    '#9f1239',
];

/** SVG del marco a la escala de impresión, igual para todas las páginas. */
const borderUrl = computed(() =>
    form.layout.border
        ? borderPreviewUrl(
              form.layout.border,
              props.book.width_mm,
              props.book.height_mm,
              props.previewScale,
          )
        : null,
);

/** Muestra de cada diseño con el color y las medidas elegidos. */
const styleSample = (style: BorderStyle) =>
    borderPreviewUrl(
        { ...(form.layout.border ?? DEFAULT_BORDER), style },
        props.book.width_mm,
        props.book.height_mm,
        props.previewScale,
    );

function setBorderEnabled(on: boolean) {
    form.layout.border = on ? { ...DEFAULT_BORDER } : null;
}

function chooseBorderStyle(style: BorderStyle) {
    form.layout.border = { ...(form.layout.border ?? DEFAULT_BORDER), style };
}

const pagesWithoutBorder = computed(
    () => form.pages.filter((p) => p.design.hide_border).length,
);

function showBorderOnAllPages() {
    for (const page of form.pages) {
        page.design.hide_border = false;
    }
}

// --- Editor de pliego ---
const openSpread = ref<number | null>(null);
const pageImageBase = pageImage({ book: props.book.id, page: 0 }).url;
const previewUrl = preview(props.book.id).url;
const editableTextBase = editableText({ book: props.book.id, page: 0 }).url;

function save() {
    form.put(update(props.book.id).url, { preserveScroll: true });
}

function deleteBook() {
    if (
        window.confirm(`¿Eliminar «${props.book.title}»? No se puede deshacer.`)
    ) {
        router.delete(destroy(props.book.id).url);
    }
}

const blankCount = computed(
    () => form.pages.filter((p) => p.source === null).length,
);

const pd = props.printDefaults;
const offsetFields = [
    { name: 'front_offset_x', label: 'Frente X' },
    { name: 'front_offset_y', label: 'Frente Y' },
    { name: 'back_offset_x', label: 'Dorso X' },
    { name: 'back_offset_y', label: 'Dorso Y' },
] as const;
</script>

<template>
    <Head :title="form.title" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="sticky top-0 z-10 -mx-4 flex flex-wrap items-end gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <div class="grid min-w-64 flex-1 gap-1">
                <Label for="title" class="sr-only">Título</Label>
                <Input
                    id="title"
                    v-model="form.title"
                    class="text-lg font-semibold"
                />
                <p class="text-xs text-muted-foreground">
                    {{ form.pages.length }} páginas
                    <template v-if="blankCount">
                        ({{ blankCount }} en blanco)</template
                    >
                    · origen: {{ book.source_filename }},
                    {{ book.source_page_count }} págs.,
                    {{ Math.round(book.width_mm) }} ×
                    {{ Math.round(book.height_mm) }} mm
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    v-if="form.isDirty"
                    class="text-sm text-muted-foreground"
                    role="status"
                    >Cambios sin guardar</span
                >
                <Button
                    variant="outline"
                    :disabled="!form.isDirty || form.processing"
                    @click="form.reset()"
                >
                    <Undo2 /> Descartar
                </Button>
                <Button
                    :disabled="!form.isDirty || form.processing"
                    @click="save"
                >
                    <Save /> {{ form.processing ? 'Guardando…' : 'Guardar' }}
                </Button>
            </div>
        </div>

        <InputError :message="form.errors.pages" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section aria-labelledby="paginas" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="paginas" class="text-lg font-semibold">Páginas</h2>
                    <p class="text-sm text-muted-foreground">
                        Arrastra para reordenar o usa los botones que aparecen
                        al pasar sobre cada página.
                    </p>
                </div>

                <ol
                    class="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-x-8 gap-y-6"
                >
                    <li
                        v-for="(spread, s) in spreads"
                        :key="s"
                        class="group/spread relative grid grid-cols-2"
                        :aria-label="`Pliego ${s + 1}`"
                        @dblclick="openSpread = s"
                    >
                        <template
                            v-for="i in [spread.left, spread.right]"
                            :key="i ?? `vacío-${s}`"
                        >
                            <PageCard
                                v-if="i !== null"
                                :page="form.pages[i]"
                                :position="i + 1"
                                :total="form.pages.length"
                                :thumbnail-url="pageThumbnail(form.pages[i])"
                                :folio-text="folioTexts[i]"
                                :folios="form.folios"
                                :width-mm="book.width_mm"
                                :height-mm="book.height_mm"
                                :print-scale="previewScale"
                                :border-url="
                                    form.pages[i].design.hide_border
                                        ? null
                                        : borderUrl
                                "
                                :frame="
                                    contentFrame(
                                        form.layout,
                                        book.width_mm,
                                        book.height_mm,
                                        previewScale,
                                        i + 1,
                                    )
                                "
                                :dragging="dragIndex === i"
                                @move="(d) => move(i, d)"
                                @duplicate="duplicate(i)"
                                @insert-blank="insertBlank(i)"
                                @toggle-folio="toggleFolio(i)"
                                @remove="remove(i)"
                                @dragstart="dragIndex = i"
                                @dragend="dragIndex = null"
                                @drop="dropOn(i)"
                            />
                            <div v-else aria-hidden="true" />
                        </template>
                        <Button
                            size="sm"
                            class="absolute bottom-7 left-1/2 -translate-x-1/2 opacity-0 shadow transition group-focus-within/spread:opacity-100 group-hover/spread:opacity-100"
                            :aria-label="`Editar el pliego ${s + 1}`"
                            @click="openSpread = s"
                        >
                            <PencilRuler /> Editar
                        </Button>
                    </li>
                </ol>
            </section>

            <aside class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Márgenes del libro</CardTitle>
                        <CardDescription>
                            El contenido de cada página se reduce y se centra
                            dentro de estos márgenes, tal como quedan impresos.
                            Son en espejo: el interior va siempre del lado del
                            lomo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="preset in marginPresets"
                                :key="preset.label"
                                size="sm"
                                :variant="
                                    isPreset(preset.mm) ? 'default' : 'outline'
                                "
                                @click="applyPreset(preset.mm)"
                            >
                                {{ preset.label }}
                            </Button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                v-for="field in marginFields"
                                :key="field.key"
                                class="grid gap-2"
                            >
                                <Label :for="field.key"
                                    >{{ field.label }} (mm)</Label
                                >
                                <Input
                                    :id="field.key"
                                    v-model.number="form.layout[field.key]"
                                    type="number"
                                    min="0"
                                    max="60"
                                    step="0.5"
                                />
                            </div>
                        </div>
                        <InputError
                            :message="
                                form.errors['layout.margin_top_mm'] ??
                                form.errors['layout.margin_bottom_mm'] ??
                                form.errors['layout.margin_inside_mm'] ??
                                form.errors['layout.margin_outside_mm']
                            "
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Marco decorativo</CardTitle>
                        <CardDescription>
                            Un marco alrededor de cada página, en el margen.
                            Puedes quitarlo en páginas concretas (por ejemplo,
                            la portada) desde el editor de pliego.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                :checked="form.layout.border != null"
                                type="checkbox"
                                class="size-4 accent-primary"
                                @change="
                                    setBorderEnabled(
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            Poner marco a las páginas
                        </label>

                        <div
                            class="grid grid-cols-5 gap-2"
                            role="radiogroup"
                            aria-label="Diseño del marco"
                        >
                            <button
                                v-for="style in borderStyles"
                                :key="style.value"
                                type="button"
                                role="radio"
                                :aria-checked="
                                    form.layout.border?.style === style.value
                                "
                                :title="style.label"
                                class="grid gap-1 rounded-md p-1 text-center text-[10px] leading-tight text-muted-foreground transition hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :class="
                                    form.layout.border?.style === style.value
                                        ? 'bg-accent font-medium text-foreground ring-2 ring-primary'
                                        : ''
                                "
                                @click="chooseBorderStyle(style.value)"
                            >
                                <span
                                    class="block w-full rounded-sm border bg-white shadow-xs"
                                    :style="{
                                        aspectRatio: `${book.width_mm} / ${book.height_mm}`,
                                    }"
                                >
                                    <img
                                        :src="styleSample(style.value)"
                                        alt=""
                                        loading="lazy"
                                        class="size-full"
                                    />
                                </span>
                                {{ style.label }}
                            </button>
                        </div>

                        <template v-if="form.layout.border">
                            <div class="grid gap-2">
                                <Label>Color</Label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="color in borderColors"
                                        :key="color"
                                        type="button"
                                        class="size-6 rounded-full border shadow-xs transition hover:scale-110"
                                        :class="
                                            form.layout.border.color === color
                                                ? 'ring-2 ring-primary ring-offset-2 ring-offset-background'
                                                : ''
                                        "
                                        :style="{ backgroundColor: color }"
                                        :aria-label="`Color ${color}`"
                                        @click="
                                            form.layout.border.color = color
                                        "
                                    />
                                    <input
                                        v-model="form.layout.border.color"
                                        type="color"
                                        class="h-7 w-10 cursor-pointer rounded border bg-transparent"
                                        aria-label="Otro color"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="grid gap-2">
                                    <Label for="border-width"
                                        >Grosor (pt)</Label
                                    >
                                    <Input
                                        id="border-width"
                                        v-model.number="
                                            form.layout.border.width_pt
                                        "
                                        type="number"
                                        min="0.2"
                                        max="4"
                                        step="0.1"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="border-inset"
                                        >Al borde (mm)</Label
                                    >
                                    <Input
                                        id="border-inset"
                                        v-model.number="
                                            form.layout.border.inset_mm
                                        "
                                        type="number"
                                        min="2"
                                        max="40"
                                        step="0.5"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="border-unit"
                                        >Adornos (mm)</Label
                                    >
                                    <Input
                                        id="border-unit"
                                        v-model.number="
                                            form.layout.border.unit_mm
                                        "
                                        type="number"
                                        min="3"
                                        max="15"
                                        step="0.5"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="pagesWithoutBorder > 0"
                                class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                            >
                                {{ pagesWithoutBorder }} página(s) sin marco.
                                <Button
                                    size="sm"
                                    variant="link"
                                    class="h-auto p-0"
                                    @click="showBorderOnAllPages"
                                >
                                    Ponerlo en todas
                                </Button>
                            </p>
                        </template>
                        <InputError
                            :message="
                                form.errors['layout.border.width_pt'] ??
                                form.errors['layout.border.inset_mm'] ??
                                form.errors['layout.border.unit_mm'] ??
                                form.errors['layout.border.color'] ??
                                form.errors['layout.border.style']
                            "
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Numeración de páginas</CardTitle>
                        <CardDescription>
                            Los números se estampan al imprimir. Los blancos y
                            las páginas marcadas «sin nº» cuentan, pero no lo
                            muestran.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.folios.enabled"
                                type="checkbox"
                                class="size-4 accent-primary"
                            />
                            Numerar las páginas
                        </label>

                        <fieldset
                            class="grid grid-cols-2 gap-4"
                            :disabled="!form.folios.enabled"
                            :class="form.folios.enabled ? '' : 'opacity-50'"
                        >
                            <div class="grid gap-2">
                                <Label for="first_numbered_page"
                                    >Numerar desde la pág.</Label
                                >
                                <Input
                                    id="first_numbered_page"
                                    v-model.number="
                                        form.folios.first_numbered_page
                                    "
                                    type="number"
                                    min="1"
                                    :max="form.pages.length"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="start_number"
                                    >Empezando en el nº</Label
                                >
                                <Input
                                    id="start_number"
                                    v-model.number="form.folios.start_number"
                                    type="number"
                                    min="0"
                                />
                            </div>
                            <div class="col-span-2 grid gap-2">
                                <Label for="folio_position">Posición</Label>
                                <select
                                    id="folio_position"
                                    v-model="form.folios.position"
                                    :class="selectClass"
                                >
                                    <option
                                        v-for="p in positions"
                                        :key="p.value"
                                        :value="p.value"
                                        class="bg-background"
                                    >
                                        {{ p.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-2 grid gap-2">
                                <Label for="folio_font">Tipo de letra</Label>
                                <select
                                    id="folio_font"
                                    v-model="form.folios.font"
                                    :class="selectClass"
                                    :style="{
                                        fontFamily: `'${form.folios.font}'`,
                                    }"
                                >
                                    <option
                                        v-for="f in fonts"
                                        :key="f.family"
                                        :value="f.family"
                                        class="bg-background"
                                        :style="{ fontFamily: `'${f.family}'` }"
                                    >
                                        {{ f.label }} — 1 2 3 4 5
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="folio_size">Tamaño (pt)</Label>
                                <Input
                                    id="folio_size"
                                    v-model.number="form.folios.size_pt"
                                    type="number"
                                    step="0.5"
                                    min="4"
                                    max="48"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="folio_margin">Margen (mm)</Label>
                                <Input
                                    id="folio_margin"
                                    v-model.number="form.folios.margin_mm"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="60"
                                />
                            </div>
                        </fieldset>
                        <InputError
                            :message="
                                form.errors['folios.first_numbered_page'] ??
                                form.errors['folios.size_pt'] ??
                                form.errors['folios.margin_mm']
                            "
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Páginas quitadas</CardTitle>
                        <CardDescription>
                            Páginas del PDF original que no están en el libro.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <p
                            v-if="removedSources.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Ninguna.
                        </p>
                        <ul v-else class="grid grid-cols-4 gap-2">
                            <li v-for="source in removedSources" :key="source">
                                <button
                                    type="button"
                                    class="group relative block w-full"
                                    :aria-label="`Devolver la página ${source} del PDF`"
                                    @click="restore(source)"
                                >
                                    <img
                                        :src="thumbnailUrl(source) ?? ''"
                                        alt=""
                                        class="w-full border"
                                        loading="lazy"
                                    />
                                    <span
                                        class="absolute inset-0 flex items-center justify-center bg-background/70 text-xs font-medium opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
                                        >Devolver</span
                                    >
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                        >pdf {{ source }}</span
                                    >
                                </button>
                            </li>
                        </ul>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="resetToSource"
                        >
                            <RotateCcw /> Volver al orden original
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Printer class="size-4" /> Imprimir
                        </CardTitle>
                        <CardDescription>
                            Genera el PDF impuesto con el orden y la numeración
                            guardados.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            v-bind="BookController.print.form(book.id)"
                            class="grid gap-4"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <Label for="page_fit">Ajuste de página</Label>
                                <select
                                    id="page_fit"
                                    name="page_fit"
                                    :class="selectClass"
                                >
                                    <option
                                        v-for="fit in pageFits"
                                        :key="fit.value"
                                        :value="fit.value"
                                        class="bg-background"
                                        :selected="
                                            fit.value ===
                                            (pd.page_fit ??
                                                (previewScale < 1
                                                    ? 'fit'
                                                    : 'actual'))
                                        "
                                    >
                                        {{ fit.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="sheets_per_signature"
                                        >Hojas/cuadernillo</Label
                                    >
                                    <select
                                        id="sheets_per_signature"
                                        name="sheets_per_signature"
                                        :class="selectClass"
                                    >
                                        <option
                                            v-for="n in 8"
                                            :key="n"
                                            :value="n"
                                            class="bg-background"
                                            :selected="
                                                n ===
                                                (pd.sheets_per_signature ?? 4)
                                            "
                                        >
                                            {{ n }} ({{ 4 * n }} págs.)
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="creep_mm">Creep (mm)</Label>
                                    <Input
                                        id="creep_mm"
                                        name="creep_mm"
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="2"
                                        :default-value="pd.creep_mm ?? 0"
                                    />
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="short_last_signature"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="short_last_signature"
                                    value="1"
                                    class="size-4 accent-primary"
                                    :checked="pd.short_last_signature ?? true"
                                />
                                Acortar el último cuadernillo
                            </label>
                            <details class="text-sm">
                                <summary class="cursor-pointer font-medium">
                                    Impresora (offsets y márgenes)
                                </summary>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div
                                        v-for="field in offsetFields"
                                        :key="field.name"
                                        class="grid gap-1"
                                    >
                                        <Label :for="field.name"
                                            >{{ field.label }} (mm)</Label
                                        >
                                        <Input
                                            :id="field.name"
                                            :name="field.name"
                                            type="number"
                                            step="0.05"
                                            min="-10"
                                            max="10"
                                            :default-value="pd[field.name] ?? 0"
                                        />
                                    </div>
                                    <div class="grid gap-1">
                                        <Label for="unprintable_margin_mm"
                                            >No imprimible (mm)</Label
                                        >
                                        <Input
                                            id="unprintable_margin_mm"
                                            name="unprintable_margin_mm"
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            max="20"
                                            :default-value="
                                                pd.unprintable_margin_mm ?? 5
                                            "
                                        />
                                    </div>
                                    <label
                                        class="flex items-center gap-2 self-end pb-2"
                                    >
                                        <input
                                            type="hidden"
                                            name="fold_marks"
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            name="fold_marks"
                                            value="1"
                                            class="size-4 accent-primary"
                                            :checked="pd.fold_marks ?? true"
                                        />
                                        Marcas de doblez
                                    </label>
                                </div>
                            </details>

                            <InputError :message="errors.print" />
                            <p
                                v-if="form.isDirty"
                                class="text-sm text-amber-700 dark:text-amber-400"
                            >
                                Guarda los cambios antes de imprimir.
                            </p>
                            <Button
                                type="submit"
                                :disabled="processing || form.isDirty"
                            >
                                <Printer />
                                {{
                                    processing
                                        ? 'Preparando PDF…'
                                        : 'Generar PDF para imprimir'
                                }}
                            </Button>
                        </Form>
                    </CardContent>
                </Card>

                <Button
                    variant="ghost"
                    class="self-start text-destructive"
                    @click="deleteBook"
                >
                    <Trash2 /> Eliminar libro
                </Button>
            </aside>
        </div>
    </div>
    <SpreadEditor
        v-if="openSpread !== null"
        :pages="form.pages"
        :start-spread="openSpread"
        :width-mm="book.width_mm"
        :height-mm="book.height_mm"
        :folios="form.folios"
        :folio-texts="folioTexts"
        :fonts="fonts"
        :print-scale="previewScale"
        :layout="form.layout"
        :border-url="borderUrl"
        :page-image-base="pageImageBase"
        :editable-text-base="editableTextBase"
        :preview-url="previewUrl"
        :dirty="form.isDirty"
        :saving="form.processing"
        @close="openSpread = null"
        @save="save"
    />
</template>
