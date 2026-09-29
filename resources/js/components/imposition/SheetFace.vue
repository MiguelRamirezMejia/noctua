<script setup lang="ts">
import type { SlotPages } from '@/types/imposition';

defineProps<{
    label: string;
    pages: SlotPages;
}>();
</script>

<template>
    <figure class="space-y-1">
        <figcaption class="text-xs text-muted-foreground">
            {{ label }}
        </figcaption>
        <div
            class="relative grid aspect-[279.4/215.9] grid-cols-2 overflow-hidden rounded-md border bg-background"
            :aria-label="`${label}: página ${pages[0] ?? 'en blanco'} a la izquierda, página ${pages[1] ?? 'en blanco'} a la derecha`"
        >
            <div
                v-for="(page, i) in pages"
                :key="i"
                class="flex items-center justify-center"
                :class="
                    page === null
                        ? 'bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,var(--color-muted)_6px,var(--color-muted)_7px)] text-muted-foreground'
                        : ''
                "
            >
                <span
                    v-if="page !== null"
                    class="text-2xl font-semibold tabular-nums"
                >
                    {{ page }}
                </span>
                <span v-else class="text-[0.65rem] tracking-wide uppercase">
                    en blanco
                </span>
            </div>
            <div
                class="pointer-events-none absolute inset-y-0 left-1/2 border-l border-dashed border-muted-foreground/50"
                aria-hidden="true"
            />
        </div>
    </figure>
</template>
