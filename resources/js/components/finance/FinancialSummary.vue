<script setup lang="ts">
import { money } from '@/types/charge';
defineProps<{rentalTotal: string | null; freightTotal: string; totalAccrued: string | null; totalPaid: string; totalDiscount?: string; balance: string | null}>();
</script>
<template>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Resumo financeiro">
        <div v-for="item in [{label:'Locação',value:rentalTotal},{label:'Fretes',value:freightTotal},{label:'Total acumulado',value:totalAccrued},{label:'Total pago',value:totalPaid},{label:'Descontos',value:totalDiscount ?? '0.00'}]" :key="item.label" class="rounded-xl border bg-card px-5 py-4">
            <p class="text-xs font-medium text-muted-foreground">{{ item.label }}</p><p class="mt-2 text-xl font-semibold tabular-nums">{{ money(item.value) }}</p>
        </div>
        <div class="rounded-xl border border-primary/20 bg-primary/5 px-5 py-4"><p class="text-xs font-semibold text-primary">Saldo a receber</p><p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ money(balance) }}</p></div>
    </section>
</template>
