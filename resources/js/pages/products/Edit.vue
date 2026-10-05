<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as productsIndex,
    show as productsShow,
} from '@/routes/products';
import type { Product, ProductType, ProductTypeOption } from '@/types';

type Props = {
    product: Product;
    productTypes: ProductTypeOption[];
};

const props = defineProps<Props>();
const selectedType = ref<ProductType>(props.product.type);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Produtos',
                href: productsIndex(),
            },
            {
                title: 'Editar produto',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="`Editar ${product.name}`" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Editar produto
                </h1>
                <p class="text-sm text-muted-foreground">
                    Atualize os dados de {{ product.name }}.
                </p>
            </div>

            <Button variant="outline" as-child>
                <Link :href="productsShow(product.id)">Voltar</Link>
            </Button>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="text-base">Dados do produto</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ProductController.update.form(product.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nome</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            placeholder="Nome do produto"
                            :default-value="product.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">Tipo</Label>
                        <select
                            id="type"
                            v-model="selectedType"
                            name="type"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="productType in productTypes"
                                :key="productType.value"
                                :value="productType.value"
                            >
                                {{ productType.label }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="default_price">Valor padrão</Label>
                            <Input
                                id="default_price"
                                name="default_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0,00"
                                :default-value="product.default_price ?? ''"
                            />
                            <InputError :message="errors.default_price" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="unit">Unidade</Label>
                            <Input
                                id="unit"
                                name="unit"
                                placeholder="peça, unidade, máquina..."
                                :default-value="product.unit ?? ''"
                            />
                            <InputError :message="errors.unit" />
                        </div>
                    </div>

                    <div v-if="selectedType === 'QUANTITY'" class="grid gap-2">
                        <Label for="stock_total">Estoque total</Label>
                        <Input
                            id="stock_total"
                            name="stock_total"
                            type="number"
                            min="0"
                            step="1"
                            required
                            placeholder="0"
                            :default-value="product.stock_total ?? 0"
                        />
                        <InputError :message="errors.stock_total" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="active"
                            name="active"
                            type="checkbox"
                            value="1"
                            :checked="product.active"
                            class="h-4 w-4 rounded border-input"
                        />
                        <Label for="active">Produto ativo</Label>
                        <InputError :message="errors.active" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="processing"
                            >Salvar alterações</Button
                        >
                        <Button variant="outline" as-child>
                            <Link :href="productsShow(product.id)"
                                >Cancelar</Link
                            >
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
