import { computed, ref } from 'vue';
import { guides } from '@/lib/guide';
import type { Guide } from '@/lib/guide';

const STORAGE_PREFIX = 'noctua.guia.';

// Estado compartido por toda la app: solo hay un murciélago.
const active = ref<Guide | null>(null);
const step = ref(0);

function seen(id: string): boolean {
    try {
        return localStorage.getItem(STORAGE_PREFIX + id) === 'vista';
    } catch {
        return false;
    }
}

function markSeen(id: string): void {
    try {
        localStorage.setItem(STORAGE_PREFIX + id, 'vista');
    } catch {
        // Sin almacenamiento (modo privado): la guía volverá a aparecer.
    }
}

/**
 * Guía con el murciélago: abrir, avanzar, cerrar y recordar cuáles ya se vieron.
 */
export function useGuide() {
    const current = computed(() =>
        active.value ? active.value.steps[step.value] : null,
    );

    function start(id: string): void {
        const guide = guides[id];

        if (guide) {
            active.value = guide;
            step.value = 0;
        }
    }

    /** Abre la guía solo si es la primera vez en esta pantalla. */
    function startOnce(id: string): void {
        if (!seen(id) && active.value === null) {
            start(id);
        }
    }

    function close(): void {
        if (active.value) {
            markSeen(active.value.id);
        }

        active.value = null;
        step.value = 0;
    }

    function next(): void {
        if (!active.value) {
            return;
        }

        if (step.value < active.value.steps.length - 1) {
            step.value++;
        } else {
            close();
        }
    }

    function previous(): void {
        step.value = Math.max(0, step.value - 1);
    }

    return { active, step, current, start, startOnce, close, next, previous };
}
