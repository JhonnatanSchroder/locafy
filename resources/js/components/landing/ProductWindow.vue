<script setup lang="ts">
import { ArrowUpRight, CalendarDays, Check, CircleDollarSign, Clock3, Truck } from '@lucide/vue';
import { ref } from 'vue';

const props = withDefaults(defineProps<{ src?: string; alt?: string }>(), { alt: 'Interface do Locafy' });
const imageFailed = ref(false);
</script>

<template>
    <div class="product-window relative overflow-hidden rounded-[22px] border border-white/12 bg-[#101c2d] shadow-[0_35px_100px_rgba(0,0,0,.42)]">
        <div class="flex h-10 items-center gap-3 border-b border-white/10 px-4">
            <span class="size-2 rounded-full bg-[#ff6b6b]" /><span class="size-2 rounded-full bg-[#f7c85d]" /><span class="size-2 rounded-full bg-[#53d59a]" />
            <div class="ml-3 flex h-6 flex-1 items-center rounded-md bg-white/[0.05] px-3 text-[9px] text-white/35">app.locafy.com.br / dashboard</div>
        </div>
        <img v-if="props.src && !imageFailed" :src="props.src" :alt="props.alt" class="block aspect-[16/9] w-full object-cover object-top" fetchpriority="high" @error="imageFailed = true" />
        <template v-else>
        <div class="flex min-h-[360px]">
            <aside class="hidden w-[150px] border-r border-white/10 p-4 sm:block">
                <div class="mb-8 flex items-center gap-2 text-[11px] font-semibold text-white"><span class="size-5 rounded-md bg-[#208AEF]" /> Locafy</div>
                <div class="space-y-1 text-[10px] text-white/40"><div class="rounded-md bg-[#208AEF]/15 px-2.5 py-2 text-[#72baff]">Visão geral</div><div class="px-2.5 py-2">Clientes</div><div class="px-2.5 py-2">Contratos</div><div class="px-2.5 py-2">Movimentações</div><div class="px-2.5 py-2">Pagamentos</div></div>
            </aside>
            <div class="min-w-0 flex-1 p-5 sm:p-7">
                <div class="flex items-start justify-between"><div><p class="text-[10px] text-white/40">Quarta-feira, 09 de outubro</p><h3 class="mt-1 text-lg font-semibold tracking-[-.03em] text-white">Visão geral</h3></div><div class="flex size-7 items-center justify-center rounded-full bg-white/10 text-[10px] text-white">AS</div></div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="metric-tile"><span class="text-white/40">Contratos ativos</span><strong>Em andamento</strong><small class="text-emerald-300"><ArrowUpRight class="inline size-3" /> visão operacional</small></div>
                    <div class="metric-tile"><span class="text-white/40">A receber</span><strong>Em aberto</strong><small class="text-[#7ec3ff]">cobranças vinculadas</small></div>
                    <div class="metric-tile"><span class="text-white/40">Em campo</span><strong>Monitorado</strong><small class="text-white/45">peças e equipamentos</small></div>
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-[1.25fr_.75fr]">
                    <div class="product-panel"><div class="mb-4 flex items-center justify-between"><span class="text-[11px] font-semibold text-white">Atividade operacional</span><span class="text-[9px] text-white/35">Últimos 7 dias</span></div><div class="flex h-28 items-end gap-2 border-b border-l border-white/10 px-2 pb-0"><i v-for="height in [38,56,44,73,62,88,68]" :key="height" class="bar" :style="{height: `${height}%`}" /></div><div class="mt-3 flex justify-between text-[8px] text-white/30"><span>03 out</span><span>05 out</span><span>09 out</span></div></div>
                    <div class="product-panel"><div class="mb-4 flex items-center justify-between"><span class="text-[11px] font-semibold text-white">Próximas ações</span><Clock3 class="size-3 text-[#72baff]" /></div><div class="space-y-3"><div class="flex gap-2"><span class="mt-0.5 flex size-4 items-center justify-center rounded-full bg-amber-400/15 text-amber-300"><CalendarDays class="size-2.5" /></span><div><p class="text-[9px] text-white">Próxima cobrança</p><span class="text-[8px] text-white/35">Acompanhe no contrato</span></div></div><div class="flex gap-2"><span class="mt-0.5 flex size-4 items-center justify-center rounded-full bg-[#208AEF]/15 text-[#72baff]"><Truck class="size-2.5" /></span><div><p class="text-[9px] text-white">Retirada agendada</p><span class="text-[8px] text-white/35">Obra em andamento</span></div></div></div></div>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>

<style scoped>
.metric-tile { display:flex; min-width:0; flex-direction:column; gap:.35rem; border:1px solid rgba(255,255,255,.08); border-radius:10px; background:rgba(255,255,255,.035); padding:.75rem; font-size:9px; }
.metric-tile strong { color:#fff; font-size:18px; letter-spacing:-.05em; }
.metric-tile small { font-size:8px; }
.product-panel { border:1px solid rgba(255,255,255,.08); border-radius:10px; background:rgba(255,255,255,.025); padding:13px; }
.bar { flex:1; min-width:7px; border-radius:3px 3px 0 0; background:linear-gradient(180deg,#55b4ff,#208AEF); opacity:.9; }
</style>
