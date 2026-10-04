<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useThemeSettings } from '@/composables/useThemeSettings';

const { resolvedMode, setMode } = useThemeSettings();
const dark = computed(() => resolvedMode.value === 'dark');
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        :aria-label="dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :title="dark ? 'Modo claro' : 'Modo oscuro'"
        @click="setMode(dark ? 'light' : 'dark')"
    >
        <Sun v-if="dark" />
        <Moon v-else />
    </Button>
</template>
