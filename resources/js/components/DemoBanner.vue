<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FlaskConical, LogOut } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { logout } from '@/routes';

const page = usePage();
const expiresAt = computed(() => page.props.demo?.expires_at ?? null);

// Se refresca cada minuto para que el tiempo restante no quede congelado.
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 60_000)));
onBeforeUnmount(() => clearInterval(timer));

const remaining = computed(() => {
    if (!expiresAt.value) {
        return '';
    }

    const minutes = Math.max(
        0,
        Math.round((new Date(expiresAt.value).getTime() - now.value) / 60_000),
    );

    return minutes >= 60
        ? `${Math.floor(minutes / 60)} h ${minutes % 60} min`
        : `${minutes} min`;
});
</script>

<template>
    <div
        v-if="expiresAt"
        class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b bg-accent px-4 py-2 text-sm text-accent-foreground md:px-6"
        role="status"
    >
        <FlaskConical class="size-4 shrink-0" />
        <p class="flex-1">
            <strong>Modo de prueba.</strong> Puedes usarlo todo, pero nada se
            guarda: tus libros se borrarán al salir o dentro de {{ remaining }}.
        </p>
        <Link
            :href="logout()"
            as="button"
            class="inline-flex items-center gap-1 font-medium underline underline-offset-4"
        >
            <LogOut class="size-4" /> Salir y borrar
        </Link>
    </div>
</template>
