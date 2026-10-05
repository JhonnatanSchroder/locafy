<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create as movementsCreate, edit as movementsEdit, index as movementsIndex, show as movementsShow } from '@/routes/movements';
import type { Movement, MovementTypeOption } from '@/types';

type PaginatedMovements = {
    data: Movement[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    movements: PaginatedMovements;
    filters: { search: string; type: string };
    movementTypes: MovementTypeOption[];
}>();

const search = ref(props.filters.search);
const type = ref(props.filters.type);

const submitSearch = () => {
    router.get(movementsIndex.url(), { search: search.value || undefined, type: type.value || undefined }, { preserveState: true, replace: true });
};

const itemSummary = (movement: Movement) => movement.items.map((item) => `${item.quantity} ${item.product.name}`).join(' · ');

defineOptions({
    layout: { breadcrumbs: [{ title: 'Movimentações', href: movementsIndex() }] },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Movimentações" />

        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Movimentações</h1>
                <p class="text-sm text-muted-foreground">Retiradas, devoluções e correções físicas dos contratos.</p>
            </div>
            <Button as-child><Link :href="movementsCreate()">Nova movimentação</Link></Button>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">Buscar movimentações</CardTitle></CardHeader>
            <CardContent>
                <form class="flex flex-col gap-3 lg:flex-row" @submit.prevent="submitSearch">
                    <Input v-model="search" placeholder="Contrato ou cliente" class="lg:max-w-md" />
                    <select v-model="type" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm lg:max-w-56">
                        <option value="">Todos os tipos</option>
                        <option v-for="option in movementTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <div class="flex gap-2">
                        <Button type="submit">Buscar</Button>
                        <Button v-if="filters.search || filters.type" variant="outline" as-child><Link :href="movementsIndex()">Limpar</Link></Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/60 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Data/hora</th>
                                <th class="px-4 py-3 font-medium">Contrato</th>
                                <th class="px-4 py-3 font-medium">Cliente</th>
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 font-medium">Itens</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="movements.data.length === 0"><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Nenhuma movimentação encontrada.</td></tr>
                            <tr v-for="movement in movements.data" :key="movement.id" class="border-b transition-colors hover:bg-muted/40 last:border-0">
                                <td class="px-4 py-3">{{ movement.occurred_at }}</td>
                                <td class="px-4 py-3 font-medium">#{{ movement.contract.number }}</td>
                                <td class="px-4 py-3">{{ movement.contract.client.name }}</td>
                                <td class="px-4 py-3"><Badge :variant="movement.type === 'WITHDRAWAL' ? 'info' : 'success'">{{ movement.type_label }}</Badge></td>
                                <td class="px-4 py-3">{{ itemSummary(movement) || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" as-child><Link :href="movementsShow(movement.id)">Ver</Link></Button>
                                        <Button variant="outline" size="sm" as-child><Link :href="movementsEdit(movement.id)">Editar</Link></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
