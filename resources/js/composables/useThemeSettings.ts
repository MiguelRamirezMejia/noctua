import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { applyAccent } from '@/lib/accent';
import { update } from '@/routes/appearance';
import type { Appearance } from '@/types';

/**
 * Modo y color de acento guardados en la cuenta: se aplican al instante en el
 * navegador y se guardan en el servidor para cualquier dispositivo.
 */
export function useThemeSettings() {
    const page = usePage();
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const accent = computed(() => page.props.appearance?.accent ?? null);

    function save(mode: Appearance, color: string | null): void {
        updateAppearance(mode);
        applyAccent(color);
        router.patch(
            update().url,
            { appearance: mode, accent_color: color },
            { preserveScroll: true, preserveState: true },
        );
    }

    return {
        mode: appearance,
        resolvedMode: resolvedAppearance,
        accent,
        setMode: (mode: Appearance) => save(mode, accent.value),
        setAccent: (color: string | null) => save(appearance.value, color),
    };
}
