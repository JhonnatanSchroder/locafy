<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Banknote, CheckCheck } from '@lucide/vue';
import PaymentDialog from '@/components/finance/PaymentDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import type { Contract } from '@/types';
const props = defineProps<{ contract: Contract }>();
const paymentOpen = ref(false);
const finalizeOpen = ref(false);
const form = useForm({});
const payment = computed(() => ({contract_id:props.contract.id,client:props.contract.client.name,balance:props.contract.financial_balance,total_accrued:props.contract.total_accrued,total_paid:props.contract.total_paid,next_charge_date:props.contract.next_charge_date}));
function finalize() {
    form.post(`/contracts/${props.contract.id}/finalize`, {preserveScroll:true,preserveState:true,onSuccess:()=>{finalizeOpen.value=false;}});
}
</script>
<template>
    <Button v-if="!['FINALIZED','CANCELLED'].includes(contract.status) && contract.financial_balance !== null && contract.financial_balance !== '0.00'" variant="outline" size="sm" @click="paymentOpen=true"><Banknote class="mr-1 size-4"/> Pagamento</Button>
    <Button v-if="contract.can_finalize" size="sm" @click="form.clearErrors();finalizeOpen=true"><CheckCheck class="mr-1 size-4"/> Finalizar</Button>
    <PaymentDialog v-model:open="paymentOpen" :receivable="payment"/>
    <Dialog :open="finalizeOpen" @update:open="value=>{if(!form.processing) finalizeOpen=value}">
        <DialogContent><DialogHeader><DialogTitle>Finalizar este contrato?</DialogTitle><DialogDescription>Após a finalização ele será considerado encerrado e não aceitará novas movimentações operacionais.</DialogDescription></DialogHeader>
            <p class="text-sm text-muted-foreground">{{ contract.client.name }} · Contrato #{{ contract.number }}</p>
            <InputError v-for="(message,field) in form.errors" :key="field" :message="message"/>
            <div class="flex justify-end gap-2"><Button variant="outline" :disabled="form.processing" @click="finalizeOpen=false">Cancelar</Button><Button :disabled="form.processing" @click="finalize">{{ form.processing ? 'Finalizando…' : 'Confirmar' }}</Button></div>
        </DialogContent>
    </Dialog>
</template>
