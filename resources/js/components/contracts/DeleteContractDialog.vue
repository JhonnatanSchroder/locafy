<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import type { Contract } from '@/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const props = defineProps<{ contract: Contract; compact?: boolean }>();
const open = ref(false);
const form = useForm({});

const destroy = () => {
    form.delete(`/contracts/${props.contract.id}`, {
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Button variant="destructive" :size="compact ? 'sm' : 'default'" @click="open = true">
        <Trash2 class="mr-1 size-4" />
        <span>Excluir contrato</span>
    </Button>
    <Dialog :open="open" @update:open="value => { if (!form.processing) open = value }">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Excluir contrato #{{ contract.number }}?</DialogTitle>
                <DialogDescription>
                    Esta ação é permanente e só será concluída se não houver histórico financeiro ou operacional impeditivo.
                </DialogDescription>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">{{ contract.client.name }}</p>
            <InputError :message="form.errors.contract" />
            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="form.processing" @click="open = false">Cancelar</Button>
                <Button variant="destructive" :disabled="form.processing" @click="destroy">
                    {{ form.processing ? 'Excluindo…' : 'Excluir contrato' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
