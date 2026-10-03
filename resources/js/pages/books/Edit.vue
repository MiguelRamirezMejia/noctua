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
import { cloneData } from '@/lib/richText';
import { loadFontFaces } from '@/composables/useFontFaces';
import { Label } from '@/components/ui/label';
import {
    destroy,
    edit,
    index,
    pageImage,
    preview,
    update,
} from '@/routes/books';
import type {
    BookPage,
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
}>({
    title: props.book.title,
    pages: cloneData(props.book.pages),
    folios: { ...props.book.folios },
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

// --- Fuentes para la vista previa ---
onMounted(() => loadFontFaces(props.fonts));

// --- Miniaturas ---
const thumbnailUrl = (source: number | null): string | null =>
    source === null ? null : props.thumbnailBase.replace(/\/0$/, `/${source}`);

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
const emptyDesign = (): PageDesign => ({ background: null, elements: [] });

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
// --- Editor de pliego ---
const openSpread = ref<number | null>(null);
const pageImageBase = pageImage({ book: props.book.id, page: 0 }).url;
const previewUrl = preview(props.book.id).url;

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
                                :thumbnail-url="
                                    thumbnailUrl(form.pages[i].source)
                                "
                                :folio-text="folioTexts[i]"
                                :folios="form.folios"
                                :width-mm="book.width_mm"
                                :height-mm="book.height_mm"
                                :print-scale="previewScale"
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
        :page-image-base="pageImageBase"
        :preview-url="previewUrl"
        :dirty="form.isDirty"
        :saving="form.processing"
        @close="openSpread = null"
        @save="save"
    />
</template>
