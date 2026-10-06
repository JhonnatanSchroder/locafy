<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Banknote, ReceiptText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import FinancialSummary from '@/components/finance/FinancialSummary.vue';
import PaymentDialog from '@/components/finance/PaymentDialog.vue';
import { formatDate, formatDateTime } from '@/lib/dates';
import { money, chargeTiming } from '@/types/charge';
import type { Receivable } from '@/types/charge';
defineProps<{charge:Receivable}>();
const open = ref(false);
const methods:Record<string,string> = {PIX:'PIX',CASH:'Dinheiro',CARD:'Cartão',TRANSFER:'Transferência',OTHER:'Outro'};
defineOptions({layout:{breadcrumbs:[{title:'Cobranças',href:'/charges'},{title:'Financeiro do contrato',href:'#'}]}});
</script>
<template>
    <Head :title="`Financeiro · Contrato #${charge.contract_id}`"/>
    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4"><div><Link href="/charges" class="mb-3 inline-flex items-center gap-1 text-xs text-muted-foreground"><ArrowLeft class="size-3"/> Cobranças</Link><h1 class="text-2xl font-semibold tracking-tight">{{ charge.client }}</h1><p class="mt-2 text-sm text-muted-foreground">Financeiro do contrato #{{ charge.contract_id }} · Cobrança {{ formatDate(charge.next_charge_date) }}</p></div><div class="flex flex-wrap gap-2"><Badge v-if="charge.days_overdue" variant="warning">{{ chargeTiming(charge) }}</Badge><Badge v-if="charge.financial_status === 'PAID'" variant="success">QUITADO</Badge><Badge v-else-if="charge.financial_status === 'PARTIAL'" variant="muted">PARCIAL</Badge><Button v-if="charge.balance && charge.balance !== '0.00' && !['FINALIZED','CANCELLED'].includes(charge.contract_status)" @click="open = true"><Banknote class="mr-2 size-4"/> Registrar pagamento</Button><Button variant="outline" as-child><Link :href="`/contracts/${charge.contract_id}`">Ver contrato</Link></Button></div></header>
        <FinancialSummary :rental-total="charge.rental_total" :freight-total="charge.freight_total" :total-accrued="charge.total_accrued" :total-paid="charge.total_paid" :total-discount="charge.total_discount" :balance="charge.balance"/>
        <Card class="rounded-xl"><CardHeader><CardTitle class="text-base">Pagamentos registrados</CardTitle><p class="text-xs text-muted-foreground">Todos os pagamentos deste contrato, incluindo registros vinculados a cobranças históricas.</p></CardHeader><CardContent class="p-0"><div class="overflow-x-auto"><table v-if="charge.payments?.length" class="w-full text-left text-sm"><thead class="border-y bg-muted/30 text-xs text-muted-foreground"><tr><th class="px-5 py-3 font-medium">Data e hora</th><th class="px-5 py-3 font-medium">Método</th><th class="px-5 py-3 font-medium">Observações</th><th class="px-5 py-3 text-right font-medium">Recebido</th><th class="px-5 py-3 text-right font-medium">Desconto</th><th class="px-5 py-3 text-right font-medium">Abatimento</th></tr></thead><tbody class="divide-y"><tr v-for="payment in charge.payments" :key="payment.id"><td class="whitespace-nowrap px-5 py-4">{{ formatDateTime(payment.paid_at) }}</td><td class="px-5 py-4"><Badge variant="muted">{{ methods[payment.method] }}</Badge></td><td class="px-5 py-4 text-muted-foreground">{{ payment.notes || '—' }}</td><td class="px-5 py-4 text-right font-semibold tabular-nums">{{ money(payment.amount) }}</td><td class="px-5 py-4 text-right tabular-nums">{{ money(payment.discount_amount) }}</td><td class="px-5 py-4 text-right font-semibold tabular-nums">{{ money(payment.settled_amount) }}</td></tr></tbody></table></div><div v-if="!charge.payments?.length" class="py-12 text-center"><ReceiptText class="mx-auto mb-3 size-7 text-muted-foreground"/><p class="text-sm font-medium">Nenhum pagamento registrado</p><p class="mt-1 text-xs text-muted-foreground">Os recebimentos aparecerão aqui após o registro.</p></div></CardContent></Card>
        <p v-if="charge.notes" class="rounded-xl border bg-card p-5 text-sm text-muted-foreground">{{ charge.notes }}</p>
        <PaymentDialog v-model:open="open" :receivable="charge"/>
    </div>
</template>
