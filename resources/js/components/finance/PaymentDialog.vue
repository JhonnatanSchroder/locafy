<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Banknote } from '@lucide/vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { currentDateTimeInput, formatDate } from '@/lib/dates';
import { money } from '@/types/charge';
import type { Receivable } from '@/types/charge';
const open = defineModel<boolean>('open',{required:true});
const props = defineProps<{receivable: Pick<Receivable, 'contract_id' | 'client' | 'balance' | 'total_accrued' | 'total_paid' | 'total_discount' | 'next_charge_date'> | null}>();
const form = useForm({amount:'',discount_amount:'0.00',paid_at:currentDateTimeInput(),method:'PIX',notes:''});
watch(open, value => {if(value) {form.reset();form.clearErrors();form.paid_at = currentDateTimeInput();}});
const methods = [{value:'PIX',label:'PIX'},{value:'CASH',label:'Dinheiro'},{value:'CARD',label:'Cartão'},{value:'TRANSFER',label:'Transferência'},{value:'OTHER',label:'Outro'}];
const asNumber = (value: string | null | undefined) => Number(value || 0);
const settled = computed(() => Math.max(0, asNumber(form.amount) + asNumber(form.discount_amount)).toFixed(2));
const remaining = computed(() => Math.max(0, asNumber(props.receivable?.balance) - Number(settled.value)).toFixed(2));
function useBalance() {
    const balance = asNumber(props.receivable?.balance);
    const discount = asNumber(form.discount_amount);
    form.amount = Math.max(0, balance - discount).toFixed(2);
}
function submit() {
    if(!props.receivable || form.processing) return;
    form.post(`/contracts/${props.receivable.contract_id}/payments`,{preserveScroll:true,onSuccess:()=>{open.value = false;form.reset();}});
}
</script>
<template>
    <Dialog :open="open" @update:open="value => {if(!form.processing) open = value}">
        <DialogContent><DialogHeader><DialogTitle class="flex items-center gap-2"><Banknote class="size-5"/> Registrar pagamento</DialogTitle><DialogDescription>{{ receivable?.client }} · Contrato #{{ receivable?.contract_id }}</DialogDescription></DialogHeader>
            <div class="rounded-lg bg-primary/5 p-4"><p class="text-xs text-muted-foreground">Saldo atual do contrato</p><p class="mt-1 text-2xl font-bold text-primary">{{ money(receivable?.balance) }}</p><p class="mt-2 text-xs text-muted-foreground">Acumulado {{ money(receivable?.total_accrued) }} · Pago {{ money(receivable?.total_paid) }} · Descontos {{ money(receivable?.total_discount) }}</p><p class="mt-1 text-xs text-muted-foreground">Data da cobrança: {{ formatDate(receivable?.next_charge_date) }}</p></div>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2"><div class="grid gap-2"><Label for="payment_amount">Valor recebido</Label><Input id="payment_amount" v-model="form.amount" type="number" min="0" step="0.01" :max="receivable?.balance ?? undefined" required/><Button type="button" variant="outline" size="sm" class="justify-self-start" :disabled="!receivable?.balance || form.processing" @click="useBalance">Usar saldo</Button><InputError :message="form.errors.amount"/></div><div class="grid gap-2"><Label for="payment_discount">Desconto</Label><Input id="payment_discount" v-model="form.discount_amount" type="number" min="0" step="0.01" :max="receivable?.balance ?? undefined"/><InputError :message="form.errors.discount_amount"/></div></div>
                <div class="rounded-lg border bg-muted/30 p-3 text-sm"><div class="flex justify-between"><span>Valor recebido</span><strong>{{ money(form.amount || '0.00') }}</strong></div><div class="flex justify-between"><span>+ Desconto</span><strong>{{ money(form.discount_amount || '0.00') }}</strong></div><div class="mt-2 flex justify-between border-t pt-2"><span>Total abatido</span><strong>{{ money(settled) }}</strong></div><div class="flex justify-between text-muted-foreground"><span>Saldo após operação</span><strong>{{ money(remaining) }}</strong></div></div>
                <div class="grid grid-cols-2 gap-3"><div class="grid gap-2"><Label for="payment_date">Data e hora</Label><Input id="payment_date" v-model="form.paid_at" type="datetime-local" required/><InputError :message="form.errors.paid_at"/></div><div class="grid gap-2"><Label for="payment_method">Método</Label><select id="payment_method" v-model="form.method" class="h-9 rounded-md border bg-background px-2"><option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option></select><InputError :message="form.errors.method"/></div></div>
                <div class="grid gap-2"><Label for="payment_notes">Observações</Label><textarea id="payment_notes" v-model="form.notes" class="rounded-md border bg-background p-2"/><InputError :message="form.errors.notes"/></div>
                <p class="text-xs text-muted-foreground">A data da próxima cobrança avança somente ao quitar todo o saldo atual.</p>
                <div class="flex justify-end gap-2"><Button type="button" variant="outline" :disabled="form.processing" @click="open = false">Cancelar</Button><Button :disabled="form.processing">{{ form.processing ? 'Registrando…' : 'Registrar pagamento' }}</Button></div>
            </form>
        </DialogContent>
    </Dialog>
</template>
