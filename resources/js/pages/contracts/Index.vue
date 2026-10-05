<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Contratos" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Contratos</h1>
                <p class="text-sm text-muted-foreground">
                    Cadastre e acompanhe contratos reais do Locafy.
                </p>
            </div>

            <Button as-child>
                <Link :href="contractsCreate()">Novo contrato</Link>
            </Button>
        </div>

        <Card>
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
                        <option value="">Todos os status</option>
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

        <Card>
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
                                <th class="px-4 py-3 font-medium">Início</th>
                                <th class="px-4 py-3 font-medium">Fim</th>
                                <th class="px-4 py-3 font-medium">
                                    Próxima cobrança
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Itens atuais
                                </th>
                                <th class="px-4 py-3 font-medium">Fretes</th>
                                <th class="px-4 py-3 font-medium">
                                    Valor acumulado
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="contracts.data.length === 0">
                                <td
                                    colspan="10"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Nenhum contrato encontrado.
                                </td>
                            </tr>
                            <tr
                                v-for="contract in contracts.data"
                                :key="contract.id"
                                class="border-b transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-4 py-3 font-medium">
                                    #{{ contract.number }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ contract.client.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            statusVariant(contract.status)
                                        "
                                        >{{ contract.status_label }}</Badge
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    {{ contract.started_at || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ contract.ended_at || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ contract.next_charge_date || '—' }}
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
                                    <div class="font-medium">
                                        {{ contract.freight_count }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            formatCurrency(
                                                contract.freight_total,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="
                                            contract.calculation_complete &&
                                            contract.total_accrued
                                        "
                                        >{{
                                            formatCurrency(
                                                contract.total_accrued,
                                            )
                                        }}</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                    <div class="text-xs text-muted-foreground">
                                        Locação
                                        {{
                                            formatCurrency(
                                                contract.rental_total,
                                            )
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Até
                                        {{ contract.calculated_until || '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    contractsShow(contract.id)
                                                "
                                                >Ver</Link
                                            >
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    contractsEdit(contract.id)
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
