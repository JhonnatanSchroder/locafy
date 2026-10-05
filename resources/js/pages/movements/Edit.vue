<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MovementForm from '@/components/movements/MovementForm.vue';
import { Button } from '@/components/ui/button';
import { index as movementsIndex, show as movementsShow } from '@/routes/movements';
import type { Movement, MovementContract } from '@/types';

defineProps<{ movement: Movement; selectedContract: MovementContract }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Movimentações', href: movementsIndex() }, { title: 'Editar', href: '#' }] } });
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="`Editar movimentação #${movement.id}`" />
        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="text-2xl font-semibold tracking-tight">Editar movimentação #{{ movement.id }}</h1><p class="text-sm text-muted-foreground">Corrija data, observação ou quantidades físicas.</p></div>
            <div class="flex gap-2"><Button variant="outline" as-child><Link :href="movementsShow(movement.id)">Ver</Link></Button><Button variant="outline" as-child><Link :href="movementsIndex()">Voltar</Link></Button></div>
        </div>
        <MovementForm :movement="movement" :selected-contract="selectedContract" />
    </div>
</template>
