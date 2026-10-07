<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import GuideBat from '@/components/guide/GuideBat.vue';
import { Button } from '@/components/ui/button';
import { useGuide } from '@/composables/useGuide';

/**
 * La guía en pantalla: ilumina el elemento de cada paso y el murciélago lo
 * explica en un bocadillo cuyo texto se escribe solo.
 */
const { active, step, current, close, next, previous } = useGuide();

const CARD_WIDTH = 480;
const CARD_HEIGHT = 230;
const GAP = 16;
const PAD = 8;

const rect = ref<DOMRect | null>(null);
const viewport = ref({ width: window.innerWidth, height: window.innerHeight });

function findTarget(): HTMLElement | null {
    const name = current.value?.target;

    if (!name) {
        return null;
    }

    // El primero visible: puede haber varios (uno por pliego, por ejemplo).
    const candidates = document.querySelectorAll<HTMLElement>(
        `[data-guide="${name}"]`,
    );

    for (const el of candidates) {
        const r = el.getBoundingClientRect();

        if (r.width > 0 && r.height > 0) {
            return el;
        }
    }

    return null;
}

function measure() {
    viewport.value = { width: window.innerWidth, height: window.innerHeight };
    rect.value = findTarget()?.getBoundingClientRect() ?? null;
}

let settleTimer: ReturnType<typeof setTimeout> | undefined;

async function focusStep() {
    await nextTick();
    const el = findTarget();
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    measure();
    clearTimeout(settleTimer);
    // Tras el desplazamiento suave, vuelve a medir.
    settleTimer = setTimeout(measure, 450);
}

// --- Texto que se escribe solo ---
const typed = ref('');
const typing = ref(false);
let typeTimer: ReturnType<typeof setInterval> | undefined;

function typeText(text: string) {
    clearInterval(typeTimer);
    typed.value = '';
    typing.value = true;
    let i = 0;

    typeTimer = setInterval(() => {
        i = Math.min(text.length, i + 2);
        typed.value = text.slice(0, i);

        if (i >= text.length) {
            clearInterval(typeTimer);
            typing.value = false;
        }
    }, 18);
}

function finishTyping() {
    if (typing.value && current.value) {
        clearInterval(typeTimer);
        typed.value = current.value.text;
        typing.value = false;
    }
}

watch(
    current,
    (value) => {
        if (value) {
            typeText(value.text);
            void focusStep();
        } else {
            clearInterval(typeTimer);
        }
    },
    { immediate: true },
);

// --- Posición del foco y del bocadillo ---
const spotlight = computed(() =>
    rect.value
        ? {
              top: `${rect.value.top - PAD}px`,
              left: `${rect.value.left - PAD}px`,
              width: `${rect.value.width + 2 * PAD}px`,
              height: `${rect.value.height + 2 * PAD}px`,
          }
        : null,
);

const cardPosition = computed(() => {
    const { width, height } = viewport.value;
    const cardWidth = Math.min(CARD_WIDTH, width - 24);
    const r = rect.value;

    if (!r) {
        return {
            left: (width - cardWidth) / 2,
            top: Math.max(12, (height - CARD_HEIGHT) / 2),
            width: cardWidth,
        };
    }

    const left = Math.min(
        Math.max(12, r.left + r.width / 2 - cardWidth / 2),
        width - cardWidth - 12,
    );

    let top: number;
    if (r.bottom + PAD + GAP + CARD_HEIGHT <= height) {
        top = r.bottom + PAD + GAP;
    } else if (r.top - PAD - GAP - CARD_HEIGHT >= 0) {
        top = r.top - PAD - GAP - CARD_HEIGHT;
    } else {
        // El elemento es muy alto: el bocadillo va abajo, encima de él.
        top = height - CARD_HEIGHT - 12;
    }

    return { left, top, width: cardWidth };
});

const cardStyle = computed(() => ({
    left: `${cardPosition.value.left}px`,
    top: `${cardPosition.value.top}px`,
    width: `${cardPosition.value.width}px`,
}));

// El murciélago mira hacia el elemento señalado.
const look = computed(() => {
    const r = rect.value;

    if (!r) {
        return { x: 0, y: 0 };
    }

    const p = cardPosition.value;
    const dx = r.left + r.width / 2 - (p.left + 60);
    const dy = r.top + r.height / 2 - (p.top + 60);
    const length = Math.hypot(dx, dy) || 1;

    return { x: dx / length, y: dy / length };
});

