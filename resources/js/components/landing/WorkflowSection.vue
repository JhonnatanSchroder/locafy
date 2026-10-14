<script setup lang="ts">
import { CalendarDays, CircleDollarSign, FileText, Package, Truck } from '@lucide/vue';
import { ref } from 'vue';

const activeStep = ref(0);
const steps = [
    { n: '01', title: 'Cliente', text: 'Cadastre e encontre rapidamente.', icon: FileText, label: 'Cliente selecionado', detail: 'Cadastro e contato no contexto do contrato.' },
    { n: '02', title: 'Contrato', text: 'Centralize itens, valores e obra.', icon: Package, label: 'Contrato ativo', detail: 'Itens, datas, cobrança e situação em uma leitura.' },
    { n: '03', title: 'Movimentação', text: 'Registre o que saiu e voltou.', icon: Truck, label: 'Histórico operacional', detail: 'Retiradas e devoluções vinculadas ao contrato.' },
    { n: '04', title: 'Recebimento', text: 'Acompanhe cobrança e saldo.', icon: CircleDollarSign, label: 'Saldo em aberto', detail: 'Pagamentos, descontos e próxima cobrança.' },
];
</script>

<template>
    <section id="fluxo" class="landing-section bg-[#f4f7fb] px-5 py-20 text-[#102239] lg:px-8 lg:py-28">
        <div class="mx-auto max-w-[1120px]">
            <div class="landing-reveal max-w-xl"><p class="landing-eyebrow text-[#208AEF]">O fluxo real da locação</p><h2 class="mt-5 text-4xl font-semibold tracking-[-.055em] sm:text-5xl">Do primeiro contato ao contrato encerrado.</h2><p class="mt-5 text-base leading-7 text-slate-600">Cada etapa deixa um registro. A operação fica clara para quem está na loja e para quem está na obra.</p></div>
            <div class="mt-14 grid gap-10 lg:grid-cols-[.72fr_1.28fr] lg:gap-20">
                <div class="workflow-steps space-y-2"><button v-for="(step, index) in steps" :key="step.n" type="button" class="workflow-step" :class="{ 'is-active': activeStep === index }" @mouseenter="activeStep = index" @focus="activeStep = index" @click="activeStep = index"><span class="workflow-number">{{ step.n }}</span><span class="min-w-0 flex-1 text-left"><strong>{{ step.title }}</strong><small>{{ step.text }}</small></span><component :is="step.icon" class="size-4 shrink-0" /></button></div>
                <div class="workflow-stage landing-reveal lg:sticky lg:top-28 lg:self-start"><div class="workflow-stage-top"><span class="flex items-center gap-2"><span class="size-2 rounded-full bg-emerald-400" /> Locafy / contrato</span><span>visão operacional</span></div><div class="workflow-stage-body"><div class="flex items-start justify-between"><div><p class="text-[10px] text-white/45">{{ steps[activeStep].label }}</p><h3 class="mt-2 text-2xl font-semibold tracking-[-.05em] text-white">{{ steps[activeStep].title }}</h3></div><component :is="steps[activeStep].icon" class="size-5 text-[#7ec3ff]" /></div><p class="mt-3 max-w-sm text-sm leading-6 text-white/55">{{ steps[activeStep].detail }}</p><div class="mt-8 grid gap-3 sm:grid-cols-3"><div class="workflow-data"><span>Contrato</span><strong>Ativo</strong></div><div class="workflow-data"><span>Itens</span><strong>Em campo</strong></div><div class="workflow-data"><span>Próximo passo</span><strong>{{ activeStep === 3 ? 'Receber' : 'Registrar' }}</strong></div></div><div class="mt-5 flex items-center gap-3 border-t border-white/10 pt-4 text-[11px] text-white/50"><CalendarDays class="size-4 text-[#7ec3ff]" /> Histórico e cobrança permanecem no contrato</div></div></div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.workflow-step { display:flex; width:100%; min-height:76px; align-items:center; gap:14px; border-left:2px solid #dce5ef; padding:15px 14px; color:#64748b; text-align:left; transition:background .25s ease,border-color .25s ease,color .25s ease,transform .25s ease; }
.workflow-step:hover,.workflow-step:focus-visible,.workflow-step.is-active { border-color:#208AEF; background:#fff; color:#102239; box-shadow:0 12px 30px rgba(22,55,92,.06); outline:none; transform:translateX(3px); }
.workflow-number { color:#208AEF; font-size:11px; font-weight:700; letter-spacing:.12em; }.workflow-step strong,.workflow-step small { display:block; }.workflow-step strong { font-size:15px; font-weight:700; letter-spacing:-.02em; }.workflow-step small { margin-top:4px; font-size:12px; line-height:1.45; color:#64748b; }
.workflow-stage { overflow:hidden; border:1px solid rgba(255,255,255,.12); border-radius:20px; background:#102239; box-shadow:0 24px 70px rgba(16,34,57,.18); }.workflow-stage-top { display:flex; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,.1); padding:13px 17px; color:rgba(255,255,255,.45); font-size:10px; }.workflow-stage-body { min-height:300px; padding:25px; }.workflow-data { border:1px solid rgba(255,255,255,.1); border-radius:10px; background:rgba(255,255,255,.04); padding:13px; }.workflow-data span,.workflow-data strong { display:block; }.workflow-data span { color:rgba(255,255,255,.42); font-size:10px; }.workflow-data strong { margin-top:9px; color:#fff; font-size:14px; letter-spacing:-.03em; }
@media (prefers-reduced-motion: reduce) { .workflow-step { transition:none; } }
</style>
