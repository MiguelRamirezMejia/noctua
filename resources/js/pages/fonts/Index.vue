<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Trash2, Upload } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import FontController from '@/actions/App/Http/Controllers/FontController';
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
import { loadFontFaces } from '@/composables/useFontFaces';
import { destroy, index } from '@/routes/fonts';
import type { FontFamilyInfo } from '@/types/fonts';

const props = defineProps<{
    families: FontFamilyInfo[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Fuentes', href: index() }],
    },
});

onMounted(() => loadFontFaces(props.families));

const search = ref('');
const sample = ref('Había una vez un dragón muy rápido');

const groups = computed(() => {
    const term = search.value.trim().toLowerCase();
    const result = new Map<string, FontFamilyInfo[]>();

    for (const family of props.families) {
        if (term && !family.label.toLowerCase().includes(term)) {
            continue;
        }

        const list = result.get(family.category) ?? [];
        list.push(family);
        result.set(family.category, list);
    }

    return [...result.entries()];
});

const faceLabel = (weight: number, italic: boolean) =>
    `${weight >= 600 ? 'negrita' : weight <= 300 ? 'fina' : 'regular'}${italic ? ' cursiva' : ''}`;

function removeFace(id: number, label: string) {
    if (window.confirm(`¿Eliminar la variante ${label}?`)) {
        router.delete(destroy(id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Fuentes" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Fuentes"
            description="Tipos de letra disponibles para los textos y los números de página. Puedes subir los tuyos."
        />

        <Card class="max-w-2xl">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Upload class="size-4" /> Subir fuentes
                </CardTitle>
                <CardDescription>
                    Archivos TTF u OTF. Sube cada variante que quieras usar
                    (regular, negrita, cursiva…). Asegúrate de que su licencia
                    permite usarlas en libros impresos.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="FontController.store.form()"
                    class="flex flex-wrap items-end gap-4"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <div class="grid min-w-64 flex-1 gap-2">
                        <Label for="files">Archivos de fuente</Label>
                        <Input
                            id="files"
                            name="files[]"
                            type="file"
                            accept=".ttf,.otf,font/ttf,font/otf"
                            multiple
                            required
                        />
                        <InputError :message="errors.files" />
                    </div>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Subiendo…' : 'Subir' }}
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <div class="flex flex-wrap gap-4">
            <div class="grid min-w-56 gap-2">
                <Label for="search">Buscar</Label>
                <Input
                    id="search"
                    v-model="search"
                    placeholder="Nombre de la fuente"
                />
            </div>
            <div class="grid min-w-72 flex-1 gap-2">
                <Label for="sample">Texto de muestra</Label>
                <Input id="sample" v-model="sample" />
            </div>
        </div>

        <section
            v-for="[category, list] in groups"
            :key="category"
            :aria-labelledby="`cat-${category}`"
            class="space-y-3"
        >
            <h2 :id="`cat-${category}`" class="text-lg font-semibold">
                {{ category }}
            </h2>
            <ul class="grid gap-3 lg:grid-cols-2">
                <li
                    v-for="family in list"
                    :key="family.family"
                    class="rounded-xl border bg-card p-4"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-medium">{{
                            family.label
                        }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{
                                family.faces.length === 0
                                    ? 'incluida'
                                    : family.faces
                                          .map((f) =>
                                              faceLabel(f.weight, f.italic),
                                          )
                                          .join(', ')
                            }}
                        </span>
                    </div>
                    <p
                        class="mt-2 truncate text-2xl"
                        :style="{ fontFamily: `'${family.family}', serif` }"
                    >
                        {{ sample }}
                    </p>
                    <ul v-if="family.own" class="mt-3 flex flex-wrap gap-2">
                        <li v-for="face in family.faces" :key="face.id">
                            <Button
                                variant="outline"
                                size="sm"
                                class="text-destructive"
                                :aria-label="`Eliminar ${family.label} ${faceLabel(face.weight, face.italic)}`"
                                @click="
                                    removeFace(
                                        face.id,
                                        `${family.label} ${faceLabel(face.weight, face.italic)}`,
                                    )
                                "
                            >
                                <Trash2 />
                                {{ faceLabel(face.weight, face.italic) }}
                            </Button>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>
    </div>
</template>
