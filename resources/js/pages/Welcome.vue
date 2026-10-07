<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpenText,
    Building2,
    Layers,
    Printer,
    Ruler,
    Type,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import GuestAccess from '@/components/GuestAccess.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: BookOpenText,
        title: 'Tu libro, tal como lo diseñaste',
        text: 'Sube el PDF de Canva, Word o InDesign. Reordena, quita o duplica páginas sin tocar el original.',
    },
    {
        icon: Type,
        title: 'Edita sobre el libro abierto',
        text: 'Cambia textos, tipografías y colores en la vista de doble página, con palabras sueltas en negrita o de color.',
    },
    {
        icon: Layers,
        title: 'Ajustes para todo el libro',
        text: 'Márgenes en espejo, numeración de páginas, fondos y fuentes aplicados de una vez.',
    },
    {
        icon: Printer,
        title: 'Imprime en casa por cuadernillos',
        text: 'Noctua ordena las páginas en hojas carta para doblar, anidar y coser. Con vista previa de cada pliego.',
    },
    {
        icon: Ruler,
        title: 'Calibrado para tu impresora',
        text: 'Una hoja de calibración mide la zona no imprimible y alinea frente y dorso.',
    },
    {
        icon: Building2,
        title: 'Solo o en equipo',
        text: 'Trabaja en tu espacio personal o crea una editorial y comparte los libros con tu equipo.',
    },
];
</script>

<template>
    <Head title="Bienvenido" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex max-w-6xl items-center justify-between gap-4 p-6"
        >
            <div class="flex items-center gap-2 text-lg font-semibold">
                <AppLogoIcon class="size-8 fill-current" />
                Noctua
            </div>
            <nav class="flex items-center gap-2">
                <ThemeToggle v-if="$page.props.auth.user" />
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Ir a mis libros</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Iniciar sesión</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Crear cuenta</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-6 pb-20">
            <section
                class="grid items-center gap-10 py-12 lg:grid-cols-2 lg:py-20"
            >
                <div class="space-y-6">
                    <p
                        class="inline-flex rounded-full bg-accent px-3 py-1 text-sm font-medium text-accent-foreground"
                    >
                        Para autores y editoriales artesanales
                    </p>
                    <h1
                        class="text-4xl leading-tight font-semibold tracking-tight md:text-5xl"
                    >
                        De tu PDF a un libro cosido a mano.
                    </h1>
                    <p class="max-w-xl text-lg text-muted-foreground">
                        Noctua prepara tu libro para imprimirlo en una impresora
                        doméstica, doblarlo por cuadernillos y coserlo. Sin
                        cálculos, sin errores de orden.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button size="lg" as-child>
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboard()
                                        : register()
                                "
                            >
                                {{
                                    $page.props.auth.user
                                        ? 'Ir a mis libros'
                                        : 'Empezar gratis'
                                }}
                            </Link>
                        </Button>
                        <Button
                            v-if="!$page.props.auth.user"
                            size="lg"
                            variant="outline"
                            as-child
                        >
                            <Link :href="login()">Ya tengo cuenta</Link>
                        </Button>
                    </div>
                    <GuestAccess v-if="!$page.props.auth.user" />
                </div>

                <!-- Un pliego abierto estilizado. -->
                <div
                    class="relative mx-auto aspect-[4/3] w-full max-w-lg"
                    aria-hidden="true"
                >
                    <div
                        class="absolute inset-x-6 top-6 bottom-0 rotate-2 rounded-xl bg-muted"
                    />
                    <div
                        class="absolute inset-0 grid grid-cols-2 overflow-hidden rounded-xl border bg-card shadow-xl"
                    >
                        <div class="space-y-3 border-r border-dashed p-8">
                            <div class="h-3 w-2/3 rounded bg-foreground/80" />
                            <div class="h-2 rounded bg-muted-foreground/30" />
                            <div class="h-2 rounded bg-muted-foreground/30" />
                            <div
                                class="h-2 w-5/6 rounded bg-muted-foreground/30"
                            />
                            <div
                                class="mt-6 aspect-[4/3] rounded-lg bg-primary/80"
                            />
                        </div>
                        <div class="space-y-3 p-8">
                            <div class="h-2 rounded bg-muted-foreground/30" />
                            <div
                                class="h-2 w-11/12 rounded bg-muted-foreground/30"
                            />
                            <div class="h-2 rounded bg-muted-foreground/30" />
                            <div
                                class="h-2 w-3/4 rounded bg-muted-foreground/30"
                            />
                            <div class="h-2 rounded bg-muted-foreground/30" />
                            <div
                                class="h-2 w-4/5 rounded bg-muted-foreground/30"
                            />
                            <div
                                class="pt-10 text-right text-xs text-muted-foreground"
                            >
                                7
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="funciones" class="space-y-8">
                <h2 id="funciones" class="text-2xl font-semibold">
                    Todo lo que necesitas para tu libro
                </h2>
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-xl border bg-card p-6"
                    >
                        <component
                            :is="feature.icon"
                            class="mb-4 size-6 text-primary"
                        />
                        <h3 class="mb-2 font-semibold">{{ feature.title }}</h3>
                        <p class="text-sm text-muted-foreground">
                            {{ feature.text }}
                        </p>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