const tilt = computed(() => (step.value % 2 === 0 ? -7 : 7));
const isLast = computed(
    () => !!active.value && step.value === active.value.steps.length - 1,
);

// --- Teclado: se atiende antes que el editor (que usa las flechas). ---
function onKeydown(event: KeyboardEvent) {
    if (!active.value) {
        return;
    }

    const handled = ['Escape', 'ArrowRight', 'ArrowLeft', 'Enter'];
    if (!handled.includes(event.key)) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (event.key === 'Escape') {
        close();
    } else if (event.key === 'ArrowLeft') {
        previous();
    } else if (typing.value) {
        finishTyping();
    } else {
        next();
    }
}

onMounted(() => {
    window.addEventListener('resize', measure);
    window.addEventListener('scroll', measure, true);
    window.addEventListener('keydown', onKeydown, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', measure);
    window.removeEventListener('scroll', measure, true);
    window.removeEventListener('keydown', onKeydown, true);
    clearInterval(typeTimer);
    clearTimeout(settleTimer);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="guide-fade">
            <div
                v-if="active && current"
                class="fixed inset-0 z-[200]"
                role="dialog"
                aria-modal="true"
                :aria-label="`Guía: ${active.title}`"
            >
                <!-- Fondo atenuado con un hueco en el elemento señalado. -->
                <div
                    v-if="spotlight"
                    class="pointer-events-none fixed rounded-xl ring-2 ring-primary transition-all duration-300 ease-out"
                    :style="{
                        ...spotlight,
                        boxShadow: '0 0 0 9999px rgb(0 0 0 / 0.55)',
                    }"
                />
                <div v-else class="fixed inset-0 bg-black/55" />

                <!-- Murciélago y bocadillo -->
                <div
                    class="fixed flex items-end gap-2 transition-all duration-300 ease-out"
                    :style="cardStyle"
                >
                    <GuideBat
                        class="w-36 shrink-0 drop-shadow-lg"
                        :talking="typing"
                        :look-x="look.x"
                        :look-y="look.y"
                        :tilt="tilt"
                    />
                    <div
                        class="relative mb-6 flex-1 rounded-2xl border bg-popover p-4 text-popover-foreground shadow-2xl"
                    >
                        <!-- Pico del bocadillo hacia el murciélago -->
                        <span
                            class="absolute bottom-6 -left-2 size-4 rotate-45 border-b border-l bg-popover"
                            aria-hidden="true"
                        />
                        <button
                            type="button"
                            class="absolute top-2 right-2 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                            aria-label="Cerrar la guía"
                            @click="close"
                        >
                            <X class="size-4" />
                        </button>
                        <p class="pr-6 font-semibold">{{ current.title }}</p>
                        <p
                            class="mt-1 min-h-16 text-sm leading-relaxed text-muted-foreground"
                            aria-live="polite"
                            @click="finishTyping"
                        >
                            {{ typed
                            }}<span
                                v-if="typing"
                                class="ml-0.5 inline-block h-4 w-0.5 animate-pulse bg-primary align-middle"
                            />
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <div class="flex gap-1" aria-hidden="true">
                                <span
                                    v-for="(_, i) in active.steps"
                                    :key="i"
                                    class="h-1.5 rounded-full transition-all"
                                    :class="
                                        i === step
                                            ? 'w-4 bg-primary'
                                            : 'w-1.5 bg-muted-foreground/30'
                                    "
                                />
                            </div>
                            <span class="sr-only"
                                >Paso {{ step + 1 }} de
                                {{ active.steps.length }}</span
                            >
                            <div class="ml-auto flex gap-1">
                                <Button
                                    v-if="step > 0"
                                    size="sm"
                                    variant="ghost"
                                    @click="previous"
                                >
                                    <ChevronLeft /> Atrás
                                </Button>
                                <Button size="sm" @click="next">
                                    {{ isLast ? '¡Entendido!' : 'Siguiente' }}
                                    <ChevronRight v-if="!isLast" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.guide-fade-enter-active,
.guide-fade-leave-active {
    transition: opacity 0.25s ease;
}

.guide-fade-enter-from,
.guide-fade-leave-to {
    opacity: 0;
}
</style>
