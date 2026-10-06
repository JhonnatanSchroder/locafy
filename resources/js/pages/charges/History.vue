<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ReceiptText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/dates';
import { money, statuses } from '@/types/charge';
import type { Charge } from '@/types/charge';
defineProps<{charges:{data:Charge[];links:{url:string|null;label:string;active:boolean}[]}}>();
defineOptions({layout:{breadcrumbs:[{title:'Cobranças',href:'/charges'},{title:'Histórico',href:'#'}]}});
</script>
<template>
    <Head title="Histórico de cobranças"/><div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"><header class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-semibold tracking-tight">Histórico de cobranças</h1><p class="mt-2 text-sm text-muted-foreground">Registros financeiros anteriores preservados. Consulte o saldo atual na tela Cobranças.</p></div><Button variant="outline" as-child><Link href="/charges">Cobranças atuais</Link></Button></header>
    <div class="overflow-x-auto rounded-xl border bg-card"><table class="w-full text-left text-sm"><thead class="border-b bg-muted/30 text-xs text-muted-foreground"><tr><th class="px-5 py-3 font-medium">Registro / contrato</th><th class="px-5 py-3 font-medium">Cliente</th><th class="px-5 py-3 font-medium">Vencimento registrado</th><th class="px-5 py-3 font-medium">Valor registrado</th><th class="px-5 py-3 font-medium">Status histórico</th></tr></thead><tbody class="divide-y"><tr v-for="row in charges.data" :key="row.id"><td class="px-5 py-4"><Link :href="`/charges/history/${row.id}`" class="font-medium hover:text-primary">#{{ row.id }} · Contrato #{{ row.contract_id }}</Link></td><td class="px-5 py-4">{{ row.client }}</td><td class="px-5 py-4">{{ formatDate(row.due_date) }}</td><td class="px-5 py-4 font-semibold tabular-nums">{{ money(row.total_amount) }}</td><td class="px-5 py-4"><Badge variant="muted">{{ statuses[row.status] }}</Badge></td></tr></tbody></table><div v-if="!charges.data.length" class="py-14 text-center"><ReceiptText class="mx-auto mb-3 size-7 text-muted-foreground"/><p class="text-sm text-muted-foreground">Nenhum registro histórico.</p></div></div>
    <nav class="flex flex-wrap gap-2"><template v-for="(link,i) in charges.links" :key="i"><Link v-if="link.url" :href="link.url" class="rounded-md border px-3 py-2 text-sm" :class="link.active ? 'bg-primary text-primary-foreground' : ''"><span v-html="link.label"/></Link></template></nav></div>
</template>
