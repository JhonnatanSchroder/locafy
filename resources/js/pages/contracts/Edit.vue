<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FreightEditor from '@/components/contracts/FreightEditor.vue';
import ContractForm from '@/components/contracts/ContractForm.vue';
import ContractAttachments from '@/components/contracts/ContractAttachments.vue';
import { Button } from '@/components/ui/button';
import { index as contractsIndex, show as contractsShow } from '@/routes/contracts';
import type { BillingPeriodOption, Contract, ContractClientOption, ContractProductOption, ContractStatusOption } from '@/types';

type Props = {
    contract: Contract;
    clients: ContractClientOption[];
    products: ContractProductOption[];
    billingPeriods: BillingPeriodOption[];
    contractStatuses: ContractStatusOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Contratos',
                href: contractsIndex(),
            },
            {
                title: 'Editar contrato',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="`Editar contrato #${contract.number}`" />

        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Editar contrato #{{ contract.number }}</h1>
                <p class="text-sm text-muted-foreground">Atualize dados comerciais preservando os itens já identificados.</p>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="contractsShow(contract.id)">Ver</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="contractsIndex()">Voltar</Link>
                </Button>
            </div>
        </div>

        <h2 class="text-lg font-semibold">Fretes registrados</h2>
        <FreightEditor v-for="freight in contract.freights" :key="freight.id" :contract-id="contract.id" :freight="freight" />
        <ContractAttachments
            :contract-id="contract.id"
            :attachments="contract.attachments ?? []"
            :locked="['FINALIZED','CANCELLED'].includes(contract.status)"
        />
        <ContractForm
            :contract="props.contract"
            :clients="clients"
            :products="products"
            :billing-periods="billingPeriods"
            :contract-statuses="contractStatuses"
        />
    </div>
</template>
