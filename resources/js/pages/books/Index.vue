<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { BookPlus } from '@lucide/vue';
import BookController from '@/actions/App/Http/Controllers/BookController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index } from '@/routes/books';
import type { BookSummary } from '@/types/book';

defineProps<{
    books: BookSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Libros', href: index() }],
    },
});

const dateFormat = new Intl.DateTimeFormat('es', { dateStyle: 'medium' });
</script>

<template>
    <Head title="Libros" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Libros"
            description="Sube el PDF de un libro ya maquetado (Canva, Word, InDesign…) para ordenar sus páginas, numerarlas e imprimirlo por cuadernillos."
        />

        <Card class="max-w-2xl" data-guide="subir-libro">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BookPlus class="size-4" /> Nuevo libro desde PDF
                </CardTitle>
                <CardDescription>
                    El PDF original se guarda intacto; todo lo que edites se
                    aplica al imprimir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="BookController.store.form()"
                    class="grid gap-4"
                    v-slot="{ errors, processing, progress }"
                >
                    <div class="grid gap-2">
                        <Label for="pdf">PDF</Label>
                        <Input
                            id="pdf"
                            name="pdf"
                            type="file"
                            accept="application/pdf"
                            required
                        />
                        <InputError :message="errors.pdf" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="title">Título (opcional)</Label>
                        <Input
                            id="title"
                            name="title"
                            placeholder="Se toma del nombre del archivo"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Procesando…' : 'Crear libro' }}
                        </Button>
                        <div
                            v-if="processing"
                            class="flex min-w-48 flex-1 items-center gap-3 text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            <progress
                                class="h-2 flex-1 overflow-hidden rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:bg-muted [&::-webkit-progress-value]:bg-primary"
                                max="100"
                                :value="progress?.percentage ?? 0"
                            />
                            <span class="tabular-nums">
                                {{
                                    (progress?.percentage ?? 0) < 100
                                        ? `Subiendo ${progress?.percentage ?? 0} %`
                                        : 'Preparando páginas…'
                                }}
                            </span>
                        </div>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <section
            v-if="books.length > 0"
            aria-labelledby="mis-libros"
            data-guide="lista-libros"
        >
            <h2 id="mis-libros" class="mb-4 text-lg font-semibold">
                Mis libros
            </h2>
            <ul
                class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5 2xl:grid-cols-7"
            >
                <li v-for="book in books" :key="book.id">
                    <Link
                        :href="edit(book.id)"
                        class="group block space-y-2 rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <img
                            :src="book.cover_url"
                            :alt="`Portada de ${book.title}`"
                            class="aspect-[3/4] w-full rounded-md border bg-muted object-contain shadow-sm transition group-hover:shadow-md"
                            loading="lazy"
                        />
                        <div>
                            <p
                                class="truncate font-medium group-hover:underline"
                            >
                                {{ book.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ book.page_count }} págs. · {{ book.size }}
                                <template v-if="book.updated_at">
                                    ·
                                    {{
                                        dateFormat.format(
                                            new Date(book.updated_at),
                                        )
                                    }}
                                </template>
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
