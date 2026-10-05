<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    create as productsCreate,
    edit as productsEdit,
    index as productsIndex,
    show as productsShow,
} from '@/routes/products';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { BadgeVariants } from '@/components/ui/badge';
import type { Product, ProductType, ProductTypeOption } from '@/types';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProducts = {
    data: Product[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    products: PaginatedProducts;
    filters: {
        search: string;
        type: string;
        active: string;
    };
    productTypes: ProductTypeOption[];
};

const props = defineProps<Props>();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
const active = ref(props.filters.active);

const submitFilters = () => {
    router.get(
        productsIndex.url(),
        {
            search: search.value || undefined,
            type: type.value || undefined,
            active: active.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const formatCurrency = (value: string | null) => {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value));
};

const formatStock = (product: Product) => {
    if (product.type === 'INDIVIDUAL') {
        return '—';
    }

    return product.stock_total?.toString() ?? '0';
};

const productStatusVariant = (product: Product): BadgeVariants['variant'] =>
    product.active ? 'success' : 'muted';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Produtos',
                href: productsIndex(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Produtos" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Produtos</h1>
                <p class="text-sm text-muted-foreground">
                    Gerencie o catálogo e o estoque-base da sua empresa.
                </p>
            </div>

            <Button as-child>
                <Link :href="productsCreate()">Novo produto</Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Buscar produtos</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px_auto]"
                    @submit.prevent="submitFilters"
                >
                    <Input
                        v-model="search"
                        name="search"
                        placeholder="Buscar por nome"
                    />

                    <select
                        v-model="type"
                        name="type"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos os tipos</option>
                        <option
                            v-for="productType in productTypes"
                            :key="productType.value"
                            :value="productType.value"
                        >
                            {{ productType.label }}
                        </option>
                    </select>

                    <select
                        v-model="active"
                        name="active"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos os status</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>

                    <div class="flex gap-2">
                        <Button type="submit">Filtrar</Button>
                        <Button
                            v-if="
                                filters.search || filters.type || filters.active
                            "
                            variant="outline"
                            as-child
                        >
                            <Link :href="productsIndex()">Limpar</Link>
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
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 font-medium">
                                    Preço padrão
                                </th>
                                <th class="px-4 py-3 font-medium">Unidade</th>
                                <th class="px-4 py-3 font-medium">
                                    Estoque total
                                </th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="products.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Nenhum produto encontrado.
                                </td>
                            </tr>
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                                class="border-b transition-colors hover:bg-muted/40 last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ product.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ product.type_label }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatCurrency(product.default_price) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ product.unit || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatStock(product) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="productStatusVariant(product)"
                                    >
                                        {{ product.active_label }}
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
                                                :href="productsShow(product.id)"
                                                >Ver</Link
                                            >
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="productsEdit(product.id)"
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
            v-if="products.links.length > 3"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Mostrando {{ products.from }} a {{ products.to }} de
                {{ products.total }} produtos
            </p>
            <div class="flex flex-wrap gap-2">
                <template v-for="link in products.links" :key="link.label">
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
