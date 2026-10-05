<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MovementForm from '@/components/movements/MovementForm.vue';
import { Button } from '@/components/ui/button';
import { index as movementsIndex } from '@/routes/movements';
import type { MovementContract, MovementContractOption, MovementType, MovementTypeOption } from '@/types';

defineProps<{
    contracts: MovementContractOption[];
    selectedContract: MovementContract | null;
    selectedType: MovementType;
    movementTypes: MovementTypeOption[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Movimentações', href: movementsIndex() }, { title: 'Nova', href: '#' }] } });
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Nova movimentação" />
        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="text-2xl font-semibold tracking-tight">Nova movimentação</h1><p class="text-sm text-muted-foreground">Registre retirada ou devolução física.</p></div>
            <Button variant="outline" as-child><Link :href="movementsIndex()">Voltar</Link></Button>
        </div>
        <MovementForm :contracts="contracts" :selected-contract="selectedContract" :selected-type="selectedType" :movement-types="movementTypes" />
    </div>
</template>
