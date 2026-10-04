<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Noctua: una lechuza (noctua, en latín) posada sobre un libro abierto. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
        role="img"
        aria-label="Noctua"
    >
        <path
            fill-rule="evenodd"
            d="M12 13 15 6.5l3.2 3.8a8.6 8.6 0 0 1 3.6 0L25 6.5l3 6.5c1.9 3.6 1.8 8-.1 11.6C26.4 28 23.4 30 20 30s-6.4-2-7.9-5.4c-1.9-3.6-2-8-.1-11.6ZM16.6 12.4a3.1 3.1 0 1 0 0 6.2 3.1 3.1 0 0 0 0-6.2Zm6.8 0a3.1 3.1 0 1 0 0 6.2 3.1 3.1 0 0 0 0-6.2ZM20 19.2l-1.6 2.2h3.2L20 19.2Z"
        />
        <path d="M6 30.5 20 33.6l14-3.1v3.6L20 37.3 6 34.1v-3.6Z" />
    </svg>
</template>
