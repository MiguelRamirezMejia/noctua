<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';
import GuideBat from '@/components/guide/GuideBat.vue';
import GuideTour from '@/components/guide/GuideTour.vue';
import { useGuide } from '@/composables/useGuide';
import { guideForPage } from '@/lib/guide';

/**
 * Botón flotante con el murciélago para abrir la guía de la pantalla actual.
 * La primera vez que se entra a cada pantalla, la guía se abre sola.
 */
const page = usePage();
const { active, start, startOnce } = useGuide();

const guideId = computed(() => guideForPage[page.component] ?? null);

let autoTimer: ReturnType<typeof setTimeout> | undefined;
watch(
    guideId,
    (id) => {
        clearTimeout(autoTimer);

        if (id) {
            // Un momento para que la pantalla termine de pintarse.
            autoTimer = setTimeout(() => startOnce(id), 700);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(autoTimer));
</script>

<template>
    <button
        v-if="guideId && !active"
        type="button"
        class="group fixed right-4 bottom-4 z-40 flex items-center gap-2 rounded-full border bg-background/90 py-1 pr-4 pl-1 text-sm font-medium shadow-lg backdrop-blur transition hover:-translate-y-0.5 hover:shadow-xl"
        aria-label="Abrir la guía de esta pantalla"
        @click="start(guideId)"
    >
        <span
            class="flex size-11 items-center justify-center overflow-hidden rounded-full bg-accent"
        >
            <GuideBat class="w-14 translate-y-1" />
        </span>
        <span class="hidden sm:inline">¿Te guío?</span>
    </button>
    <GuideTour />
</template>
