<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowUpRight, Banknote, ReceiptText, Search, Wallet } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import PaymentDialog from '@/components/finance/PaymentDialog.vue';
import { formatDate } from '@/lib/dates';
import { money, chargeTiming } from '@/types/charge';
import type { Receivable } from '@/types/charge';
const props = defineProps<{charges:{data:Receivable[];links:{url:string|null;label:string;active:boolean}[];total:number};filter:string;search:string;summary:{count:number;balance:string}}>();
const search = ref(props.search);
const selected = ref<Receivable|null>(null);
const open = ref(false);
const filters = [{value:'today',label:'Hoje'},{value:'overdue',label:'Atrasadas'},{value:'upcoming',label:'Próximas'},{value:'',label:'Todas'}];
function pay(row:Receivable) {selected.value = row;open.value = true;}
function applyFilter(value:string) {router.get('/charges',{filter:value,search:search.value},{preserveState:true,replace:true});}
defineOptions({layout:{breadcrumbs:[{title:'Cobranças',href:'/charges'}]}});
</script>
<template>
    <Head title="Cobranças"/>
    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4"><div><p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Financeiro</p><h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Cobranças</h1><p class="mt-2 text-sm text-muted-foreground">O saldo atual dos contratos, sempre atualizado.</p></div><Button variant="outline" as-child><Link href="/charges/history"><ReceiptText class="mr-2 size-4"/> Histórico de cobranças</Link></Button></header>
        <div class="grid gap-4 sm:grid-cols-2"><div class="rounded-xl border border-primary/20 bg-primary/5 p-5"><div class="flex items-center gap-2 text-sm text-primary"><Wallet class="size-4"/> Saldo total a receber</div><p class="mt-2 text-3xl font-bold tabular-nums text-primary">{{ money(summary.balance) }}</p><p class="mt-1 text-xs text-muted-foreground">Contratos ativos e devolvidos com saldo</p></div><div class="rounded-xl border bg-card p-5"><p class="text-sm text-muted-foreground">Contratos nesta seleção</p><p class="mt-2 text-3xl font-semibold tabular-nums">{{ summary.count }}</p><p class="mt-1 text-xs text-muted-foreground">Os mais antigos aparecem primeiro</p></div></div>
        <div class="rounded-xl border bg-card"><div class="flex flex-wrap items-center justify-between gap-4 border-b p-4"><nav class="flex gap-1 rounded-lg bg-muted p-1"><button v-for="f in filters" :key="f.value" type="button" class="rounded-md px-3 py-2 text-sm transition-colors" :class="props.filter === f.value ? 'bg-background font-semibold text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="applyFilter(f.value)">{{ f.label }}</button></nav><form class="flex gap-2" @submit.prevent="applyFilter(props.filter)"><Input v-model="search" placeholder="Cliente ou contrato" class="sm:w-64" aria-label="Buscar cobranças"/><Button variant="outline" type="submit" aria-label="Buscar"><Search class="size-4"/></Button></form></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-muted/30 text-xs uppercase tracking-wide text-muted-foreground"><tr><th class="px-5 py-3 font-medium">Cliente / contrato</th><th class="px-5 py-3 font-medium">Cobrança</th><th class="px-5 py-3 font-medium text-right">Acumulado</th><th class="px-5 py-3 font-medium text-right">Pago</th><th class="px-5 py-3 font-medium text-right">Saldo a receber</th><th class="px-5 py-3 font-medium">Status</th><th class="px-5 py-3"/></tr></thead><tbody class="divide-y">
                <tr v-for="row in charges.data" :key="row.id" class="transition-colors hover:bg-muted/20"><td class="px-5 py-5"><Link :href="`/charges/${row.id}`" class="font-semibold hover:text-primary">{{ row.client }}</Link><Link :href="`/contracts/${row.contract_id}`" class="mt-1 block text-xs text-muted-foreground">Contrato #{{ row.contract_id }} <ArrowUpRight class="inline size-3"/></Link></td><td class="whitespace-nowrap px-5 py-5"><p>{{ formatDate(row.next_charge_date) }}</p><p v-if="row.days_overdue" class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ chargeTiming(row) }}</p><p v-else-if="row.contract_status === 'RETURNED'" class="mt-1 text-xs text-muted-foreground">Devolvido · saldo pendente</p></td><td class="px-5 py-5 text-right tabular-nums">{{ money(row.total_accrued) }}</td><td class="px-5 py-5 text-right tabular-nums text-muted-foreground">{{ money(row.total_paid) }}</td><td class="px-5 py-5 text-right text-lg font-bold tabular-nums text-primary">{{ money(row.balance) }}</td><td class="px-5 py-5"><div class="flex flex-wrap gap-1"><Badge v-if="row.days_overdue" variant="warning">ATRASADO</Badge><Badge v-else-if="row.due_today" variant="info">HOJE</Badge><Badge v-else variant="muted">{{ row.contract_status === 'RETURNED' ? 'DEVOLVIDO' : 'PRÓXIMA' }}</Badge><Badge v-if="row.financial_status === 'PARTIAL'" variant="muted">PARCIAL</Badge></div></td><td class="px-5 py-5"><Button size="sm" @click="pay(row)"><Banknote class="mr-1 size-4"/> Pagar</Button></td></tr>
            </tbody></table></div>
            <div v-if="!charges.data.length" class="px-6 py-16 text-center"><ReceiptText class="mx-auto mb-4 size-9 text-muted-foreground"/><h2 class="font-semibold">Nenhuma cobrança nesta seleção</h2><p class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">Contratos aparecem conforme a data de cobrança e o saldo atual. Contratos devolvidos com saldo também aparecem.</p><Button variant="outline" class="mt-5" as-child><Link href="/contracts">Ver contratos</Link></Button></div>
            <footer v-if="charges.total" class="flex flex-wrap items-center justify-between gap-3 border-t p-4"><span class="text-xs text-muted-foreground">{{ charges.total }} contrato(s)</span><nav class="flex gap-1"><template v-for="(link,i) in charges.links" :key="i"><Link v-if="link.url" :href="link.url" class="rounded-md px-3 py-2 text-sm" :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"><span v-html="link.label"/></Link></template></nav></footer>
        </div>
        <PaymentDialog v-model:open="open" :receivable="selected"/>
    </div>
</template>
