<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    edit as contractsEdit,
    index as contractsIndex,
} from '@/routes/contracts';
import { store as freightsStore } from '@/routes/contracts/freights';
import {
    create as movementsCreate,
    edit as movementsEdit,
} from '@/routes/movements';
import type { Contract } from '@/types';

type Props = {
    contract: Contract;
};

const props = defineProps<Props>();

const freightForm = useForm({
    quantity: 1,
    unit_amount: '',
    occurred_at: '',
    notes: '',
});

const statusVariant = (status: string) => {
    if (status === 'ACTIVE') {
        return 'success';
    }

    if (status === 'CANCELLED') {
        return 'danger';
    }

    return 'muted';
};
const formatCurrency = (value: string | number | null | undefined) => {
    if (value === null || value === undefined) {
        return '—';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(amount);
};

const submitFreight = () => {
    freightForm.post(freightsStore.url(props.contract.id), {
        preserveScroll: true,
        onSuccess: () => freightForm.reset(),
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contratos',
                href: contractsIndex(),
            },
            {
                title: 'Detalhes',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="`Contrato #${contract.number}`" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <div class="mb-2 flex items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Contrato #{{ contract.number }}
                    </h1>
                    <Badge :variant="statusVariant(contract.status)">{{
                        contract.status_label
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ contract.client.name }}
                </p>
            </div>

            <div class="flex gap-2">
                <Button as-child>
                    <Link :href="contractsEdit(contract.id)">Editar</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            movementsCreate.url({
                                query: {
                                    contract: contract.id,
                                    type: 'WITHDRAWAL',
                                },
                            })
                        "
                        >Nova retirada</Link
                    >
                </Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            movementsCreate.url({
                                query: {
                                    contract: contract.id,
                                    type: 'RETURN',
                                },
                            })
                        "
                        >Nova devolução</Link
                    >
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="contractsIndex()">Voltar</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_24rem]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Dados do contrato</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Cliente
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ contract.client.name }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Início
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ contract.started_at || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Fim
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ contract.ended_at || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Próxima cobrança
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ contract.next_charge_date || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Cobrar sábado
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ contract.charge_saturdays ? 'Sim' : 'Não' }}
                            </dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Endereço da obra
                            </dt>
                            <dd class="mt-1 text-sm whitespace-pre-line">
                                {{ contract.worksite_address || '—' }}
                            </dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Observação
                            </dt>
                            <dd class="mt-1 text-sm whitespace-pre-line">
                                {{ contract.notes || '—' }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Resumo</CardTitle>
                </CardHeader>

                <CardContent>
                    <dl class="space-y-4">
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Itens
                            </dt>

                            <dd class="mt-1 text-sm">
                                {{ contract.items.length }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Fretes
                            </dt>

                            <dd
                                class="mt-1 flex items-center justify-between gap-3"
                            >
                                <span class="text-sm">
                                    {{ contract.freight_count }}
                                    {{
                                        contract.freight_count === 1
                                            ? 'frete'
                                            : 'fretes'
                                    }}
                                </span>

                                <span class="font-semibold">
                                    {{ formatCurrency(contract.freight_total) }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Locação acumulada
                            </dt>

                            <dd class="mt-1 text-sm">
                                <span
                                    v-if="
                                        contract.calculation_complete &&
                                        contract.rental_total !== null
                                    "
                                    class="font-semibold"
                                >
                                    {{ formatCurrency(contract.rental_total) }}
                                </span>

                                <span v-else class="text-muted-foreground">
                                    —
                                </span>
                            </dd>

                            <dd class="text-xs text-muted-foreground">
                                Calculado até
                                {{ contract.calculated_until || '—' }}
                            </dd>
                        </div>

                        <div class="border-t pt-4">
                            <dt
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Total acumulado
                            </dt>

                            <dd class="mt-1 text-lg font-semibold text-primary">
                                {{ formatCurrency(contract.total_accrued) }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Itens do contrato</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b bg-muted/60 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Produto</th>
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 font-medium">Período</th>
                                <th class="px-4 py-3 font-medium">Atual</th>
                                <th class="px-4 py-3 font-medium">Peça-dias</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Preço unitário
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Subtotal
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in contract.items"
                                :key="item.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ item.product.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.product.type_label }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.billing_period_label }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.current_quantity ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.billable_quantity_days ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ item.unit_price }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{
                                        item.accrued_subtotal
                                            ? `R$ ${item.accrued_subtotal}`
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Movimentações</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="!contract.movements?.length"
                    class="text-sm text-muted-foreground"
                >
                    —
                </div>
                <div
                    v-for="movement in contract.movements"
                    :key="movement.id"
                    class="border-b pb-4 last:border-0 last:pb-0"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-medium">
                                {{ movement.occurred_at }} ·
                                {{ movement.type_label }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    movement.items
                                        .map(
                                            (item) =>
                                                `${item.quantity}x ${item.product.name}`,
                                        )
                                        .join(' · ')
                                }}
                            </div>
                        </div>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="movementsEdit(movement.id)"
                                >Editar</Link
                            >
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-5 xl:grid-cols-[24rem_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Adicionar frete</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitFreight">
                        <div class="grid gap-2">
                            <label
                                for="freight_quantity"
                                class="text-sm font-medium"
                                >Quantidade</label
                            >
                            <input
                                id="freight_quantity"
                                v-model="freightForm.quantity"
                                type="number"
                                min="1"
                                step="1"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            />
                            <p
                                v-if="freightForm.errors.quantity"
                                class="text-sm text-destructive"
                            >
                                {{ freightForm.errors.quantity }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label
                                for="freight_unit_amount"
                                class="text-sm font-medium"
                                >Valor unitário</label
                            >
                            <input
                                id="freight_unit_amount"
                                v-model="freightForm.unit_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            />
                            <p
                                v-if="freightForm.errors.unit_amount"
                                class="text-sm text-destructive"
                            >
                                {{ freightForm.errors.unit_amount }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label
                                for="freight_occurred_at"
                                class="text-sm font-medium"
                                >Data</label
                            >
                            <input
                                id="freight_occurred_at"
                                v-model="freightForm.occurred_at"
                                type="datetime-local"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            />
                            <p
                                v-if="freightForm.errors.occurred_at"
                                class="text-sm text-destructive"
                            >
                                {{ freightForm.errors.occurred_at }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label
                                for="freight_notes"
                                class="text-sm font-medium"
                                >Observação</label
                            >
                            <textarea
                                id="freight_notes"
                                v-model="freightForm.notes"
                                rows="3"
                                class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            />
                            <p
                                v-if="freightForm.errors.notes"
                                class="text-sm text-destructive"
                            >
                                {{ freightForm.errors.notes }}
                            </p>
                        </div>

                        <Button :disabled="freightForm.processing"
                            >Registrar frete</Button
                        >
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Histórico de fretes</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="!contract.freights?.length"
                        class="text-sm text-muted-foreground"
                    >
                        —
                    </div>
                    <div
                        v-for="freight in contract.freights"
                        :key="freight.id"
                        class="border-b pb-4 last:border-0 last:pb-0"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium">
                                    {{ freight.occurred_at || '—' }}
                                </div>
                                <div class="text-sm">
                                    {{ freight.quantity }}
                                    {{
                                        freight.quantity === 1
                                            ? 'frete'
                                            : 'fretes'
                                    }}
                                    × {{ formatCurrency(freight.unit_amount) }}
                                </div>
                                <div
                                    class="text-sm whitespace-pre-line text-muted-foreground"
                                >
                                    {{ freight.notes || '—' }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold">
                                Total {{ formatCurrency(freight.total) }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
