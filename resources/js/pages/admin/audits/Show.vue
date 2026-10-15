<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ScrollText } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
type Audit = { id: number; module: string | null; entity_type: string; entity_id: number; operation: string; source: string | null; user: string | null; user_id: number | null; company_id: number; created_at: string | null; reason: string | null; before_state: Record<string, unknown> | null; after_state: Record<string, unknown> | null };
const props = defineProps<{ audit: Audit }>();
const fields = [...new Set([...Object.keys(props.audit.before_state || {}), ...Object.keys(props.audit.after_state || {})])];
const value = (state: Record<string, unknown> | null, field: string) => state?.[field] === null || state?.[field] === undefined ? '—' : String(state[field]);
const date = (value: string | null) => value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value)) : '—';
const label = (value: string) => value.replaceAll('_', ' ');
defineOptions({ layout: { breadcrumbs: [{ title: 'Auditoria', href: '/auditoria' }, { title: 'Detalhe', href: '#' }] } });
</script>
<template>
    <Head title="Detalhe da auditoria" />
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 sm:p-6 lg:p-8"><Button variant="ghost" class="w-fit" as-child><Link href="/auditoria"><ArrowLeft class="mr-2 size-4" />Voltar para auditoria</Link></Button><header><p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Evento #{{ audit.id }}</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ label(audit.operation) }}</h1><p class="mt-2 text-sm text-muted-foreground">{{ audit.entity_type }} #{{ audit.entity_id }} · {{ date(audit.created_at) }}</p></header><div class="grid gap-4 sm:grid-cols-3"><Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Operador</p><p class="mt-1 font-medium">{{ audit.user || 'Sistema' }}</p></CardContent></Card><Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Módulo / origem</p><p class="mt-1 font-medium">{{ audit.module || '—' }} · {{ audit.source || '—' }}</p></CardContent></Card><Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Empresa</p><p class="mt-1 font-medium">#{{ audit.company_id }}</p></CardContent></Card></div><Card><CardHeader><CardTitle class="flex items-center gap-2"><ScrollText class="size-5" />Campos alterados</CardTitle></CardHeader><CardContent><div v-if="fields.length" class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-muted-foreground"><tr><th class="px-3 py-3">Campo</th><th class="px-3 py-3">Antes</th><th class="px-3 py-3">Depois</th></tr></thead><tbody class="divide-y"><tr v-for="field in fields" :key="field"><td class="px-3 py-3 font-medium">{{ field }}</td><td class="px-3 py-3 text-muted-foreground">{{ value(audit.before_state, field) }}</td><td class="px-3 py-3">{{ value(audit.after_state, field) }}</td></tr></tbody></table></div><p v-else class="py-8 text-sm text-muted-foreground">Este evento não possui comparação de campos.</p></CardContent></Card><Card v-if="audit.reason"><CardHeader><CardTitle class="text-base">Motivo</CardTitle></CardHeader><CardContent><p class="text-sm">{{ audit.reason }}</p></CardContent></Card></div>
</template>
