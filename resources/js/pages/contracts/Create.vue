<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ContractForm from '@/components/contracts/ContractForm.vue';
import { Button } from '@/components/ui/button';
import { index as contractsIndex } from '@/routes/contracts';
import type { BillingPeriodOption, ClientTypeOption, ContractClientOption, ContractProductOption } from '@/types';

type Props = {
    clients: ContractClientOption[];
    clientTypes: ClientTypeOption[];
    products: ContractProductOption[];
    billingPeriods: BillingPeriodOption[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contratos',
                href: contractsIndex(),
            },
            {
                title: 'Novo contrato',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Novo contrato" />

        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Novo contrato</h1>
                <p class="text-sm text-muted-foreground">Crie um contrato com cliente, endereço e itens comerciais.</p>
            </div>

            <Button variant="outline" as-child>
                <Link :href="contractsIndex()">Voltar</Link>
            </Button>
        </div>

        <ContractForm :client-types="clientTypes" :clients="clients" :products="products" :billing-periods="billingPeriods" />
    </div>
</template>
