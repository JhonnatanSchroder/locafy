<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as movementsIndex, store as movementsStore, update as movementsUpdate } from '@/routes/movements';
import type { Movement, MovementContract, MovementContractOption, MovementType, MovementTypeOption } from '@/types';

type Props = {
    movement?: Movement;
    contracts?: MovementContractOption[];
    selectedContract: MovementContract | null;
    selectedType?: MovementType;
    movementTypes?: MovementTypeOption[];
};

const props = defineProps<Props>();

const form = useForm({
    contract_id: props.selectedContract?.id ?? '',
    type: props.movement?.type ?? props.selectedType ?? 'WITHDRAWAL',
    occurred_at: props.movement?.occurred_at ?? '',
    notes: props.movement?.notes ?? '',
    items: props.selectedContract?.items.map((item) => ({
        contract_item_id: item.id,
        quantity: props.movement?.items.find((movementItem) => movementItem.contract_item_id === item.id)?.quantity ?? 0,
        equipment_id: null,
    })) ?? [],
});

const submit = () => {
    if (props.movement) {
        form.put(movementsUpdate.url(props.movement.id), { preserveScroll: true });
        return;
    }

    form.post(movementsStore.url(), { preserveScroll: true });
};

const fieldError = (field: string) => form.errors[field as keyof typeof form.errors] as string | undefined;
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Dados da movimentação</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="contract_id">Contrato</Label>
                        <select
                            id="contract_id"
                            v-model="form.contract_id"
                            :disabled="!!movement || !!selectedContract"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>Selecione</option>
                            <option v-for="contract in contracts" :key="contract.id" :value="contract.id">
                                #{{ contract.number }} · {{ contract.client_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.contract_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">Tipo</Label>
                        <select id="type" v-model="form.type" :disabled="!!movement" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option v-for="type in movementTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="occurred_at">Data/hora</Label>
                        <Input id="occurred_at" v-model="form.occurred_at" type="datetime-local" required />
                        <InputError :message="form.errors.occurred_at" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Observação</Label>
                    <textarea id="notes" v-model="form.notes" rows="3" class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.notes" />
                </div>
            </CardContent>
        </Card>

        <Card v-if="selectedContract">
            <CardHeader>
                <CardTitle class="text-base">Itens</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/60 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Produto</th>
                                <th class="px-4 py-3 font-medium">Atual fora</th>
                                <th class="px-4 py-3 font-medium">Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in selectedContract.items" :key="item.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ item.product.name }}</td>
                                <td class="px-4 py-3">{{ item.current_quantity }}</td>
                                <td class="px-4 py-3">
                                    <Input v-model="form.items[index].quantity" type="number" min="0" step="1" class="max-w-32" />
                                    <InputError :message="fieldError(`items.${index}.quantity`)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError class="px-4 py-3" :message="form.errors.items" />
            </CardContent>
        </Card>

        <div class="flex items-center gap-3">
            <Button :disabled="form.processing">Salvar movimentação</Button>
            <Button variant="outline" as-child>
                <Link :href="movementsIndex()">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
