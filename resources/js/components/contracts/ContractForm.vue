<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InlineClientDialog from '@/components/clients/InlineClientDialog.vue';
import { useInlineClientOptions } from '@/lib/inline-client';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as contractsIndex,
    store as contractsStore,
    update as contractsUpdate,
} from '@/routes/contracts';
import type {
    BillingPeriod,
    BillingPeriodOption,
    Contract,
    ClientTypeOption,
    ContractClientOption,
    ContractProductOption,
    ContractStatusOption,
} from '@/types';

type ContractItemForm = {
    id?: number | null;
    product_id: number | string;
    billing_period: BillingPeriod;
    unit_price: string;
    initial_quantity?: number | string;
};

type ContractFormData = {
    client_id: number | string;
    status: string;
    worksite_address: string;
    started_at: string;
    ended_at: string;
    charge_saturdays: boolean;
    next_charge_date: string;
    charge_interval_days: number;
    notes: string;
    initial_freight?: {
        quantity: number | string;
        unit_amount: string;
        notes: string;
    };
    items: ContractItemForm[];
};

type Props = {
    contract?: Contract;
    clients: ContractClientOption[];
    clientTypes?: ClientTypeOption[];
    products: ContractProductOption[];
    billingPeriods: BillingPeriodOption[];
    contractStatuses?: ContractStatusOption[];
};

const props = defineProps<Props>();

const form = useForm<ContractFormData>({
    client_id: props.contract?.client.id ?? '',
    status: props.contract?.status ?? 'ACTIVE',
    worksite_address: props.contract?.worksite_address ?? '',
    started_at: props.contract?.started_at ?? '',
    ended_at: props.contract?.ended_at ?? '',
    charge_saturdays: props.contract?.charge_saturdays ?? true,
    next_charge_date: props.contract?.next_charge_date ?? '',
    charge_interval_days: props.contract?.charge_interval_days ?? 15,
    notes: props.contract?.notes ?? '',
    initial_freight: props.contract ? undefined : {
        quantity: 0,
        unit_amount: '',
        notes: '',
    },
    items: props.contract?.items.map((item): ContractItemForm => ({
        id: item.id,
        product_id: item.product.id,
        billing_period: item.billing_period,
        unit_price: item.unit_price,
    })) ?? [
        {
            id: null,
            product_id: '',
            initial_quantity: 0,
            billing_period: 'DAY',
            unit_price: '',
        },
    ],
});

const clientDialogOpen = ref(false);
const selectedPhotos = ref<Array<{ file: File; url: string }>>([]);
const { options: clientOptions, created: clientCreated } = useInlineClientOptions(() => props.clients, id => {form.client_id = id});

const title = computed(() =>
    props.contract ? 'Editar contrato' : 'Novo contrato',
);
const submitLabel = computed(() =>
    props.contract ? 'Salvar contrato' : 'Criar contrato',
);

const selectedProduct = (productId: number | string) =>
    props.products.find((product) => product.id === Number(productId));
const periodOptionsFor = (productId: number | string) => {
    const product = selectedProduct(productId);

    if (product?.type === 'QUANTITY') {
        return props.billingPeriods.filter((period) => period.value === 'DAY');
    }

    return props.billingPeriods;
};

const addItem = () => {
    form.items.push({
        id: null,
        product_id: '',
        initial_quantity: 0,
        billing_period: 'DAY',
        unit_price: '',
    });
};

const removeItem = (index: number) => {
    if (form.items.length === 1) {
        return;
    }

    form.items.splice(index, 1);
};

const onProductChange = (item: ContractItemForm) => {
    const product = selectedProduct(item.product_id);

    if (product?.type === 'QUANTITY') {
        item.billing_period = 'DAY';
        item.initial_quantity ??= 0;
    } else {
        delete item.initial_quantity;
    }

    if (
        product?.default_price !== null &&
        product?.default_price !== undefined
    ) {
        item.unit_price = product.default_price;
    }
};

