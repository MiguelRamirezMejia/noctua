<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    ArrowDownToLine,
    ArrowUpToLine,
    Bold,
    ChevronLeft,
    ChevronRight,
    Copy,
    Eye,
    Italic,
    Redo2,
    Save,
    Square,
    Trash2,
    Type,
    Undo2,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    toRaw,
    watch,
} from 'vue';
import PageCanvas from '@/components/book/editor/PageCanvas.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cloneData, toHex } from '@/lib/richText';
import type {
    BookPage,
    FolioSettings,
    PageDesign,
    PageElement,
    TextAlign,
    TextElement,
    VerticalAlign,
} from '@/types/book';
import type { FontFamilyInfo } from '@/types/fonts';

const props = defineProps<{
    pages: BookPage[];
    startSpread: number;
    widthMm: number;
    heightMm: number;
    folios: FolioSettings;
    folioTexts: (string | null)[];
    fonts: FontFamilyInfo[];
    printScale: number;
    pageImageBase: string;
    previewUrl: string;
    dirty: boolean;
    saving: boolean;
}>();

const emit = defineEmits<{
    close: [];
    save: [];
}>();

// --- Pliegos ---
const spreadCount = computed(() => Math.floor(props.pages.length / 2) + 1);
const spread = ref(Math.min(props.startSpread, spreadCount.value - 1));
const positions = computed<[number | null, number | null]>(() => {
    if (spread.value === 0) {
        return [null, 0];
    }

    const left = 2 * spread.value - 1;

    return [left, left + 1 < props.pages.length ? left + 1 : null];
});
const spreadLabel = computed(() => {
    const shown = positions.value.filter((p): p is number => p !== null);

    return shown.length === 1
        ? `Página ${shown[0] + 1}`
        : `Páginas ${shown[0] + 1}–${shown[1] + 1}`;
});

// --- Selección ---
const activeIndex = ref<number>(positions.value[1] ?? positions.value[0] ?? 0);
const selectedId = ref<string | null>(null);
const editingId = ref<string | null>(null);
const editor = shallowRef<Editor | null>(null);

watch(spread, () => {
    selectedId.value = null;
    editingId.value = null;
    activeIndex.value = positions.value[1] ?? positions.value[0] ?? 0;
    resetHistory();
});

const activePage = computed(() => props.pages[activeIndex.value]);
const selected = computed<PageElement | null>(
    () =>
        activePage.value?.design.elements.find(
            (e) => e.id === selectedId.value,
        ) ?? null,
);
const selectedText = computed<TextElement | null>(() =>
    selected.value?.type === 'text' ? selected.value : null,
);

function select(index: number, id: string | null) {
    activeIndex.value = index;
    selectedId.value = id;

    if (editingId.value !== id) {
        editingId.value = null;
        editor.value = null;
    }
}

function startEditing(index: number, id: string) {
    select(index, id);
    editingId.value = id;
}

function stopEditing() {
    editingId.value = null;
    editor.value = null;
}

// --- Historial (deshacer / rehacer) del pliego abierto ---
const history = ref<string[]>([]);
const future = ref<string[]>([]);
let applying = false;
let snapshotTimer: ReturnType<typeof setTimeout> | undefined;

const snapshot = () =>
    JSON.stringify(
        positions.value.map((p) =>
            p === null ? null : toRaw(props.pages[p].design),
        ),
    );

function resetHistory() {
    history.value = [snapshot()];
    future.value = [];
}

function record() {
    if (applying) {
        return;
    }

    clearTimeout(snapshotTimer);
    snapshotTimer = setTimeout(() => {
        const current = snapshot();

        if (history.value[history.value.length - 1] !== current) {
            history.value.push(current);
            future.value = [];
        }
    }, 300);
}

function apply(state: string) {
    applying = true;
    const designs = JSON.parse(state) as (PageDesign | null)[];
    positions.value.forEach((p, i) => {
        const design = designs[i];

        if (p !== null && design) {
            props.pages[p].design = design;
        }
    });
    selectedId.value = null;
    stopEditing();
    void nextTick(() => (applying = false));
}

function undo() {
    if (history.value.length < 2) {
        return;
    }

    future.value.push(history.value.pop()!);
    apply(history.value[history.value.length - 1]);
}

