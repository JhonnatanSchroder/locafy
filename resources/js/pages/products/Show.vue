<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    edit as productsEdit,
    index as productsIndex,
} from '@/routes/products';
import type { Product } from '@/types';
import type { BadgeVariants } from '@/components/ui/badge';

type Props = {
    product: Product;
};

defineProps<Props>();

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
            {
                title: 'Detalhes do produto',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="product.name" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ product.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Dados do produto e estoque-base.
                </p>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="productsIndex()">Voltar</Link>
                </Button>
                <Button as-child>
                    <Link :href="productsEdit(product.id)">Editar</Link>
                </Button>
            </div>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="text-base">Informações</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Nome
                        </dt>
                        <dd>{{ product.name }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Tipo
                        </dt>
                        <dd>{{ product.type_label }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Valor padrão
                        </dt>
                        <dd>{{ formatCurrency(product.default_price) }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Unidade
                        </dt>
                        <dd>{{ product.unit || '—' }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Estoque total
                        </dt>
                        <dd>{{ formatStock(product) }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Disponível
                        </dt>
                        <dd>{{ product.available ?? '—' }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Status
                        </dt>
                        <dd>
                            <Badge :variant="productStatusVariant(product)">
                                {{ product.active_label }}
                            </Badge>
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
