<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpenText, Layers, Printer, Type } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    {
        icon: BookOpenText,
        text: 'Sube el PDF de tu libro y ordena sus páginas.',
    },
    {
        icon: Type,
        text: 'Edita textos, tipografías y colores sobre el libro abierto.',
    },
    { icon: Layers, text: 'Numeración, márgenes y fondos para todo el libro.' },
    {
        icon: Printer,
        text: 'Imprímelo en casa por cuadernillos, listo para coser.',
    },
];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex"
        >
            <!-- Trama suave de páginas superpuestas. -->
            <div
                class="pointer-events-none absolute -right-24 -bottom-24 size-[28rem] rotate-12 rounded-3xl border border-current opacity-10"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -right-10 -bottom-36 size-[28rem] rotate-6 rounded-3xl border border-current opacity-10"
                aria-hidden="true"
            />

            <Link
                :href="home()"
                class="relative flex items-center gap-3 text-xl font-semibold"
            >
                <AppLogoIcon class="size-10 fill-current" />
                Noctua
            </Link>

            <div class="relative max-w-md space-y-8">
                <h2 class="text-3xl leading-tight font-semibold">
                    De tu PDF a un libro cosido a mano.
                </h2>
                <ul class="space-y-4">
                    <li
                        v-for="item in highlights"
                        :key="item.text"
                        class="flex items-start gap-3"
                    >
                        <component
                            :is="item.icon"
                            class="mt-0.5 size-5 shrink-0 opacity-90"
                        />
                        <span class="opacity-90">{{ item.text }}</span>
                    </li>
                </ul>
            </div>

            <p class="relative text-sm opacity-75">
                Para autores independientes y editoriales artesanales.
            </p>
        </aside>

        <main
            class="flex flex-col items-center justify-center bg-background p-6 md:p-10"
        >
            <div class="w-full max-w-sm space-y-8">
                <Link
                    :href="home()"
                    class="flex items-center justify-center gap-2 text-lg font-semibold lg:hidden"
                >
                    <AppLogoIcon class="size-8 fill-current" />
                    Noctua
                </Link>
                <div class="space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
