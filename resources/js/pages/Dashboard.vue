<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpenText, FileText, Ruler } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { create as imposition } from '@/routes/imposition';
import { calibration, testDocument } from '@/routes/printing';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
    },
});

const tools = [
    {
        title: 'Imponer un PDF',
        description:
            'Sube el PDF de páginas y obtén las hojas carta 2-up ordenadas por cuadernillos.',
        icon: BookOpenText,
        href: imposition().url,
        inertia: true,
    },
    {
        title: 'Calibrar la impresora',
        description:
            'Hoja de dos caras con reglas, escalas de borde y cruces de registro.',
        icon: Ruler,
        href: calibration().url,
        inertia: false,
    },
    {
        title: 'Documento de prueba',
        description:
            'Dieciséis páginas numeradas para comprobar el doblado y el orden.',
        icon: FileText,
        href: testDocument().url,
        inertia: false,
    },
];
</script>

<template>
    <Head title="Inicio" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Noctua"
            description="Composición e impresión de libros cosidos a mano."
        />

        <div class="grid gap-4 md:grid-cols-3">
            <component
                :is="tool.inertia ? Link : 'a'"
                v-for="tool in tools"
                :key="tool.title"
                :href="tool.href"
                class="group rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <Card class="h-full transition-colors group-hover:bg-accent">
                    <CardHeader>
                        <tool.icon class="mb-2 size-6 text-muted-foreground" />
                        <CardTitle>{{ tool.title }}</CardTitle>
                        <CardDescription>{{
                            tool.description
                        }}</CardDescription>
                    </CardHeader>
                </Card>
            </component>
        </div>
    </div>
</template>