function redo() {
    const next = future.value.pop();

    if (next) {
        history.value.push(next);
        apply(next);
    }
}

watch(
    () =>
        positions.value.map((p) => (p === null ? null : props.pages[p].design)),
    record,
    { deep: true },
);

// --- Crear y modificar elementos ---
const round = (n: number) => Math.round(n * 10) / 10;

const uid = () =>
    `e-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 6)}`;

const defaultFont = computed(
    () =>
        props.fonts.find((f) => f.family === props.folios.font)?.family ??
        props.fonts[0]?.family ??
        'Libertinus Serif',
);

function addText() {
    const width = props.widthMm * 0.7;
    const element: TextElement = {
        id: uid(),
        type: 'text',
        x: round((props.widthMm - width) / 2),
        y: round(props.heightMm * 0.4),
        width: round(width),
        height: 25,
        font: defaultFont.value,
        size_pt: 16,
        line_height: 1.3,
        color: '#000000',
        align: 'center',
        valign: 'top',
        fill: null,
        spans: [
            { text: 'Escribe aquí', bold: false, italic: false, color: null },
        ],
    };
    activePage.value.design.elements.push(element);
    startEditing(activeIndex.value, element.id);
}

function addRect() {
    const element: PageElement = {
        id: uid(),
        type: 'rect',
        x: round(props.widthMm * 0.3),
        y: round(props.heightMm * 0.3),
        width: round(props.widthMm * 0.4),
        height: 30,
        fill: '#ffffff',
        opacity: 1,
    };
    activePage.value.design.elements.push(element);
    select(activeIndex.value, element.id);
}

function removeSelected() {
    const elements = activePage.value.design.elements;
    const i = elements.findIndex((e) => e.id === selectedId.value);

    if (i >= 0) {
        elements.splice(i, 1);
        selectedId.value = null;
        stopEditing();
    }
}

function duplicateSelected() {
    if (!selected.value) {
        return;
    }

    const copy = { ...cloneData(selected.value), id: uid() };
    copy.x += 5;
    copy.y += 5;
    activePage.value.design.elements.push(copy);
    select(activeIndex.value, copy.id);
}

function reorder(toFront: boolean) {
    const elements = activePage.value.design.elements;
    const i = elements.findIndex((e) => e.id === selectedId.value);

    if (i < 0) {
        return;
    }

    const [el] = elements.splice(i, 1);

    if (toFront) {
        elements.push(el);
    } else {
        elements.unshift(el);
    }
}

// --- Estilo de texto: a la selección si se está escribiendo, si no a todo el cuadro ---
function toggleStyle(mark: 'bold' | 'italic') {
    const el = selectedText.value;

    if (!el) {
        return;
    }

    if (editor.value && editingId.value === el.id) {
        const chain = editor.value.chain().focus();
        void (
            mark === 'bold' ? chain.toggleBold() : chain.toggleItalic()
        ).run();

        return;
    }

    const all = el.spans.every((s) => s[mark]);
    el.spans.forEach((s) => (s[mark] = !all));
}

function setTextColor(color: string) {
    const el = selectedText.value;

    if (!el) {
        return;
    }

    if (
        editor.value &&
        editingId.value === el.id &&
        !editor.value.state.selection.empty
    ) {
        editor.value.chain().focus().setColor(color).run();

        return;
    }

    el.color = color;
    el.spans.forEach((s) => (s.color = null));
}

const isActive = (mark: 'bold' | 'italic') => {
    const el = selectedText.value;

    if (!el) {
        return false;
    }

    if (editor.value && editingId.value === el.id) {
        return editor.value.isActive(mark);
    }

    return el.spans.length > 0 && el.spans.every((s) => s[mark]);
};

const alignOptions: {
    value: TextAlign;
    icon: typeof AlignLeft;
    label: string;
}[] = [
    { value: 'left', icon: AlignLeft, label: 'Izquierda' },
    { value: 'center', icon: AlignCenter, label: 'Centro' },
    { value: 'right', icon: AlignRight, label: 'Derecha' },
    { value: 'justify', icon: AlignJustify, label: 'Justificado' },
];
const valignOptions: { value: VerticalAlign; label: string }[] = [
    { value: 'top', label: 'Arriba' },
    { value: 'middle', label: 'Centro' },
    { value: 'bottom', label: 'Abajo' },
];

