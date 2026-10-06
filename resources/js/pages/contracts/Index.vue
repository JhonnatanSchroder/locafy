<script setup lang="ts">
import ContractOperations from '@/components/contracts/ContractOperations.vue';
import { formatDate } from '@/lib/dates';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ClipboardList, Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    create as contractsCreate,
    edit as contractsEdit,
    index as contractsIndex,
    show as contractsShow,
} from '@/routes/contracts';
import type { Contract, ContractStatusOption } from '@/types';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedContracts = {
    data: Contract[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    contracts: PaginatedContracts;
    overview: {active:number;returned:number;total:number};
    filters: {
        search: string;
        status: string;
    };
    contractStatuses: ContractStatusOption[];
};

const props = defineProps<Props>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);

const statusVariant = (contractStatus: string) => {
    if (contractStatus === 'PAYMENT_PENDING') return 'warning';
    if (contractStatus === 'READY_TO_FINALIZE') return 'info';
    if (contractStatus === 'ACTIVE') {
        return 'success';
    }

    if (contractStatus === 'CANCELLED') {
        return 'danger';
    }

    return 'muted';
};

const submitSearch = () => {
    router.get(
        contractsIndex.url(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
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

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contratos',
                href: contractsIndex(),
            },
        ],
    },
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Contratos" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Contratos</h1>
                <p class="text-sm text-muted-foreground">
                    Acompanhe locações, movimentações e o financeiro dos seus contratos.
                </p>
            </div>

            <Button as-child>
                <Link :href="contractsCreate()"><Plus class="mr-2 size-4"/> Novo contrato</Link>
            </Button>
        </div>

        <section class="grid gap-4 sm:grid-cols-3"><div v-for="metric in [{label:'Contratos ativos',value:overview.active},{label:'Devolvidos para fechamento',value:overview.returned},{label:'Total de contratos',value:overview.total}]" :key="metric.label" class="rounded-xl border bg-card px-5 py-4"><p class="text-xs font-medium text-muted-foreground">{{ metric.label }}</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ metric.value }}</p></div></section>
        <Card class="rounded-xl">
            <CardHeader>
                <CardTitle class="text-base">Buscar contratos</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="flex flex-col gap-3 lg:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="search"
                        name="search"
                        placeholder="Buscar por número, cliente ou endereço"
                        class="lg:max-w-md"
                    />
                    <select
                        v-model="status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none lg:max-w-56"
                    >
                        <option value="">Todos</option>
                        <option
                            v-for="option in contractStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <div class="flex gap-2">
                        <Button type="submit">Buscar</Button>
                        <Button
                            v-if="filters.search || filters.status"
                            variant="outline"
                            as-child
                        >
                            <Link :href="contractsIndex()">Limpar</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="rounded-xl">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b bg-muted/60 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Contrato</th>
                                <th class="px-4 py-3 font-medium">Cliente</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Total acumulado</th><th class="px-4 py-3 font-medium">Total pago</th>
                                <th class="px-4 py-3 font-medium">
                                    Próxima cobrança
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Itens atuais
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    Saldo a receber
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="contracts.data.length === 0">
                                <td
                                    colspan="9"
                                    class="px-4 py-14 text-center text-muted-foreground"
                                >
                                    <ClipboardList class="mx-auto mb-3 size-8"/><p class="font-semibold text-foreground">Nenhum contrato nesta seleção</p><p class="mt-1 text-sm">Ajuste os filtros ou crie uma nova locação.</p><Button variant="outline" class="mt-4" as-child><Link :href="contractsCreate()">Novo contrato</Link></Button>
                                </td>
                            </tr>
                            <tr
                                v-for="contract in contracts.data"
                                :key="contract.id"
                                class="border-b transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <Link :href="contractsShow(contract.id)" class="hover:text-primary">#{{ contract.number }}</Link>
                                </td>
                                <td class="px-4 py-3">
                                    {{ contract.client.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            statusVariant(contract.display_status)
                                        "
                                        >{{ contract.display_status_label }}</Badge
                                    >
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ formatCurrency(contract.total_accrued) }}</td><td class="px-4 py-3 tabular-nums">{{ formatCurrency(contract.total_paid) }}</td>
                                <td class="px-4 py-3">
                                    {{ formatDate(contract.next_charge_date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="
                                            contract.items.some(
                                                (item) =>
                                                    (item.current_quantity ??
                                                        0) > 0,
                                            )
                                        "
                                    >
                                        {{
                                            contract.items
                                                .filter(
                                                    (item) =>
                                                        (item.current_quantity ??
                                                            0) > 0,
                                                )
                                                .map(
                                                    (item) =>
                                                        `${item.current_quantity} ${item.product.name}`,
                                                )
                                                .join(' · ')
                                        }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>

                                <td class="px-4 py-3">
                                    <p class="text-base font-semibold tabular-nums text-primary">{{ formatCurrency(contract.financial_balance) }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Acumulado {{ formatCurrency(contract.total_accrued) }}</p>
                                    <p class="text-xs text-muted-foreground">Pago {{ formatCurrency(contract.total_paid) }}</p>
                                    <p class="text-xs text-muted-foreground">Descontos {{ formatCurrency(contract.total_discount) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2"><ContractOperations :contract="contract"/>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    contractsShow.url(contract.id)
                                                "
                                                >Ver</Link
                                            >
                                        </Button>
                                        <Button
                                            v-if="contract.status !== 'CANCELLED'"
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    contractsEdit.url(contract.id)
                                                "
                                                >Editar</Link
                                            >
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="contracts.links.length > 3"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Mostrando {{ contracts.from }} a {{ contracts.to }} de
                {{ contracts.total }} contratos
            </p>
            <div class="flex flex-wrap gap-2">
                <template v-for="link in contracts.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        as-child
                    >
                        <Link :href="link.url" v-html="link.label" />
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        size="sm"
                        disabled
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
