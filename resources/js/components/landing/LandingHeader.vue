<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Menu, X } from '@lucide/vue';
import { ref } from 'vue';
import { dashboard, login } from '@/routes';

defineProps<{ authenticated: boolean }>();
const open = ref(false);
</script>

<template>
    <header class="landing-header fixed inset-x-0 top-0 z-50">
        <div class="mx-auto flex h-[72px] max-w-[1240px] items-center justify-between px-5 lg:px-8">
            <a href="#top" class="flex items-center gap-2.5" aria-label="Locafy início">
                <img src="/images/logo.png" alt="" class="size-8 rounded-[10px]" />
                <span class="text-[18px] font-semibold tracking-[-0.04em] text-white">Locafy</span>
            </a>
            <nav class="hidden items-center gap-8 text-[13px] font-medium text-white/60 md:flex" aria-label="Principal">
                <a href="#produto" class="landing-nav-link">Produto</a>
                <a href="#fluxo" class="landing-nav-link">Como funciona</a>
                <a href="#mobile" class="landing-nav-link">Web + Mobile</a>
            </nav>
            <Link :href="authenticated ? dashboard() : login()" class="hidden items-center gap-2 rounded-full border border-white/15 bg-white/[0.06] px-4 py-2 text-[13px] font-semibold text-white transition hover:border-white/30 hover:bg-white/10 md:flex">
                {{ authenticated ? 'Abrir sistema' : 'Entrar' }}
                <ArrowUpRight class="size-3.5" />
            </Link>
            <button class="flex size-10 items-center justify-center rounded-full border border-white/15 text-white md:hidden" type="button" :aria-expanded="open" aria-label="Abrir menu" @click="open = !open">
                <X v-if="open" class="size-5" /><Menu v-else class="size-5" />
            </button>
        </div>
        <div v-if="open" class="border-t border-white/10 bg-[#08111f]/95 px-5 py-5 backdrop-blur-xl md:hidden">
            <nav class="flex flex-col gap-4 text-sm text-white/75">
                <a href="#produto" @click="open = false">Produto</a>
                <a href="#fluxo" @click="open = false">Como funciona</a>
                <a href="#mobile" @click="open = false">Web + Mobile</a>
                <Link :href="authenticated ? dashboard() : login()" class="pt-2 font-semibold text-white">{{ authenticated ? 'Abrir sistema' : 'Entrar' }}</Link>
            </nav>
        </div>
    </header>
</template>
