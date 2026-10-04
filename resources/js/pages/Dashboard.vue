<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    CheckCircle2,
    Circle,
    FileText,
    Plus,
    Printer,
    Ruler,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { edit, index as books } from '@/routes/books';
import { create as imposition } from '@/routes/imposition';
import { calibration, testDocument } from '@/routes/printing';

const props = defineProps<{
    recentBooks: {
        id: number;
        title: string;
        page_count: number;
        cover_url: string;
        updated_at: string | null;
    }[];
    bookCount: number;
    steps: { book: boolean; edit: boolean; print: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
    },
});

const page = usePage();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const workspace = computed(() => page.props.auth.publisher);

const steps = computed(() => [
    {
        done: props.steps.book,
        title: 'Sube tu primer libro',
        text: 'El PDF de Canva, Word o InDesign. El original se guarda intacto.',
        href: books().url,
        action: 'Subir libro',
    },
    {
        done: props.steps.edit,
        title: 'Prepáralo para imprimir',
        text: 'Ordena páginas, numéralas, ajusta márgenes o retoca textos en el editor de pliego.',
        href: books().url,
        action: 'Ver mis libros',
    },
    {
        done: props.steps.print,
        title: 'Genera el PDF para imprimir',
        text: 'Imprime a tamaño real, en dúplex por el borde corto, y dobla por cuadernillos.',
        href: books().url,
        action: 'Imprimir',
    },
]);
const pending = computed(() => steps.value.filter((s) => !s.done).length);

const dateFormat = new Intl.DateTimeFormat('es', { dateStyle: 'medium' });
</script>

<template>
    <Head title="Inicio" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Hola, {{ firstName }}
                </h1>
                <p class="text-muted-foreground">
                    Estás en
                    <span class="font-medium text-foreground">{{
                        workspace?.name
                    }}</span>
                    <template v-if="workspace?.personal">
                        (tu espacio personal)</template
                    >
                    · {{ bookCount }} {{ bookCount === 1 ? 'libro' : 'libros' }}
                </p>
            </div>
            <Button as-child size="lg">
                <Link :href="books()"><Plus /> Nuevo libro</Link>
            </Button>
        </div>

        <Card v-if="pending > 0">
            <CardHeader>
                <CardTitle>Primeros pasos</CardTitle>
                <CardDescription>
                    {{
                        pending === 3
                            ? 'Tres pasos para tener tu libro listo para coser.'
                            : `Te ${pending === 1 ? 'queda 1 paso' : `quedan ${pending} pasos`}.`
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol class="grid gap-4 md:grid-cols-3">
                    <li
                        v-for="(step, i) in steps"
                        :key="step.title"
                        class="flex flex-col gap-2 rounded-lg border p-4"
                        :class="step.done ? 'opacity-60' : ''"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <CheckCircle2
                                v-if="step.done"
                                class="size-5 text-primary"
                            />
                            <Circle
                                v-else
                                class="size-5 text-muted-foreground"
                            />
                            {{ i + 1 }}. {{ step.title }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ step.text }}
                        </p>
                        <Button
                            v-if="!step.done"
                            variant="outline"
                            size="sm"
                            class="mt-auto self-start"
                            as-child
                        >
                            <Link :href="step.href">{{ step.action }}</Link>
                        </Button>
                    </li>
                </ol>
            </CardContent>
        </Card>

        <section aria-labelledby="recientes" class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 id="recientes" class="text-lg font-semibold">
                    Libros recientes
                </h2>
                <Link
                    v-if="bookCount > recentBooks.length"
                    :href="books()"
                    class="text-sm text-primary hover:underline"
                >
                    Ver todos
                </Link>
            </div>

            <div
                v-if="recentBooks.length === 0"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center"
            >
                <BookOpenText class="size-10 text-muted-foreground" />
                <p class="font-medium">Todavía no hay libros en este espacio</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Sube el PDF de un libro y Noctua lo prepara para imprimir y
                    coser.
                </p>
                <Button as-child
                    ><Link :href="books()"
                        ><Plus /> Subir mi primer libro</Link
                    ></Button
                >
            </div>

            <ul
                v-else
                class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-6"
            >
                <li v-for="book in recentBooks" :key="book.id">
                    <Link
                        :href="edit(book.id)"
                        class="group block space-y-2 rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <img
                            :src="book.cover_url"
                            :alt="`Portada de ${book.title}`"
                            class="aspect-[3/4] w-full rounded-md border bg-muted object-contain shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md"
                            loading="lazy"
                        />
                        <div>
                            <p
                                class="truncate text-sm font-medium group-hover:text-primary"
                            >
                                {{ book.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ book.page_count }} págs.
                                <template v-if="book.updated_at"
                                    >·
                                    {{
                                        dateFormat.format(
                                            new Date(book.updated_at),
                                        )
                                    }}</template
                                >
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </section>

        <section aria-labelledby="herramientas" class="space-y-4">
            <h2 id="herramientas" class="text-lg font-semibold">
                Herramientas
            </h2>
            <div class="grid gap-4 md:grid-cols-3">
                <Link
                    :href="imposition()"
                    class="group rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Card
                        class="h-full transition-colors group-hover:bg-accent"
                    >
                        <CardHeader>
                            <Printer class="mb-2 size-6 text-primary" />
                            <CardTitle>Imposición rápida</CardTitle>
                            <CardDescription
                                >Cualquier PDF ordenado por cuadernillos, sin
                                crear un libro.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                </Link>
                <a
                    :href="calibration().url"
                    class="group rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Card
                        class="h-full transition-colors group-hover:bg-accent"
                    >
                        <CardHeader>
                            <Ruler class="mb-2 size-6 text-primary" />
                            <CardTitle>Calibrar la impresora</CardTitle>
                            <CardDescription
                                >Mide la zona no imprimible y alinea frente y
                                dorso.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                </a>
                <a
                    :href="testDocument().url"
                    class="group rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Card
                        class="h-full transition-colors group-hover:bg-accent"
                    >
                        <CardHeader>
                            <FileText class="mb-2 size-6 text-primary" />
                            <CardTitle>Documento de prueba</CardTitle>
                            <CardDescription
                                >Dieciséis páginas numeradas para practicar el
                                doblado.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                </a>
            </div>
        </section>
    </div>
</template>
