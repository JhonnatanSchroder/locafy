<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(defineProps<{ src?: string; alt?: string }>(), { alt: 'Aplicativo Mobile do Locafy' });
const imageFailed = ref(false);
</script>

<template>
    <div class="mobile-device relative mx-auto w-[190px] rounded-[30px] border-[6px] border-[#1b2a3e] bg-[#0b1523] p-1 shadow-[0_28px_70px_rgba(0,0,0,.45)] sm:w-[220px]">
        <img v-if="props.src && !imageFailed" :src="props.src" :alt="props.alt" class="block aspect-[9/19] w-full rounded-[22px] object-cover object-top" loading="lazy" @error="imageFailed = true" />
        <template v-else>
        <div class="overflow-hidden rounded-[22px] bg-[#f5f8fc]">
            <div class="flex items-center justify-between bg-[#102239] px-4 py-4 text-white"><div><p class="text-[8px] text-white/45">Locafy</p><p class="mt-0.5 text-[12px] font-semibold">Operação Locafy</p></div><span class="flex size-6 items-center justify-center rounded-full bg-[#208AEF] text-[8px] font-semibold">LC</span></div>
            <div class="space-y-3 p-3"><div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-[8px] text-slate-400">Contratos ativos</p><strong class="mt-1 block text-xl tracking-[-.06em] text-[#13253c]">Em andamento</strong><div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-2/3 rounded-full bg-[#208AEF]" /></div></div><div class="grid grid-cols-2 gap-2"><div class="rounded-xl bg-white p-3 shadow-sm"><span class="text-[8px] text-slate-400">A receber</span><strong class="mt-1 block text-[13px] text-[#13253c]">Em aberto</strong></div><div class="rounded-xl bg-white p-3 shadow-sm"><span class="text-[8px] text-slate-400">Em campo</span><strong class="mt-1 block text-[13px] text-[#13253c]">Monitorado</strong></div></div><div class="rounded-xl bg-white p-3 shadow-sm"><div class="flex items-center justify-between"><span class="text-[9px] font-semibold text-[#13253c]">Últimas movimentações</span><span class="text-[8px] text-[#208AEF]">Ver todas</span></div><div class="mt-3 space-y-2.5"><div class="flex items-center gap-2"><span class="flex size-5 items-center justify-center rounded-full bg-emerald-50 text-emerald-500">↘</span><div><p class="text-[9px] font-medium text-slate-700">Devolução</p><span class="text-[8px] text-slate-400">Histórico do contrato</span></div></div><div class="flex items-center gap-2"><span class="flex size-5 items-center justify-center rounded-full bg-blue-50 text-[#208AEF]">↗</span><div><p class="text-[9px] font-medium text-slate-700">Retirada</p><span class="text-[8px] text-slate-400">Histórico do contrato</span></div></div></div></div></div>
            <div class="flex justify-around border-t border-slate-200 bg-white px-3 py-3 text-[8px] text-slate-400"><span class="text-[#208AEF]">Início</span><span>Contratos</span><span>Mais</span></div>
        </div>
        </template>
    </div>
</template>
