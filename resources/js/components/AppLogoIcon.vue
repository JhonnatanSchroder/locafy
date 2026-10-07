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
<img
    src="/images/logo.png"
    alt="Locafy"
    class="mx-auto mb-1 w-auto object-contain"
/>
</template>
