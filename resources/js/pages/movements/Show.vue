<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit as movementsEdit, index as movementsIndex } from '@/routes/movements';
import type { Movement } from '@/types';

defineProps<{ movement: Movement }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Movimentações', href: movementsIndex() }, { title: 'Detalhes', href: '#' }] } });
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="`Movimentação #${movement.id}`" />
        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="text-2xl font-semibold tracking-tight">Movimentação #{{ movement.id }}</h1><p class="text-sm text-muted-foreground">Contrato #{{ movement.contract.number }} · {{ movement.contract.client.name }}</p></div>
            <div class="flex gap-2"><Button as-child><Link :href="movementsEdit(movement.id)">Editar</Link></Button><Button variant="outline" as-child><Link :href="movementsIndex()">Voltar</Link></Button></div>
        </div>
        <Card>
            <CardHeader><CardTitle class="text-base">Dados</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center gap-3"><Badge :variant="movement.type === 'WITHDRAWAL' ? 'info' : 'success'">{{ movement.type_label }}</Badge><span class="text-sm">{{ movement.occurred_at }}</span></div>
                <p class="whitespace-pre-line text-sm text-muted-foreground">{{ movement.notes || '—' }}</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader><CardTitle class="text-base">Itens</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/60 text-left text-muted-foreground"><tr><th class="px-4 py-3 font-medium">Produto</th><th class="px-4 py-3 font-medium">Quantidade</th></tr></thead>
                    <tbody><tr v-for="item in movement.items" :key="item.id" class="border-b last:border-0"><td class="px-4 py-3 font-medium">{{ item.product.name }}</td><td class="px-4 py-3">{{ item.quantity }}</td></tr></tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