const fontGroups = computed(() => {
    const groups = new Map<string, FontFamilyInfo[]>();

    for (const font of props.fonts) {
        const list = groups.get(font.category) ?? [];
        list.push(font);
        groups.set(font.category, list);
    }

    return [...groups.entries()];
});

// --- Fondo de página ---
function setBackground(color: string | null) {
    const design = activePage.value.design;

    design.background =
        color === null
            ? null
            : { color, mode: design.background?.mode ?? 'behind' };
}

// --- Teclado ---
function onKeydown(event: KeyboardEvent) {
    if (editingId.value) {
        return;
    }

    const target = event.target as HTMLElement | null;
    if (target && ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)) {
        return;
    }

    const ctrl = event.ctrlKey || event.metaKey;

    if (ctrl && event.key.toLowerCase() === 'z') {
        event.preventDefault();
        if (event.shiftKey) {
            redo();
        } else {
            undo();
        }
    } else if (ctrl && event.key.toLowerCase() === 'y') {
        event.preventDefault();
        redo();
    } else if (
        (event.key === 'Delete' || event.key === 'Backspace') &&
        selected.value
    ) {
        event.preventDefault();
        removeSelected();
    } else if (event.key.startsWith('Arrow') && selected.value) {
        event.preventDefault();
        const step = event.shiftKey ? 5 : 1;
        const el = selected.value;
        if (event.key === 'ArrowLeft') el.x -= step;
        if (event.key === 'ArrowRight') el.x += step;
        if (event.key === 'ArrowUp') el.y -= step;
        if (event.key === 'ArrowDown') el.y += step;
    } else if (event.key === 'Escape') {
        if (selectedId.value) {
            selectedId.value = null;
        } else {
            emit('close');
        }
    } else if (event.key === 'PageDown') {
        spread.value = Math.min(spreadCount.value - 1, spread.value + 1);
    } else if (event.key === 'PageUp') {
        spread.value = Math.max(0, spread.value - 1);
    }
}

// --- Escala: el pliego ocupa el espacio disponible ---
const stage = ref<HTMLElement | null>(null);
const scale = ref(2);
let observer: ResizeObserver | undefined;

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    resetHistory();

    observer = new ResizeObserver(([entry]) => {
        const { width, height } = entry.contentRect;
        scale.value = Math.max(
            0.5,
            Math.min(
                (width - 48) / (2 * props.widthMm),
                (height - 56) / props.heightMm,
            ),
        );
    });

    if (stage.value) {
        observer.observe(stage.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    observer?.disconnect();
    clearTimeout(snapshotTimer);
    clearTimeout(previewTimer);
});

const imageUrl = (page: BookPage) =>
    page.source === null
        ? null
        : props.pageImageBase.replace(/\/0\/imagen$/, `/${page.source}/imagen`);

// --- Vista exacta: el pliego compuesto por el servidor, tal como se imprimirá ---
const exact = ref(false);
const previewImages = ref<Record<number, string>>({});
const previewLoading = ref(false);
const previewError = ref<string | null>(null);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function loadPreview() {
    previewLoading.value = true;
    previewError.value = null;
    const shown = positions.value.filter((p): p is number => p !== null);

    try {
        const response = await fetch(props.previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({
                pages: props.pages,
                folios: props.folios,
                positions: shown.map((p) => p + 1),
            }),
        });
        const body = (await response.json()) as {
            images?: Record<number, string>;
            message?: string;
        };

        if (!response.ok) {
            throw new Error(
                body.message ?? 'No se pudo generar la vista exacta.',
            );
        }

        previewImages.value = body.images ?? {};
    } catch (error) {
        previewError.value =
            error instanceof Error ? error.message : 'Error desconocido.';
    } finally {
        previewLoading.value = false;
    }
}

function schedulePreview() {
    if (!exact.value) {
        return;
    }

    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => void loadPreview(), 600);
}

