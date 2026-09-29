<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    Copy,
    Hash,
    SquarePlus,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import type { BookPage, FolioSettings } from '@/types/book';

const props = defineProps<{
    page: BookPage;
    position: number;
    total: number;
    thumbnailUrl: string | null;
    folioText: string | null;
    folios: FolioSettings;
    /** Tamaño de la página de origen en mm. */
    widthMm: number;
    heightMm: number;
    /** Escala con la que se imprimirá (para previsualizar el folio a su tamaño real). */
    printScale: number;
    dragging: boolean;
}>();

const emit = defineEmits<{
    move: [delta: number];
    duplicate: [];
    insertBlank: [];
    toggleFolio: [];
    remove: [];
    dragstart: [];
    dragend: [];
    drop: [];
}>();

const recto = computed(() => props.position % 2 === 1);

/** Folio en unidades del contenedor (cqw/cqh) para que escale con la miniatura. */
const folioStyle = computed(() => {
    const sizeMm = ((props.folios.size_pt / props.printScale) * 25.4) / 72;
    const marginMm = props.folios.margin_mm / props.printScale;
    const v = `${(marginMm / props.heightMm) * 100}cqh`;
    const h = `${(marginMm / props.widthMm) * 100}cqw`;
    const style: Record<string, string> = {
        fontFamily: `"${props.folios.font}"`,
        // Proporción real, con un mínimo legible en la miniatura.
        fontSize: `max(${(sizeMm / props.heightMm) * 100}cqh, 9px)`,
        lineHeight: '1',
    };

    if (props.folios.position === 'top-outer') {
        style.top = v;
    } else {
        style.bottom = v;
    }

    if (props.folios.position === 'bottom-center') {
        style.left = '50%';
        style.transform = 'translateX(-50%)';
    } else if (recto.value) {
        style.right = h;
    } else {
        style.left = h;
    }

    return style;
});

const label = computed(() =>
    props.page.source === null
        ? `Página ${props.position}: en blanco`
        : `Página ${props.position}: página ${props.page.source} del PDF`,
);
</script>

<template>
    <figure
        class="group relative flex w-full flex-col gap-1"
        :class="dragging ? 'opacity-40' : ''"
        draggable="true"
        :aria-label="label"
        @dragstart="emit('dragstart')"
        @dragend="emit('dragend')"
        @dragover.prevent
        @drop.prevent="emit('drop')"
    >
        <div
            class="[container-type:size] relative w-full overflow-hidden border bg-background shadow-sm"
            :style="{ aspectRatio: `${widthMm} / ${heightMm}` }"
        >
            <img
                v-if="thumbnailUrl"
                :src="thumbnailUrl"
                alt=""
                class="pointer-events-none size-full object-fill select-none"
                loading="lazy"
            />
            <div
                v-else
                class="flex size-full items-center justify-center bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,var(--color-muted)_6px,var(--color-muted)_7px)] text-xs text-muted-foreground uppercase"
            >
                en blanco
            </div>
            <span
                v-if="folioText"
                class="pointer-events-none absolute text-black"
                :style="folioStyle"
                >{{ folioText }}</span
            >
        </div>

        <figcaption
            class="flex items-center justify-between text-xs text-muted-foreground"
        >
            <span class="tabular-nums">
                {{ position }}
                <span v-if="page.source !== null && page.source !== position">
                    · pdf {{ page.source }}
                </span>
            </span>
            <span v-if="!page.folio && page.source !== null" title="Sin folio">
                sin nº
            </span>
        </figcaption>

        <div
            class="absolute inset-x-1 top-1 flex flex-wrap justify-center gap-1 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100"
        >
            <button
                type="button"
                class="rounded bg-background/90 p-1 shadow hover:bg-accent disabled:opacity-30"
                :disabled="position === 1"
                :aria-label="`Mover la página ${position} a la izquierda`"
                title="Mover antes"
                @click="emit('move', -1)"
            >
                <ArrowLeft class="size-3.5" />
            </button>
            <button
                type="button"
                class="rounded bg-background/90 p-1 shadow hover:bg-accent disabled:opacity-30"
                :disabled="position === total"
                :aria-label="`Mover la página ${position} a la derecha`"
                title="Mover después"
                @click="emit('move', 1)"
            >
                <ArrowRight class="size-3.5" />
            </button>
            <button
                v-if="page.source !== null"
                type="button"
                class="rounded bg-background/90 p-1 shadow hover:bg-accent"
                :class="page.folio ? '' : 'text-muted-foreground line-through'"
                :aria-label="`${page.folio ? 'Quitar' : 'Poner'} número en la página ${position}`"
                :aria-pressed="page.folio"
                :title="page.folio ? 'Quitar número' : 'Poner número'"
                @click="emit('toggleFolio')"
            >
                <Hash class="size-3.5" />
            </button>
            <button
                type="button"
                class="rounded bg-background/90 p-1 shadow hover:bg-accent"
                :aria-label="`Duplicar la página ${position}`"
                title="Duplicar"
                @click="emit('duplicate')"
            >
                <Copy class="size-3.5" />
            </button>
            <button
                type="button"
                class="rounded bg-background/90 p-1 shadow hover:bg-accent"
                :aria-label="`Insertar una página en blanco después de la ${position}`"
                title="Insertar blanco después"
                @click="emit('insertBlank')"
            >
                <SquarePlus class="size-3.5" />
            </button>
            <button
                type="button"
                class="rounded bg-background/90 p-1 text-destructive shadow hover:bg-accent disabled:opacity-30"
                :disabled="total === 1"
                :aria-label="`Quitar la página ${position}`"
                title="Quitar"
                @click="emit('remove')"
            >
                <Trash2 class="size-3.5" />
            </button>
        </div>
    </figure>
</template>
