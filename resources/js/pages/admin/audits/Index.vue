<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Eye, ScrollText } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Audit = { id: number; module: string | null; entity_type: string; entity_id: number; operation: string; source: string | null; user: string | null; created_at: string | null };
type LinkItem = { url: string | null; label: string; active: boolean };
const props = defineProps<{ audits: { data: Audit[]; links: LinkItem[]; from: number | null; to: number | null; total: number }; filters: Record<string, string | null>; users: { id: number; name: string }[] }>();
const filters = ref<Record<string, string>>(Object.fromEntries(Object.entries(props.filters).map(([key, value]) => [key, value || ''])));
const submit = () => router.get('/auditoria', Object.fromEntries(Object.entries(filters.value).map(([key, value]) => [key, value || undefined])), { preserveState: true, replace: true });
const date = (value: string | null) => value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
const label = (value: string | null) => value ? value.replaceAll('_', ' ') : '—';
defineOptions({ layout: { breadcrumbs: [{ title: 'Auditoria', href: '/auditoria' }] } });
</script>
<template>
    <Head title="Auditoria" />
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6 lg:p-8">
        <header><p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Governança</p><h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Auditoria</h1><p class="mt-2 text-sm text-muted-foreground">Histórico operacional da empresa, somente leitura.</p></header>
        <Card><CardHeader><CardTitle class="text-base">Filtrar eventos</CardTitle></CardHeader><CardContent><form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="submit"><Input v-model="filters.from" type="date" aria-label="De" /><Input v-model="filters.to" type="date" aria-label="Até" /><Input v-model="filters.module" placeholder="Módulo" aria-label="Módulo" /><Input v-model="filters.operation" placeholder="Operação" aria-label="Operação" /><Input v-model="filters.user_id" placeholder="ID do usuário" aria-label="ID do usuário" /><div class="flex gap-2"><Button type="submit">Filtrar</Button><Button v-if="Object.values(filters).some(Boolean)" variant="outline" as-child><Link href="/auditoria">Limpar</Link></Button></div></form></CardContent></Card>
        <Card><CardContent class="p-0"><div class="hidden overflow-x-auto md:block"><table class="w-full text-left text-sm"><thead class="border-b bg-muted/50 text-muted-foreground"><tr><th class="px-4 py-3">Evento</th><th class="px-4 py-3">Entidade</th><th class="px-4 py-3">Operador</th><th class="px-4 py-3">Origem</th><th class="px-4 py-3">Data</th><th class="px-4 py-3" /></tr></thead><tbody class="divide-y"><tr v-for="audit in audits.data" :key="audit.id"><td class="px-4 py-3"><Badge variant="muted">{{ label(audit.operation) }}</Badge><p class="mt-1 text-xs text-muted-foreground">{{ audit.module || '—' }}</p></td><td class="px-4 py-3">{{ audit.entity_type }} #{{ audit.entity_id }}</td><td class="px-4 py-3">{{ audit.user || 'Sistema' }}</td><td class="px-4 py-3">{{ audit.source || '—' }}</td><td class="px-4 py-3 whitespace-nowrap">{{ date(audit.created_at) }}</td><td class="px-4 py-3 text-right"><Button size="sm" variant="outline" as-child><Link :href="`/auditoria/${audit.id}`"><Eye class="mr-1 size-4" />Ver</Link></Button></td></tr><tr v-if="!audits.data.length"><td colspan="6" class="px-4 py-12 text-center text-muted-foreground"><ScrollText class="mx-auto mb-3 size-8" />Nenhum evento encontrado.</td></tr></tbody></table></div><div class="grid gap-3 p-3 md:hidden"><article v-for="audit in audits.data" :key="audit.id" class="rounded-lg border p-4"><div class="flex items-start justify-between gap-3"><div><Badge variant="muted">{{ label(audit.operation) }}</Badge><p class="mt-2 font-medium">{{ audit.entity_type }} #{{ audit.entity_id }}</p></div><Button size="sm" variant="outline" as-child><Link :href="`/auditoria/${audit.id}`">Ver</Link></Button></div><dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted-foreground"><div><dt>Operador</dt><dd class="text-foreground">{{ audit.user || 'Sistema' }}</dd></div><div><dt>Origem</dt><dd class="text-foreground">{{ audit.source || '—' }}</dd></div><div class="col-span-2"><dt>Data</dt><dd class="text-foreground">{{ date(audit.created_at) }}</dd></div></dl></article><p v-if="!audits.data.length" class="py-10 text-center text-muted-foreground">Nenhum evento encontrado.</p></div></CardContent></Card>
        <div v-if="audits.links.length > 3" class="flex flex-wrap gap-2"><Button v-for="link in audits.links" :key="link.label" size="sm" :variant="link.active ? 'default' : 'outline'" :disabled="!link.url" as-child><Link v-if="link.url" :href="link.url" v-html="link.label" /></Button></div>
    </div>
</template>
