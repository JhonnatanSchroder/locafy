<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import type { Contract } from '@/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const props = defineProps<{ contract: Contract; compact?: boolean }>();
const open = ref(false);
const confirmation = ref('');
const form = useForm<{ contract?: string }>({});

const canConfirm = () => confirmation.value === String(props.contract.number);

const start = () => {
    confirmation.value = '';
    form.clearErrors();
    open.value = true;
};

const destroy = () => {
    if (!canConfirm()) {
        form.setError(
            'contract',
            `Digite ${props.contract.number} para confirmar a exclusão.`,
        );
        return;
    }

    form.delete(`/contracts/${props.contract.id}`, {
        onSuccess: () => {
            open.value = false;
            confirmation.value = '';
        },
    });
};
</script>

<template>
    <Button
        variant="destructive"
        :size="compact ? 'sm' : 'default'"
        @click="start"
    >
        <Trash2 class="mr-1 size-4" />
        <span>Excluir contrato</span>
    </Button>
    <Dialog
        :open="open"
        @update:open="
            (value) => {
                if (!form.processing) open = value;
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Excluir contrato #{{ contract.number }}?</DialogTitle
                >
                <DialogDescription>
                    Esta ação apagará permanentemente o contrato e todo o
                    histórico operacional e financeiro exclusivo associado a
                    ele.
                </DialogDescription>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                {{ contract.client.name }}
            </p>
            <div class="grid gap-2">
                <label
                    class="text-sm font-medium"
                    :for="`delete-contract-${contract.id}`"
                >
                    Digite {{ contract.number }} para confirmar
                </label>
                <Input
                    :id="`delete-contract-${contract.id}`"
                    v-model="confirmation"
                    inputmode="numeric"
                    autocomplete="off"
                    :disabled="form.processing"
                    @keyup.enter="destroy"
                />
            </div>
            <InputError :message="form.errors.contract" />
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="open = false"
                    >Cancelar</Button
                >
                <Button
                    variant="destructive"
                    :disabled="form.processing || !canConfirm()"
                    @click="destroy"
                >
                    {{ form.processing ? 'Excluindo…' : 'Excluir contrato' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
