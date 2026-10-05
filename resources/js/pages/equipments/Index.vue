<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    create as equipmentsCreate,
    edit as equipmentsEdit,
    index as equipmentsIndex,
    show as equipmentsShow,
} from '@/routes/equipments';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { BadgeVariants } from '@/components/ui/badge';
import type {
    Equipment,
    EquipmentProductOption,
    EquipmentStatusOption,
} from '@/types';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEquipments = {
    data: Equipment[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    equipments: PaginatedEquipments;
    filters: {
        search: string;
        product: string;
        status: string;
    };
    eligibleProducts: EquipmentProductOption[];
    equipmentStatuses: EquipmentStatusOption[];
};

const props = defineProps<Props>();
const search = ref(props.filters.search);
const product = ref(props.filters.product);
const status = ref(props.filters.status);

const submitFilters = () => {
    router.get(
        equipmentsIndex.url(),
        {
            search: search.value || undefined,
            product: product.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const equipmentStatusVariant = (
    status: Equipment['status'],
): BadgeVariants['variant'] => {
    if (status === 'AVAILABLE') {
        return 'success';
    }

    if (status === 'RENTED') {
        return 'info';
    }

    if (status === 'MAINTENANCE') {
        return 'warning';
    }

    return 'muted';
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Equipamentos',
                href: equipmentsIndex(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Equipamentos" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Equipamentos
                </h1>
                <p class="text-sm text-muted-foreground">
                    Gerencie os equipamentos físicos identificáveis da sua
                    empresa.
                </p>
            </div>

            <Button as-child>
                <Link :href="equipmentsCreate()">Novo equipamento</Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Buscar equipamentos</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_180px_auto]"
                    @submit.prevent="submitFilters"
                >
                    <Input
                        v-model="search"
                        name="search"
                        placeholder="Buscar por nome ou marca"
                    />

                    <select
                        v-model="product"
                        name="product"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos os produtos</option>
                        <option
                            v-for="eligibleProduct in eligibleProducts"
                            :key="eligibleProduct.id"
                            :value="eligibleProduct.id"
                        >
                            {{ eligibleProduct.name }}
                        </option>
                    </select>

                    <select
                        v-model="status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos os status</option>
                        <option
                            v-for="equipmentStatus in equipmentStatuses"
                            :key="equipmentStatus.value"
                            :value="equipmentStatus.value"
                        >
                            {{ equipmentStatus.label }}
                        </option>
                    </select>

                    <div class="flex gap-2">
                        <Button type="submit">Filtrar</Button>
                        <Button
                            v-if="
                                filters.search ||
                                filters.product ||
                                filters.status
                            "
                            variant="outline"
                            as-child
                        >
                            <Link :href="equipmentsIndex()">Limpar</Link>
                        </Button>
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
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">Produto</th>
                                <th class="px-4 py-3 font-medium">Marca</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="equipments.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Nenhum equipamento encontrado.
                                </td>
                            </tr>
                            <tr
                                v-for="equipment in equipments.data"
                                :key="equipment.id"
                                class="border-b transition-colors hover:bg-muted/40 last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ equipment.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ equipment.product.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ equipment.brand || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            equipmentStatusVariant(
                                                equipment.status,
                                            )
                                        "
                                    >
                                        {{ equipment.status_label }}
                                    </Badge>
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
                                                    equipmentsShow(equipment.id)
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
                                                    equipmentsEdit(equipment.id)
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
            v-if="equipments.links.length > 3"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Mostrando {{ equipments.from }} a {{ equipments.to }} de
                {{ equipments.total }} equipamentos
            </p>
            <div class="flex flex-wrap gap-2">
                <template v-for="link in equipments.links" :key="link.label">
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
