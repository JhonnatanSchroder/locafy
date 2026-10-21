<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Banknote, CheckCheck, Ellipsis, Pencil, Trash2 } from '@lucide/vue';
import DeleteContractDialog from '@/components/contracts/DeleteContractDialog.vue';
import PaymentDialog from '@/components/finance/PaymentDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { edit as contractsEdit } from '@/routes/contracts';
import type { Contract } from '@/types';

const props = defineProps<{
    contract: Contract;
    menu?: boolean;
    canDelete?: boolean;
}>();
const paymentOpen = ref(false);
const finalizeOpen = ref(false);
const deleteOpen = ref(false);
const form = useForm({});

const payment = computed(() => ({
    contract_id: props.contract.id,
    client: props.contract.client.name,
    balance: props.contract.financial_balance,
    total_accrued: props.contract.total_accrued,
    total_paid: props.contract.total_paid,
    total_discount: props.contract.total_discount,
    next_charge_date: props.contract.next_charge_date,
}));

function finalize() {
    form.post(`/contracts/${props.contract.id}/finalize`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            finalizeOpen.value = false;
        },
    });
}
</script>

<template>
    <DropdownMenu v-if="menu">
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="icon"
                class="size-9"
                aria-label="Ações do contrato"
            >
                <Ellipsis class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuItem
                v-if="
                    !['FINALIZED', 'CANCELLED'].includes(contract.status) &&
                    contract.financial_balance !== null &&
                    contract.financial_balance !== '0.00'
                "
                @select="paymentOpen = true"
            >
                <Banknote class="size-4" /> Registrar pagamento
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="contract.can_finalize"
                @select="finalizeOpen = true"
            >
                <CheckCheck class="size-4" /> Finalizar contrato
            </DropdownMenuItem>
            <DropdownMenuItem :as-child="true">
                <Link :href="contractsEdit(contract.id)">
                    <Pencil class="size-4" /> Editar contrato
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-if="canDelete"
                variant="destructive"
                @select="deleteOpen = true"
            >
                <Trash2 class="size-4" /> Excluir contrato
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
    <Button
        v-else-if="
            !['FINALIZED', 'CANCELLED'].includes(contract.status) &&
            contract.financial_balance !== null &&
            contract.financial_balance !== '0.00'
        "
        variant="outline"
        size="sm"
        @click="paymentOpen = true"
    >
        <Banknote class="mr-1 size-4" /> Pagamento
    </Button>
    <Button
        v-if="!menu && contract.can_finalize"
        size="sm"
        @click="
            form.clearErrors();
            finalizeOpen = true;
        "
    >
        <CheckCheck class="mr-1 size-4" /> Finalizar
    </Button>
    <PaymentDialog v-model:open="paymentOpen" :receivable="payment" />
    <DeleteContractDialog
        v-if="menu && canDelete"
        v-model:open="deleteOpen"
        :contract="contract"
        triggerless
    />
    <Dialog
        :open="finalizeOpen"
        @update:open="
            (value) => {
                if (!form.processing) finalizeOpen = value;
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Finalizar este contrato?</DialogTitle>
                <DialogDescription
                    >Após a finalização ele será considerado encerrado e não
                    aceitará novas movimentações
                    operacionais.</DialogDescription
                >
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                {{ contract.client.name }} · Contrato #{{ contract.number }}
            </p>
            <InputError
                v-for="(message, field) in form.errors"
                :key="field"
                :message="message"
            />
            <div class="flex justify-end gap-2">
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="finalizeOpen = false"
                    >Cancelar</Button
                >
                <Button :disabled="form.processing" @click="finalize">{{
                    form.processing ? 'Finalizando…' : 'Confirmar'
                }}</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
