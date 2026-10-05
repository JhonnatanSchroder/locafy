<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import EquipmentController from '@/actions/App/Http/Controllers/EquipmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as equipmentsIndex } from '@/routes/equipments';
import type { EquipmentProductOption, EquipmentStatusOption } from '@/types';

type Props = {
    eligibleProducts: EquipmentProductOption[];
    equipmentStatuses: EquipmentStatusOption[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Equipamentos',
                href: equipmentsIndex(),
            },
            {
                title: 'Novo equipamento',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Novo equipamento" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Novo equipamento
                </h1>
                <p class="text-sm text-muted-foreground">
                    Cadastre uma unidade física identificável.
                </p>
            </div>

            <Button variant="outline" as-child>
                <Link :href="equipmentsIndex()">Voltar</Link>
            </Button>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="text-base">Dados do equipamento</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="EquipmentController.store.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="product_id">Produto</Label>
                        <select
                            id="product_id"
                            name="product_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" disabled selected>
                                Selecione um produto
                            </option>
                            <option
                                v-for="product in eligibleProducts"
                                :key="product.id"
                                :value="product.id"
                            >
                                {{ product.name }}
                            </option>
                        </select>
                        <InputError :message="errors.product_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Nome</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            placeholder="Betoneira 01"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="brand">Marca</Label>
                            <Input
                                id="brand"
                                name="brand"
                                placeholder="Marca do equipamento"
                            />
                            <InputError :message="errors.brand" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option
                                    v-for="equipmentStatus in equipmentStatuses"
                                    :key="equipmentStatus.value"
                                    :value="equipmentStatus.value"
                                    :selected="
                                        equipmentStatus.value === 'AVAILABLE'
                                    "
                                >
                                    {{ equipmentStatus.label }}
                                </option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Observação</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Observações gerais"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="processing"
                            >Salvar equipamento</Button
                        >
                        <Button variant="outline" as-child>
                            <Link :href="equipmentsIndex()">Cancelar</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
