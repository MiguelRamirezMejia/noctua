<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Monitor, Moon, Palette, Sun } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useThemeSettings } from '@/composables/useThemeSettings';
import { edit } from '@/routes/appearance';

defineProps<{
    presets: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Apariencia', href: edit() }],
    },
});

const { mode, accent, setMode, setAccent } = useThemeSettings();

const modes = [
    { value: 'light', icon: Sun, label: 'Claro' },
    { value: 'dark', icon: Moon, label: 'Oscuro' },
    { value: 'system', icon: Monitor, label: 'Como el sistema' },
] as const;

// El selector de color libre guarda con una pequeña espera mientras se arrastra.
const custom = ref(accent.value ?? '#2563eb');
let timer: ReturnType<typeof setTimeout> | undefined;

function pickCustom(value: string) {
    custom.value = value;
    clearTimeout(timer);
    timer = setTimeout(() => setAccent(value), 300);
}
</script>

<template>
    <Head title="Apariencia" />

    <h1 class="sr-only">Apariencia</h1>

    <div class="space-y-10">
        <section class="space-y-4" aria-labelledby="modo">
            <Heading
                id="modo"
                variant="small"
                title="Modo"
                description="Claro, oscuro o el que use tu sistema. Se guarda en tu cuenta."
            />
            <div class="grid max-w-xl grid-cols-3 gap-3">
                <button
                    v-for="option in modes"
                    :key="option.value"
                    type="button"
                    class="flex flex-col items-center gap-2 rounded-lg border p-4 text-sm transition hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="
                        mode === option.value
                            ? 'border-primary ring-1 ring-primary'
                            : ''
                    "
                    :aria-pressed="mode === option.value"
                    @click="setMode(option.value)"
                >
                    <component :is="option.icon" class="size-5" />
                    {{ option.label }}
                </button>
            </div>
        </section>

        <section class="space-y-4" aria-labelledby="color">
            <Heading
                id="color"
                variant="small"
                title="Color de acento"
                description="Colorea botones, enlaces, el elemento activo del menú, el foco y el resaltado al pasar el ratón."
            />

            <div class="flex max-w-xl flex-wrap gap-3">
                <button
                    type="button"
                    class="relative flex size-10 items-center justify-center rounded-full border bg-neutral-900 text-white transition hover:scale-110 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:bg-neutral-100 dark:text-neutral-900"
                    :aria-pressed="accent === null"
                    aria-label="Color de Noctua (neutro)"
                    title="Noctua (neutro)"
                    @click="setAccent(null)"
                >
                    <Check v-if="accent === null" class="size-4" />
                </button>
                <button
                    v-for="preset in presets"
                    :key="preset.value"
                    type="button"
                    class="relative flex size-10 items-center justify-center rounded-full text-white transition hover:scale-110 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :style="{ backgroundColor: preset.value }"
                    :aria-pressed="accent === preset.value"
                    :aria-label="preset.label"
                    :title="preset.label"
                    @click="setAccent(preset.value)"
                >
                    <Check v-if="accent === preset.value" class="size-4" />
                </button>
                <label
                    class="relative flex size-10 cursor-pointer items-center justify-center rounded-full border transition hover:scale-110"
                    title="Otro color"
                >
                    <Palette class="size-4" />
                    <input
                        type="color"
                        class="absolute inset-0 cursor-pointer opacity-0"
                        :value="custom"
                        aria-label="Elegir otro color"
                        @input="
                            pickCustom(
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
            </div>
        </section>

        <section class="space-y-4" aria-labelledby="vista">
            <Heading id="vista" variant="small" title="Vista previa" />
            <div class="max-w-xl space-y-4 rounded-xl border bg-card p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <Button>Botón principal</Button>
                    <Button variant="outline">Secundario</Button>
                    <Button variant="ghost">Pasa el ratón por aquí</Button>
                </div>
                <div
                    class="rounded-md bg-accent px-3 py-2 text-sm text-accent-foreground"
                >
                    Así se ve un elemento resaltado o activo.
                </div>
                <input
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="Haz clic aquí para ver el foco"
                />
            </div>
        </section>
    </div>
</template>