watch(exact, (on) => {
    if (on) {
        selectedId.value = null;
        stopEditing();
        void loadPreview();
    } else {
        previewImages.value = {};
    }
});
watch(spread, schedulePreview);
watch(
    () => [
        props.folios,
        positions.value.map((p) => (p === null ? null : props.pages[p])),
    ],
    schedulePreview,
    { deep: true },
);
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex flex-col bg-muted"
        role="dialog"
        aria-modal="true"
        :aria-label="`Editor de pliego: ${spreadLabel}`"
    >
        <header
            class="flex flex-wrap items-center gap-2 border-b bg-background px-4 py-2"
        >
            <Button
                variant="ghost"
                size="icon"
                aria-label="Cerrar el editor"
                @click="emit('close')"
            >
                <X />
            </Button>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Pliego anterior"
                    :disabled="spread === 0"
                    @click="spread--"
                >
                    <ChevronLeft />
                </Button>
                <span
                    class="min-w-32 text-center text-sm font-medium tabular-nums"
                >
                    {{ spreadLabel }}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Pliego siguiente"
                    :disabled="spread >= spreadCount - 1"
                    @click="spread++"
                >
                    <ChevronRight />
                </Button>
            </div>

            <div class="mx-2 h-6 w-px bg-border" />

            <Button
                variant="outline"
                size="sm"
                :disabled="exact"
                @click="addText"
            >
                <Type /> Texto
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="exact"
                @click="addRect"
            >
                <Square /> Rectángulo
            </Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Deshacer"
                title="Deshacer (Ctrl+Z)"
                :disabled="history.length < 2 || exact"
                @click="undo"
            >
                <Undo2 />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Rehacer"
                title="Rehacer (Ctrl+Y)"
                :disabled="future.length === 0 || exact"
                @click="redo"
            >
                <Redo2 />
            </Button>

            <div class="ml-auto flex items-center gap-2">
                <Button
                    :variant="exact ? 'default' : 'outline'"
                    size="sm"
                    :aria-pressed="exact"
                    @click="exact = !exact"
                >
                    <Eye /> Vista exacta
                </Button>
                <span v-if="dirty" class="text-sm text-muted-foreground"
                    >Sin guardar</span
                >
                <Button
                    size="sm"
                    :disabled="!dirty || saving"
                    @click="emit('save')"
                >
                    <Save /> {{ saving ? 'Guardando…' : 'Guardar' }}
                </Button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <main
                ref="stage"
                class="relative flex min-w-0 flex-1 items-center justify-center overflow-auto p-6"
            >
                <div class="flex items-start">
                    <template v-for="(p, side) in positions" :key="side">
                        <div
                            v-if="p === null"
                            :style="{
                                width: `${widthMm * scale}px`,
                                height: `${heightMm * scale}px`,
                            }"
                        />
                        <figure v-else class="flex flex-col items-center gap-2">
                            <img
                                v-if="exact && previewImages[p + 1]"
                                :src="previewImages[p + 1]"
                                :alt="`Vista exacta de la página ${p + 1}`"
                                class="bg-white shadow-lg"
                                :style="{
                                    width: `${widthMm * scale}px`,
                                    height: `${heightMm * scale}px`,
                                }"
                            />
                            <PageCanvas
                                v-else
                                :page="pages[p]"
                                :position="p + 1"
                                :width-mm="widthMm"
                                :height-mm="heightMm"
                                :scale="scale"
                                :image-url="imageUrl(pages[p])"
                                :folio-text="folioTexts[p]"
                                :folios="folios"
                                :print-scale="printScale"
                                :selected-id="
                                    activeIndex === p ? selectedId : null
                                "
                                :editing-id="
                                    activeIndex === p ? editingId : null
                                "
                                :active="activeIndex === p && !selectedId"
                                @select="(id) => select(p, id)"
                                @edit="(id) => startEditing(p, id)"
                                @stop-editing="stopEditing"
                                @editor-ready="(e) => (editor = e)"
                                @changed="record"
                            />
                            <figcaption
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ p + 1 }}
                            </figcaption>
                        </figure>
                    </template>
                </div>

                <p
                    v-if="exact && (previewLoading || previewError)"
                    class="absolute top-3 left-1/2 -translate-x-1/2 rounded-md bg-background px-3 py-1 text-sm shadow"
                    :class="
                        previewError
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                    role="status"
                >
                    {{ previewError ?? 'Componiendo…' }}
                </p>
            </main>

            <aside
                class="w-80 shrink-0 overflow-y-auto border-l bg-background p-4 text-sm"
                aria-label="Propiedades"
            >
                <p v-if="exact" class="text-muted-foreground">
                    Así saldrá impreso este pliego. Desactiva «Vista exacta»
                    para seguir editando.
                </p>

                <!-- Cuadro de texto -->
                <div v-else-if="selectedText" class="grid gap-4">
                    <h2 class="font-semibold">Texto</h2>
                    <p class="-mt-2 text-xs text-muted-foreground">
                        Doble clic en el cuadro para escribir. Selecciona
                        palabras para ponerlas en negrita, cursiva o de otro
                        color.
                    </p>

                    <div class="flex gap-1">
                        <Button
                            :variant="isActive('bold') ? 'default' : 'outline'"
                            size="icon"
                            aria-label="Negrita"
                            :aria-pressed="isActive('bold')"
                            @mousedown.prevent
                            @click="toggleStyle('bold')"
                        >
                            <Bold />
                        </Button>
                        <Button
                            :variant="
                                isActive('italic') ? 'default' : 'outline'
                            "
                            size="icon"
                            aria-label="Cursiva"
                            :aria-pressed="isActive('italic')"
                            @mousedown.prevent
                            @click="toggleStyle('italic')"
                        >
                            <Italic />
                        </Button>
                        <label
                            class="ml-2 flex items-center gap-2"
                            @mousedown.stop
                        >
                            <span>Color</span>
                            <input
                                type="color"
                                class="h-9 w-12 cursor-pointer rounded border"
                                :value="selectedText.color"
                                aria-label="Color del texto"
                                @input="
                                    setTextColor(
                                        toHex(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                    )
                                "
                            />
                        </label>
                    </div>

                    <div class="grid gap-2">
                        <Label for="el-font">Tipo de letra</Label>
                        <select
                            id="el-font"
                            v-model="selectedText.font"
                            class="h-9 rounded-md border border-input bg-transparent px-2"
                            :style="{ fontFamily: `'${selectedText.font}'` }"
                        >
                            <optgroup
                                v-for="[category, list] in fontGroups"
                                :key="category"
                                :label="category"
                            >
                                <option
                                    v-for="f in list"
                                    :key="f.family"
                                    :value="f.family"
                                    class="bg-background"
                                    :style="{ fontFamily: `'${f.family}'` }"
                                >
                                    {{ f.label }}
                                </option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1">
                            <Label for="el-size">Tamaño (pt)</Label>
                            <Input
                                id="el-size"
                                v-model.number="selectedText.size_pt"
                                type="number"
                                min="1"
                                max="400"
                                step="0.5"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="el-lh">Interlineado</Label>
                            <Input
                                id="el-lh"
                                v-model.number="selectedText.line_height"
                                type="number"
                                min="0.5"
                                max="5"
                                step="0.05"
                            />
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-muted-foreground">
                        Impreso a ≈
                        {{ (selectedText.size_pt * printScale).toFixed(1) }} pt
                        (la página se ajusta al
                        {{ Math.round(printScale * 100) }} %).
                    </p>

                    <div class="grid gap-2">
                        <span>Alineación</span>
                        <div class="flex gap-1">
                            <Button
                                v-for="a in alignOptions"
                                :key="a.value"
                                :variant="
                                    selectedText.align === a.value
                                        ? 'default'
                                        : 'outline'
                                "
                                size="icon"
                                :aria-label="a.label"
                                :aria-pressed="selectedText.align === a.value"
                                @click="selectedText.align = a.value"
                            >
                                <component :is="a.icon" />
                            </Button>
                        </div>
                        <div class="flex gap-1">
                            <Button
                                v-for="v in valignOptions"
                                :key="v.value"
                                :variant="
                                    selectedText.valign === v.value
                                        ? 'default'
                                        : 'outline'
                                "
                                size="sm"
                                :aria-pressed="selectedText.valign === v.value"
                                @click="selectedText.valign = v.value"
                            >
                                {{ v.label }}
                            </Button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            class="size-4 accent-primary"
                            :checked="selectedText.fill !== null"
                            @change="
                                selectedText.fill = (
                                    $event.target as HTMLInputElement
                                ).checked
                                    ? '#ffffff'
                                    : null
                            "
                        />
                        Fondo del cuadro (para tapar lo de debajo)
                    </label>
                    <input
                        v-if="selectedText.fill !== null"
                        v-model="selectedText.fill"
                        type="color"
                        class="h-9 w-full cursor-pointer rounded border"
                        aria-label="Color de fondo del cuadro"
                    />
                </div>

                <!-- Rectángulo -->
                <div v-else-if="selected?.type === 'rect'" class="grid gap-4">
                    <h2 class="font-semibold">Rectángulo</h2>
                    <div class="grid gap-2">
                        <Label for="rect-fill">Color</Label>
                        <input
                            id="rect-fill"
                            v-model="selected.fill"
                            type="color"
                            class="h-9 w-full cursor-pointer rounded border"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rect-opacity"
                            >Opacidad:
                            {{ Math.round(selected.opacity * 100) }} %</Label
                        >
                        <input
                            id="rect-opacity"
                            v-model.number="selected.opacity"
                            type="range"
                            min="0"
                            max="1"
                            step="0.05"
                            class="accent-primary"
                        />
                    </div>
                </div>

                <!-- Página -->
                <div v-else-if="activePage" class="grid gap-4">
                    <h2 class="font-semibold">Página {{ activeIndex + 1 }}</h2>
                    <div class="grid gap-2">
                        <span>Fondo de la página</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                size="sm"
                                :variant="
                                    activePage.design.background === null
                                        ? 'default'
                                        : 'outline'
                                "
                                @click="setBackground(null)"
                            >
                                Sin fondo
                            </Button>
                            <button
                                type="button"
                                class="size-8 rounded border bg-white"
                                aria-label="Fondo blanco"
                                @click="setBackground('#ffffff')"
                            />
                            <button
                                type="button"
                                class="size-8 rounded border bg-black"
                                aria-label="Fondo negro"
                                @click="setBackground('#000000')"
                            />
                            <input
                                type="color"
                                class="h-8 w-12 cursor-pointer rounded border"
                                aria-label="Color de fondo"
                                :value="
                                    activePage.design.background?.color ??
                                    '#ffffff'
                                "
                                @input="
                                    setBackground(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </div>
                    </div>
                    <div v-if="activePage.design.background" class="grid gap-2">
                        <Label for="bg-mode">Cómo se aplica</Label>
                        <select
                            id="bg-mode"
                            v-model="activePage.design.background.mode"
                            class="h-9 rounded-md border border-input bg-transparent px-2"
                        >
                            <option value="behind" class="bg-background">
                                Detrás de la página
                            </option>
                            <option value="tint" class="bg-background">
                                Teñir la página
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            <template
                                v-if="
                                    activePage.design.background.mode ===
                                    'behind'
                                "
                            >
                                Se ve en páginas en blanco o transparentes. Si
                                la página tiene una imagen de fondo, la imagen
                                lo tapa: usa «Teñir».
                            </template>
                            <template v-else>
                                Lo blanco toma este color y lo oscuro se
                                mantiene. Funciona mejor con colores claros.
                            </template>
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Selecciona un elemento para cambiarlo, o añade un texto
                        o un rectángulo con los botones de arriba.
                    </p>
                </div>

                <!-- Acciones comunes del elemento -->
                <div
                    v-if="selected && !exact"
                    class="mt-6 grid gap-4 border-t pt-4"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1">
                            <Label for="el-x">X (mm)</Label>
                            <Input
                                id="el-x"
                                v-model.number="selected.x"
                                type="number"
                                step="0.5"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="el-y">Y (mm)</Label>
                            <Input
                                id="el-y"
                                v-model.number="selected.y"
                                type="number"
                                step="0.5"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="el-w">Ancho (mm)</Label>
                            <Input
                                id="el-w"
                                v-model.number="selected.width"
                                type="number"
                                min="3"
                                step="0.5"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="el-h">Alto (mm)</Label>
                            <Input
                                id="el-h"
                                v-model.number="selected.height"
                                type="number"
                                min="3"
                                step="0.5"
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="duplicateSelected"
                            ><Copy /> Duplicar</Button
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="reorder(true)"
                            ><ArrowUpToLine /> Al frente</Button
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="reorder(false)"
                            ><ArrowDownToLine /> Al fondo</Button
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-destructive"
                            @click="removeSelected"
                            ><Trash2 /> Eliminar</Button
                        >
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