const isQuantityProduct = (productId: number | string) =>
    selectedProduct(productId)?.type === 'QUANTITY';

const fieldError = (field: string) =>
    form.errors[field as keyof typeof form.errors] as string | undefined;

const addPhotos = (files: FileList | null) => {
    if (!files) return;

    for (const file of Array.from(files)) {
        if (selectedPhotos.value.length >= 10) break;
        selectedPhotos.value.push({ file, url: URL.createObjectURL(file) });
    }
};

const removePhoto = (index: number) => {
    const [photo] = selectedPhotos.value.splice(index, 1);
    if (photo) URL.revokeObjectURL(photo.url);
};

const uploadSelectedPhotos = (contractId: number) => {
    if (selectedPhotos.value.length === 0) return;

    router.post(
        `/contracts/${contractId}/attachments`,
        { attachments: selectedPhotos.value.map(photo => photo.file) },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                selectedPhotos.value.forEach(photo => URL.revokeObjectURL(photo.url));
                selectedPhotos.value = [];
            },
        },
    );
};

const submit = () => {
    if (props.contract) {
        form.transform(({initial_freight: _freight, ...data}) => data).put(contractsUpdate.url(props.contract.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(contractsStore.url(), {
        preserveScroll: true,
        onSuccess: page => {
            const contractId = (page.props as { contract?: { id: number } }).contract?.id;
            if (contractId) uploadSelectedPhotos(contractId);
        },
    });
};
</script>

<template>
    <InlineClientDialog v-if="!contract" v-model:open="clientDialogOpen" :client-types="clientTypes ?? []" @created="clientCreated"/>
    <form class="space-y-5" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Dados do contrato</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between"><Label for="client_id">Cliente</Label><Button v-if="!contract" type="button" variant="outline" size="sm" @click="clientDialogOpen = true">+ Novo cliente</Button></div>
                        <select
                            id="client_id"
                            v-model="form.client_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" disabled>
                                Selecione um cliente
                            </option>
                            <option
                                v-for="client in clientOptions"
                                :key="client.id"
                                :value="client.id"
                            >
                                {{ client.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.client_id" />
                    </div>

                    <div v-if="contract" class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="status in contractStatuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="worksite_address">Endereço da obra</Label>
                    <textarea
                        id="worksite_address"
                        v-model="form.worksite_address"
                        rows="3"
                        class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Endereço onde o contrato será executado"
                    />
                    <InputError :message="form.errors.worksite_address" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="started_at">Início</Label>
                        <Input
                            id="started_at"
                            v-model="form.started_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="form.errors.started_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="next_charge_date">Próxima cobrança</Label>
                        <Input
                            id="next_charge_date"
                            v-model="form.next_charge_date"
                            type="date"
                        />
                        <InputError :message="form.errors.next_charge_date" />
                    </div>

                    <div class="grid gap-2"><Label for="charge_interval_days">Intervalo entre cobranças (dias)</Label><Input id="charge_interval_days" v-model="form.charge_interval_days" type="number" min="1" max="365" required/><InputError :message="form.errors.charge_interval_days"/></div>
                    <div v-if="contract" class="grid gap-2">
                        <Label for="ended_at">Fim</Label>
                        <Input
                            id="ended_at"
                            v-model="form.ended_at"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.ended_at" />
                    </div>

                    <div class="flex items-end pb-2">
                        <label
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <input
                                v-model="form.charge_saturdays"
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                            />
                            Cobrar sábado
                        </label>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Observação</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Observações internas"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </CardContent>
        </Card>

        <Card v-if="!contract">
            <CardHeader>
                <CardTitle class="text-base">Fotos do contrato</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">{{ selectedPhotos.length }} / 10 fotos</p>
                    <label class="inline-flex cursor-pointer items-center rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted">
                        + Adicionar fotos
                        <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="addPhotos(($event.target as HTMLInputElement).files)" />
                    </label>
                </div>
                <div v-if="selectedPhotos.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(photo, index) in selectedPhotos" :key="photo.url" class="overflow-hidden rounded-lg border">
                        <img :src="photo.url" :alt="photo.file.name" class="aspect-video w-full object-cover" />
                        <div class="space-y-2 p-3">
                            <p class="truncate text-sm font-medium">{{ photo.file.name }}</p>
                            <Button type="button" variant="outline" size="sm" class="w-full" @click="removePhoto(index)">Remover</Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="!contract">
            <CardHeader>
                <CardTitle class="text-base">Frete inicial</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="initial_freight_quantity"
                            >Quantidade</Label
                        >
                        <Input
                            id="initial_freight_quantity"
                            v-model="form.initial_freight!.quantity"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                        />
                        <InputError
                            :message="fieldError('initial_freight.quantity')"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="initial_freight_unit_amount"
                            >Valor unitário</Label
                        >
                        <Input
                            id="initial_freight_unit_amount"
                            v-model="form.initial_freight!.unit_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0,00"
                        />
                        <InputError
                            :message="
                                fieldError('initial_freight.unit_amount')
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="initial_freight_notes"
                        >Observação do frete</Label
                    >
                    <textarea
                        id="initial_freight_notes"
                        v-model="form.initial_freight!.notes"
                        rows="3"
                        class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError
                        :message="fieldError('initial_freight.notes')"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-base">Itens do contrato</CardTitle>
                <Button type="button" variant="outline" @click="addItem"
                    >Adicionar item</Button
                >
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b bg-muted/60 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="min-w-72 px-4 py-3 font-medium">
                                    Produto
                                </th>
                                <th
                                    v-if="!contract"
                                    class="min-w-40 px-4 py-3 font-medium"
                                >
                                    Quantidade inicial
                                </th>
                                <th class="min-w-44 px-4 py-3 font-medium">
                                    Período
                                </th>
                                <th class="min-w-40 px-4 py-3 font-medium">
                                    Preço
                                </th>
                                <th
                                    class="w-32 px-4 py-3 text-right font-medium"
                                >
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in form.items"
                                :key="item.id ?? `new-${index}`"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <select
                                        v-model="item.product_id"
                                        required
                                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                        @change="onProductChange(item)"
                                    >
                                        <option value="" disabled>
                                            Selecione um produto
                                        </option>
                                        <option
                                            v-for="product in products"
                                            :key="product.id"
                                            :value="product.id"
                                        >
                                            {{ product.name }} ·
                                            {{ product.type_label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="
                                            fieldError(
                                                `items.${index}.product_id`,
                                            )
                                        "
                                    />
                                </td>
                                <td
                                    v-if="!contract"
                                    class="px-4 py-3 align-top"
                                >
                                    <Input
                                        v-if="
                                            isQuantityProduct(item.product_id)
                                        "
                                        v-model="item.initial_quantity"
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="0"
                                    />
                                    <span
                                        v-else
                                        class="flex h-9 items-center text-muted-foreground"
                                        >—</span
                                    >
                                    <InputError
                                        :message="
                                            fieldError(
                                                `items.${index}.initial_quantity`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <select
                                        v-model="item.billing_period"
                                        required
                                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option
                                            v-for="period in periodOptionsFor(
                                                item.product_id,
                                            )"
                                            :key="period.value"
                                            :value="period.value"
                                        >
                                            {{ period.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="
                                            fieldError(
                                                `items.${index}.billing_period`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Input
                                        v-model="item.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        placeholder="0,00"
                                    />
                                    <InputError
                                        :message="
                                            fieldError(
                                                `items.${index}.unit_price`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="form.items.length === 1"
                                        @click="removeItem(index)"
                                    >
                                        Remover
                                    </Button>
                                    <InputError
                                        :message="
                                            fieldError(`items.${index}.id`)
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError class="px-4 py-3" :message="form.errors.items" />
            </CardContent>
        </Card>

        <div class="flex items-center gap-3">
            <Button :disabled="form.processing">{{ submitLabel }}</Button>
            <Button variant="outline" as-child>
                <Link :href="contractsIndex()">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
