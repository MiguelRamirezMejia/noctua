<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Download, FileText, Ruler } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import ImpositionController from '@/actions/App/Http/Controllers/ImpositionController';
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
import { create, show } from '@/routes/imposition';
import { calibration, testDocument } from '@/routes/printing';
import type { ImpositionDefaults, RecentImposition } from '@/types/imposition';

const props = defineProps<{
    defaults: ImpositionDefaults;
    recent: RecentImposition[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Imposición', href: create() }],
    },
});

const offsets = reactive({
    front_offset_x: props.defaults.front_offset_x,
    front_offset_y: props.defaults.front_offset_y,
    back_offset_x: props.defaults.back_offset_x,
    back_offset_y: props.defaults.back_offset_y,
});
const sheets = ref(props.defaults.sheets_per_signature);
const testPages = ref(16);

const calibrationUrl = computed(
    () => calibration({ query: { ...offsets } }).url,
);
const testDocumentUrl = computed(
    () => testDocument({ query: { pages: testPages.value } }).url,
);

const offsetFields = [
    { name: 'front_offset_x', label: 'Frente X' },
    { name: 'front_offset_y', label: 'Frente Y' },
    { name: 'back_offset_x', label: 'Dorso X' },
    { name: 'back_offset_y', label: 'Dorso Y' },
] as const;

const dateFormat = new Intl.DateTimeFormat('es', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <Head title="Imposición" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Imposición"
            description="Convierte el PDF de páginas de tu libro en hojas carta horizontales 2-up, listas para imprimir, doblar y coser por cuadernillos."
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <Card>
                <CardHeader>
                    <CardTitle>Imponer un PDF</CardTitle>
                    <CardDescription>
                        Todas las páginas deben tener el mismo tamaño y caber
                        dos veces en la hoja (media carta o menor).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="ImpositionController.store.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="pdf">PDF de páginas</Label>
                            <Input
                                id="pdf"
                                name="pdf"
                                type="file"
                                accept="application/pdf"
                                required
                            />
                            <InputError :message="errors.pdf" />
                        </div>

                        <fieldset class="grid gap-4 sm:grid-cols-2">
                            <legend class="mb-2 text-sm font-medium">
                                Cuadernillos
                            </legend>

                            <div class="grid gap-2">
                                <Label for="sheets_per_signature">
                                    Hojas por cuadernillo
                                </Label>
                                <select
                                    id="sheets_per_signature"
                                    v-model.number="sheets"
                                    name="sheets_per_signature"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="n in 8"
                                        :key="n"
                                        :value="n"
                                        class="bg-background"
                                    >
                                        {{ n }}
                                        {{ n === 1 ? 'hoja' : 'hojas' }} ·
                                        {{ 4 * n }} páginas
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.sheets_per_signature"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="creep_mm"
                                    >Creep (mm por hoja)</Label
                                >
                                <Input
                                    id="creep_mm"
                                    name="creep_mm"
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    max="2"
                                    :default-value="defaults.creep_mm"
                                />
                                <InputError :message="errors.creep_mm" />
                            </div>

                            <label
                                class="flex items-center gap-2 text-sm sm:col-span-2"
                            >
                                <input
                                    type="hidden"
                                    name="short_last_signature"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="short_last_signature"
                                    value="1"
                                    class="size-4 accent-primary"
                                    :checked="defaults.short_last_signature"
                                />
                                Acortar el último cuadernillo en lugar de
                                rellenarlo con páginas en blanco
                            </label>
                        </fieldset>

                        <fieldset class="grid gap-4">
                            <legend class="mb-2 text-sm font-medium">
                                Impresora
                            </legend>

                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div
                                    v-for="field in offsetFields"
                                    :key="field.name"
                                    class="grid gap-2"
                                >
                                    <Label :for="field.name">
                                        {{ field.label }} (mm)
                                    </Label>
                                    <Input
                                        :id="field.name"
                                        v-model.number="offsets[field.name]"
                                        :name="field.name"
                                        type="number"
                                        step="0.05"
                                        min="-10"
                                        max="10"
                                    />
                                    <InputError :message="errors[field.name]" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="unprintable_margin_mm">
                                        Zona no imprimible (mm)
                                    </Label>
                                    <Input
                                        id="unprintable_margin_mm"
                                        name="unprintable_margin_mm"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="20"
                                        :default-value="
                                            defaults.unprintable_margin_mm
                                        "
                                    />
                                    <InputError
                                        :message="errors.unprintable_margin_mm"
                                    />
                                </div>

                                <label
                                    class="flex items-center gap-2 self-end pb-2 text-sm"
                                >
                                    <input
                                        type="hidden"
                                        name="fold_marks"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="fold_marks"
                                        value="1"
                                        class="size-4 accent-primary"
                                        :checked="defaults.fold_marks"
                                    />
                                    Marcas de doblez
                                </label>
                            </div>
                        </fieldset>

                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Imponiendo…' : 'Imponer PDF' }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Ruler class="size-4" /> Calibrar la impresora
                        </CardTitle>
                        <CardDescription>
                            Imprímela a tamaño real en dúplex (volteo por el
                            borde corto). Usa los offsets de la izquierda para
                            verificar una corrección.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button as-child variant="outline" class="w-full">
                            <a :href="calibrationUrl">
                                <Download /> Hoja de calibración
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="size-4" /> Documento de prueba
                        </CardTitle>
                        <CardDescription>
                            Páginas media carta numeradas en grande, con la
                            cabeza y el lomo marcados, para probar el doblado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-end gap-2">
                        <div class="grid flex-1 gap-2">
                            <Label for="test_pages">Páginas</Label>
                            <Input
                                id="test_pages"
                                v-model.number="testPages"
                                type="number"
                                min="1"
                                max="400"
                            />
                        </div>
                        <Button as-child variant="outline">
                            <a :href="testDocumentUrl"><Download /> PDF</a>
                        </Button>
                    </CardContent>
                </Card>

                <Card v-if="recent.length > 0">
                    <CardHeader>
                        <CardTitle>Recientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li v-for="item in recent" :key="item.id">
                                <Link
                                    :href="show(item.id)"
                                    class="flex flex-col py-2 hover:underline"
                                >
                                    <span class="truncate font-medium">
                                        {{ item.name }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ item.page_count }} págs. ·
                                        {{
                                            dateFormat.format(
                                                new Date(item.created_at),
                                            )
                                        }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
