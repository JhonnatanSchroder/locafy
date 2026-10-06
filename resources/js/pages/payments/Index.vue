<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Banknote, CalendarDays, Receipt, Search } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/dates';
import { money } from '@/types/charge';
import type { Payment } from '@/types/charge';
type Filters = {search:string;method:string;from:string;to:string};
const props = defineProps<{payments:{data:(Payment & {client:string;contract_id:number})[];links:{url:string|null;label:string;active:boolean}[];total:number;from:number|null;to:number|null};summary:{today:string;month:string;count:number;filtered_total:string};filters:Filters}>();
const filters = reactive({...props.filters});
const methods:Record<string,string>={PIX:'PIX',CASH:'Dinheiro',CARD:'Cartão',TRANSFER:'Transferência',OTHER:'Outro'};
function search() {router.get('/pagamentos',filters,{preserveState:true,preserveScroll:true,replace:true});}
defineOptions({layout:{breadcrumbs:[{title:'Pagamentos',href:'/pagamentos'}]}});
</script>
<template>
    <Head title="Pagamentos"/>
    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b pb-5"><div><h1 class="text-2xl font-semibold tracking-tight">Pagamentos</h1><p class="mt-1 text-sm text-muted-foreground">Recebimentos da sua empresa, com histórico por contrato.</p></div><Button variant="outline" as-child><Link href="/contracts">Registrar no contrato</Link></Button></header>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-5"><p class="flex items-center justify-between text-sm text-muted-foreground">Recebido hoje <CalendarDays class="size-4"/></p><p class="mt-3 text-2xl font-semibold tabular-nums">{{ money(summary.today) }}</p></div>
            <div class="rounded-xl border bg-card p-5"><p class="flex items-center justify-between text-sm text-muted-foreground">Recebido no mês <Banknote class="size-4"/></p><p class="mt-3 text-2xl font-semibold tabular-nums">{{ money(summary.month) }}</p></div>
            <div class="rounded-xl border bg-card p-5"><p class="text-sm text-muted-foreground">Pagamentos na seleção</p><p class="mt-3 text-2xl font-semibold tabular-nums">{{ summary.count }}</p></div>
            <div class="rounded-xl border border-primary/20 bg-primary/5 p-5"><p class="text-sm text-primary">Total da seleção</p><p class="mt-3 text-2xl font-bold tabular-nums text-primary">{{ money(summary.filtered_total) }}</p></div>
        </div>
        <form class="grid items-end gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 xl:grid-cols-5" @submit.prevent="search">
            <div class="grid gap-2"><Label for="search">Cliente ou contrato</Label><Input id="search" v-model="filters.search" placeholder="Nome ou número"/></div>
            <div class="grid gap-2"><Label for="from">De</Label><Input id="from" v-model="filters.from" type="date"/></div>
            <div class="grid gap-2"><Label for="to">Até</Label><Input id="to" v-model="filters.to" type="date" :min="filters.from || undefined"/></div>
            <div class="grid gap-2"><Label for="method">Método</Label><select id="method" v-model="filters.method" class="h-9 rounded-md border bg-background px-3"><option value="">Todos</option><option v-for="(label,value) in methods" :key="value" :value="value">{{ label }}</option></select></div>
            <div class="flex gap-2"><Button type="submit"><Search class="mr-1 size-4"/> Filtrar</Button><Button variant="outline" as-child><Link href="/pagamentos">Limpar</Link></Button></div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card"><table class="w-full text-sm"><thead class="border-b bg-muted/50 text-left text-muted-foreground"><tr><th class="px-4 py-3 font-medium">Data</th><th class="px-4 py-3 font-medium">Cliente</th><th class="px-4 py-3 font-medium">Contrato</th><th class="px-4 py-3 font-medium">Valor</th><th class="px-4 py-3 font-medium">Método</th><th class="px-4 py-3 font-medium">Observação</th><th class="px-4 py-3 text-right font-medium">Ações</th></tr></thead><tbody>
            <tr v-for="payment in payments.data" :key="payment.id" class="border-b last:border-0 hover:bg-muted/30"><td class="whitespace-nowrap px-4 py-4">{{ formatDateTime(payment.paid_at) }}</td><td class="px-4 py-4 font-medium">{{ payment.client }}</td><td class="px-4 py-4">#{{ payment.contract_id }}</td><td class="whitespace-nowrap px-4 py-4 font-semibold tabular-nums text-primary">{{ money(payment.amount) }}</td><td class="px-4 py-4"><Badge variant="muted">{{ methods[payment.method] ?? payment.method }}</Badge></td><td class="max-w-xs whitespace-pre-wrap break-words px-4 py-4 text-muted-foreground">{{ payment.notes || '—' }}</td><td class="px-4 py-4 text-right"><Button variant="outline" size="sm" as-child><Link :href="`/contracts/${payment.contract_id}`">Ver contrato</Link></Button></td></tr>
            <tr v-if="!payments.data.length"><td colspan="7" class="px-4 py-14 text-center"><Receipt class="mx-auto mb-3 size-8 text-muted-foreground"/><p class="font-semibold">Nenhum pagamento nesta seleção</p><p class="mt-1 text-muted-foreground">Ajuste os filtros ou registre um recebimento em um contrato.</p></td></tr>
        </tbody></table></div>
        <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-muted-foreground">{{ payments.from ?? 0 }}–{{ payments.to ?? 0 }} de {{ payments.total }} pagamentos</p><div v-if="payments.links.length > 3" class="flex flex-wrap gap-2"><template v-for="link in payments.links" :key="link.label"><Button v-if="link.url" :variant="link.active?'default':'outline'" size="sm" as-child><Link :href="link.url" preserve-state preserve-scroll v-html="link.label"/></Button><Button v-else variant="outline" size="sm" disabled v-html="link.label"/></template></div></div>
    </div>
</template>
