<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import FinancialSummary from '@/components/finance/FinancialSummary.vue';
import InputError from '@/components/InputError.vue';
import { formatDate, formatDateTime } from '@/lib/dates';
import { money, statuses } from '@/types/charge';
import type { Charge } from '@/types/charge';
defineProps<{charge:Charge & {contract_finance:{rental_total:string|null;freight_total:string;total_accrued:string|null;total_paid:string;total_discount:string;balance:string|null}}}>();
const cancel = useForm({});
defineOptions({layout:{breadcrumbs:[{title:'Cobranças',href:'/charges'},{title:'Histórico',href:'/charges/history'},{title:'Registro',href:'#'}]}});
</script>
<template><Head :title="`Registro histórico #${charge.id}`"/><div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"><header class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-semibold">Registro histórico #{{ charge.id }}</h1><p class="mt-2 text-sm text-muted-foreground">{{ charge.client }} · Contrato #{{ charge.contract_id }}</p></div><Button variant="outline" as-child><Link :href="`/charges/${charge.contract_id}`">Financeiro atual do contrato</Link></Button></header>
<div class="rounded-xl border bg-card p-5"><Badge variant="muted">{{ statuses[charge.status] }}</Badge><p class="mt-3 text-sm">Vencimento registrado: {{ formatDate(charge.due_date) }}</p><p class="mt-2 text-lg font-semibold">Valor do snapshot: {{ money(charge.total_amount) }}</p><p class="mt-1 text-sm text-muted-foreground">Locação {{ money(charge.rental_amount) }} · Fretes {{ money(charge.freight_amount) }}</p><p class="mt-3 text-xs text-muted-foreground">Este registro preserva o faturamento anterior. O saldo atual é calculado pelo contrato.</p></div>
<FinancialSummary :rental-total="charge.contract_finance.rental_total" :freight-total="charge.contract_finance.freight_total" :total-accrued="charge.contract_finance.total_accrued" :total-paid="charge.contract_finance.total_paid" :total-discount="charge.contract_finance.total_discount" :balance="charge.contract_finance.balance"/>
<div class="rounded-xl border bg-card p-5"><h2 class="mb-3 font-semibold">Pagamentos vinculados a este registro</h2><p v-if="!charge.payments.length" class="text-sm text-muted-foreground">Nenhum pagamento vinculado.</p><div v-for="payment in charge.payments" :key="payment.id" class="flex justify-between gap-3 border-b py-3 text-sm last:border-0"><span>{{ formatDateTime(payment.paid_at) }} · {{ payment.method }} · Desconto {{ money(payment.discount_amount) }}</span><strong class="tabular-nums">{{ money(payment.settled_amount) }}</strong></div></div>
<div v-if="charge.status === 'PENDING' && !charge.payments.length"><Button variant="outline" :disabled="cancel.processing" @click="cancel.patch(`/charges/${charge.id}/cancel`)">Cancelar registro histórico</Button><InputError :message="Object.values(cancel.errors).join(' ')"/></div></div></template>
