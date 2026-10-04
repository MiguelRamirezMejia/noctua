<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3';
import { computed, ref } from 'vue';
import RichTextEditor from '@/components/book/editor/RichTextEditor.vue';
import type { ContentFrame } from '@/lib/layout';
import { ptToPx } from '@/lib/richText';
import type {
    BookPage,
    FolioSettings,
    PageElement,
    TextElement,
    TextSpan,
} from '@/types/book';

const props = defineProps<{
    page: BookPage;
    position: number;
    widthMm: number;
    heightMm: number;
    /** Píxeles de pantalla por mm. */
    scale: number;
    imageUrl: string | null;
    folioText: string | null;
    folios: FolioSettings;
    printScale: number;
    /** Dónde va el contenido según los márgenes del libro. */
    frame: ContentFrame;
    /** SVG del marco decorativo, o null si esta página no lleva. */
    borderUrl?: string | null;
    selectedId: string | null;
    editingId: string | null;
    active: boolean;
}>();

const emit = defineEmits<{
    select: [id: string | null];
    edit: [id: string];
    stopEditing: [];
    editorReady: [editor: Editor];
    changed: [];
}>();

const MIN_SIZE_MM = 3;
const px = (mm: number) => `${mm * props.scale}px`;

const background = computed(() => props.page.design.background);

const frameStyle = computed(() => ({
    left: px(props.frame.x),
    top: px(props.frame.y),
    width: px(props.widthMm),
    height: px(props.heightMm),
    transform: `scale(${props.frame.scale})`,
}));

// --- Arrastrar y cambiar tamaño ---
type Handle = 'move' | 'nw' | 'ne' | 'sw' | 'se' | 'n' | 's' | 'e' | 'w';
const drag = ref<{
    element: PageElement;
    handle: Handle;
    startX: number;
    startY: number;
    box: { x: number; y: number; width: number; height: number };
} | null>(null);

function startDrag(event: PointerEvent, element: PageElement, handle: Handle) {
    if (props.editingId === element.id) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    emit('select', element.id);
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    drag.value = {
        element,
        handle,
        startX: event.clientX,
        startY: event.clientY,
        box: {
            x: element.x,
            y: element.y,
            width: element.width,
            height: element.height,
        },
    };
}

function onDrag(event: PointerEvent) {
    const d = drag.value;

    if (!d) {
        return;
    }

    // El contenido está escalado por los márgenes: el movimiento en pantalla se divide también por esa escala.
    const dx = (event.clientX - d.startX) / (props.scale * props.frame.scale);
    const dy = (event.clientY - d.startY) / (props.scale * props.frame.scale);
    const round = (n: number) => Math.round(n * 10) / 10;
    const el = d.element;
    const b = d.box;

    if (d.handle === 'move') {
        el.x = round(b.x + dx);
        el.y = round(b.y + dy);

        return;
    }

    if (d.handle.includes('e')) {
        el.width = round(Math.max(MIN_SIZE_MM, b.width + dx));
    }

    if (d.handle.includes('s')) {
        el.height = round(Math.max(MIN_SIZE_MM, b.height + dy));
    }

    if (d.handle.includes('w')) {
        const width = Math.max(MIN_SIZE_MM, b.width - dx);
        el.x = round(b.x + b.width - width);
        el.width = round(width);
    }

    if (d.handle.includes('n')) {
        const height = Math.max(MIN_SIZE_MM, b.height - dy);
        el.y = round(b.y + b.height - height);
        el.height = round(height);
    }
}

function endDrag() {
    if (drag.value) {
        drag.value = null;
        emit('changed');
    }
}

const handles: { name: Handle; class: string }[] = [
    { name: 'nw', class: '-left-1.5 -top-1.5 cursor-nwse-resize' },
    { name: 'n', class: 'left-1/2 -top-1.5 -translate-x-1/2 cursor-ns-resize' },
    { name: 'ne', class: '-right-1.5 -top-1.5 cursor-nesw-resize' },
    {
        name: 'e',
        class: '-right-1.5 top-1/2 -translate-y-1/2 cursor-ew-resize',
    },
    { name: 'se', class: '-right-1.5 -bottom-1.5 cursor-nwse-resize' },
    {
        name: 's',
        class: 'left-1/2 -bottom-1.5 -translate-x-1/2 cursor-ns-resize',
    },
    { name: 'sw', class: '-left-1.5 -bottom-1.5 cursor-nesw-resize' },
    { name: 'w', class: '-left-1.5 top-1/2 -translate-y-1/2 cursor-ew-resize' },
];

// --- Estilos ---
function textBoxStyle(el: TextElement): Record<string, string> {
    return {
        fontFamily: `"${el.font}"`,
        fontSize: `${ptToPx(el.size_pt, props.scale)}px`,
        lineHeight: String(el.line_height),
        color: el.color,
        textAlign: el.align,
        justifyContent:
            el.valign === 'middle'
                ? 'center'
                : el.valign === 'bottom'
                  ? 'flex-end'
                  : 'flex-start',
        backgroundColor: el.fill ?? 'transparent',
    };
}

