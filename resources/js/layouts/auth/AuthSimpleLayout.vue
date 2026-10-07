<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center
               bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <!-- Título/descrição usados por outras páginas de autenticação -->
                <div
                    v-if="title || description"
                    class="space-y-2 text-center"
                >
                    <h1
                        v-if="title"
                        class="text-xl font-medium"
                    >
                        {{ title }}
                    </h1>

                    <p
                        v-if="description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
