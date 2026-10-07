<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { start as startDemo } from '@/routes/demo';

/**
 * «Entrar como invitado»: probar Noctua sin registrarse. Solo aparece si el
 * modo de prueba está activado (NOCTUA_DEMO=true).
 */
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });
</script>

<template>
    <Form
        v-if="$page.props.demo?.enabled"
        v-slot="{ processing }"
        v-bind="startDemo.form()"
        :class="
            compact
                ? 'grid gap-3'
                : 'flex flex-wrap items-center gap-3 rounded-lg border border-dashed p-4'
        "
    >
        <div
            v-if="compact"
            class="flex items-center gap-3 text-xs text-muted-foreground uppercase"
        >
            <span class="h-px flex-1 bg-border" />o<span
                class="h-px flex-1 bg-border"
            />
        </div>
        <Button
            type="submit"
            variant="secondary"
            :class="compact ? 'w-full' : ''"
            :disabled="processing"
        >
            <FlaskConical />
            {{ processing ? 'Preparando…' : 'Entrar como invitado' }}
        </Button>
        <span
            class="text-sm text-muted-foreground"
            :class="compact ? 'text-center' : ''"
        >
            Sin registrarte: pruébalo todo con tu PDF. Al salir se borra.
        </span>
    </Form>
</template>