function spanStyle(span: TextSpan): Record<string, string> {
    return {
        fontWeight: span.bold ? '700' : '400',
        fontStyle: span.italic ? 'italic' : 'normal',
        ...(span.color ? { color: span.color } : {}),
    };
}

const folioStyle = computed(() => {
    const f = props.folios;
    const marginMm = f.margin_mm / props.printScale;
    const recto = props.position % 2 === 1;
    const style: Record<string, string> = {
        fontFamily: `"${f.font}"`,
        fontSize: `${ptToPx(f.size_pt / props.printScale, props.scale)}px`,
        lineHeight: '1',
    };

    style[f.position === 'top-outer' ? 'top' : 'bottom'] = px(marginMm);

    if (f.position === 'bottom-center') {
        style.left = '50%';
        style.transform = 'translateX(-50%)';
    } else {
        style[recto ? 'right' : 'left'] = px(marginMm);
    }

    return style;
});

function updateSpans(el: TextElement, spans: TextSpan[]) {
    el.spans = spans;
    emit('changed');
}
</script>

<template>
    <div
        class="relative shrink-0 overflow-hidden bg-white shadow-lg"
        :class="active ? 'ring-2 ring-primary/40' : ''"
        :style="{ width: px(widthMm), height: px(heightMm) }"
        :aria-label="`Página ${position}`"
        role="group"
        @pointerdown.self="emit('select', null)"
        @pointermove="onDrag"
        @pointerup="endDrag"
        @pointercancel="endDrag"
    >
        <!-- Fondo «detrás»: solo se ve donde la página no pinta nada. -->
        <div
            v-if="background?.mode === 'behind'"
            class="pointer-events-none absolute inset-0"
            :style="{ backgroundColor: background.color }"
        />
        <div
            v-if="imageUrl"
            class="pointer-events-none absolute origin-top-left"
            :style="frameStyle"
        >
            <img
                :src="imageUrl"
                alt=""
                class="size-full object-contain select-none"
                draggable="false"
            />
        </div>
        <!-- Tinte: misma fusión «multiplicar» que al imprimir. -->
        <div
            v-if="background?.mode === 'tint'"
            class="pointer-events-none absolute inset-0 mix-blend-multiply"
            :style="{ backgroundColor: background.color }"
        />

        <!-- Marco decorativo: SVG de Typst a página completa, como al imprimir. -->
        <img
            v-if="borderUrl"
            :src="borderUrl"
            alt=""
            loading="lazy"
            class="pointer-events-none absolute inset-0 size-full select-none"
            draggable="false"
        />

        <!-- Los elementos acompañan al contenido dentro de los márgenes del libro. -->
        <div
            class="pointer-events-none absolute origin-top-left"
            :style="frameStyle"
        >
            <div
                v-for="el in page.design.elements"
                :key="el.id"
                class="pointer-events-auto absolute"
                :class="[
                    selectedId === el.id
                        ? 'outline-2 outline-primary'
                        : 'hover:outline-1 hover:outline-primary/50',
                    editingId === el.id ? '' : 'cursor-move',
                ]"
                :style="{
                    left: px(el.x),
                    top: px(el.y),
                    width: px(el.width),
                    height: px(el.height),
                }"
                @pointerdown="startDrag($event, el, 'move')"
                @dblclick.stop="el.type === 'text' && emit('edit', el.id)"
            >
                <div
                    v-if="el.type === 'rect'"
                    class="size-full"
                    :style="{ backgroundColor: el.fill, opacity: el.opacity }"
                />
                <div
                    v-else
                    class="flex size-full flex-col whitespace-pre-wrap"
                    :style="textBoxStyle(el)"
                >
                    <RichTextEditor
                        v-if="editingId === el.id"
                        :spans="el.spans"
                        @change="(s) => updateSpans(el, s)"
                        @ready="(e) => emit('editorReady', e)"
                        @done="emit('stopEditing')"
                    />
                    <div v-else>
                        <span
                            v-for="(span, i) in el.spans"
                            :key="i"
                            :style="spanStyle(span)"
                            >{{ span.text }}</span
                        >
                    </div>
                </div>

                <template v-if="selectedId === el.id && editingId !== el.id">
                    <span
                        v-for="h in handles"
                        :key="h.name"
                        class="absolute size-3 rounded-sm border border-primary bg-background"
                        :class="h.class"
                        @pointerdown="startDrag($event, el, h.name)"
                    />
                </template>
            </div>
        </div>

        <span
            v-if="folioText"
            class="pointer-events-none absolute text-black"
            :style="folioStyle"
            >{{ folioText }}</span
        >
    </div>
</template>
