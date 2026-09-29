<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Download } from '@lucide/vue';
import { computed } from 'vue';
import SheetFace from '@/components/imposition/SheetFace.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, pdf, show } from '@/routes/imposition';
import type {
    ImpositionSummary,
    SignaturePlan,
    StoredImposition,
} from '@/types/imposition';

const props = defineProps<{
    imposition: StoredImposition;
    summary: ImpositionSummary;
    signatures: SignaturePlan[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Imposición', href: create() },
        {
            title: props.imposition.original_name,
            href: show(props.imposition.id),
        },
    ],
});

const previewUrl = computed(() => pdf(props.imposition.id).url);
const downloadUrl = computed(
    () => pdf(props.imposition.id, { query: { download: 1 } }).url,
);

const stats = computed(() => [
    { label: 'Páginas', value: props.summary.page_count },
    { label: 'Cuadernillos', value: props.summary.signatures },
    { label: 'Hojas carta', value: props.summary.sheets },
    { label: 'Caras a imprimir', value: props.summary.faces },
    { label: 'Páginas en blanco', value: props.summary.blank_pages },
]);

/** Caras del PDF que ocupa cada cuadernillo, para imprimir por rangos. */
const faceRanges = computed(() => {
    let face = 1;

    return props.signatures.map((s) => {
        const first = face;
        face += 2 * s.sheets.length;

        return `${first}–${face - 1}`;
    });
});
</script>

<template>
    <Head :title="`Imposición · ${imposition.original_name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <h1 class="truncate text-xl font-semibold tracking-tight">
                    {{ imposition.original_name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Cuadernillos de
                    {{ imposition.settings.sheets_per_signature }}
                    {{
                        imposition.settings.sheets_per_signature === 1
                            ? 'hoja'
                            : 'hojas'
                    }}
                    · creep {{ imposition.settings.creep_mm }} mm/hoja · dorso
                    {{ imposition.printer.back_offset_x }},
                    {{ imposition.printer.back_offset_y }} mm
                </p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="create()"><ArrowLeft /> Nueva imposición</Link>
                </Button>
                <Button as-child>
                    <a :href="downloadUrl"><Download /> Descargar PDF</a>
                </Button>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-xl border bg-card p-4"
            >
                <dt class="text-xs text-muted-foreground">{{ stat.label }}</dt>
                <dd class="text-2xl font-semibold tabular-nums">
                    {{ stat.value }}
                </dd>
            </div>
        </dl>

        <Card>
            <CardHeader>
                <CardTitle>Cómo imprimir</CardTitle>
            </CardHeader>
            <CardContent>
                <ol class="list-decimal space-y-1 pl-5 text-sm">
                    <li>
                        Imprime a <strong>tamaño real (100 %)</strong>, sin
                        «Ajustar a página».
                    </li>
                    <li>
                        Dúplex con <strong>volteo por el borde corto</strong>.
                    </li>
                    <li>
                        Dobla cada hoja por las marcas centrales y mete las
                        hojas de cada cuadernillo una dentro de otra, la hoja 1
                        por fuera.
                    </li>
                </ol>
            </CardContent>
        </Card>

        <section class="space-y-4" aria-labelledby="pliegos">
            <h2 id="pliegos" class="text-lg font-semibold">Pliegos</h2>

            <Card v-for="(signature, s) in signatures" :key="signature.number">
                <CardHeader>
                    <CardTitle>Cuadernillo {{ signature.number }}</CardTitle>
                    <CardDescription>
                        Páginas {{ signature.first_page }}–{{
                            signature.last_page
                        }}
                        · caras {{ faceRanges[s] }} del PDF
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                    >
                        <div
                            v-for="sheet in signature.sheets"
                            :key="sheet.index"
                            class="space-y-2"
                        >
                            <p class="text-sm font-medium">
                                Hoja {{ sheet.index }}
                                <span
                                    v-if="sheet.index === 1"
                                    class="font-normal text-muted-foreground"
                                    >(exterior)</span
                                >
                                <span
                                    v-else-if="
                                        sheet.index === signature.sheets.length
                                    "
                                    class="font-normal text-muted-foreground"
                                    >(central)</span
                                >
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <SheetFace
                                    label="Frente"
                                    :pages="sheet.front"
                                />
                                <SheetFace label="Dorso" :pages="sheet.back" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="space-y-4" aria-labelledby="vista-pdf">
            <h2 id="vista-pdf" class="text-lg font-semibold">PDF impuesto</h2>
            <iframe
                :src="previewUrl"
                title="Vista previa del PDF impuesto"
                class="h-[80vh] w-full rounded-xl border bg-muted"
            />
        </section>
    </div>
</template>
