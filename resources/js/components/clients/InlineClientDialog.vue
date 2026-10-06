<script setup lang="ts">
import { ref } from 'vue';
import ClientFields, { type ClientFormData } from './ClientFields.vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import type { ClientTypeOption } from '@/types';
const open = defineModel<boolean>('open', {required: true});
defineProps<{clientTypes: ClientTypeOption[]}>();
const emit = defineEmits<{created: [client: {id: number; name: string}]}>();
const empty = (): ClientFormData => ({type:'INDIVIDUAL',name:'',document:'',phone:'',residential_address:'',notes:''});
const form = ref(empty());
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const failure = ref('');
async function submit() {
    if (saving.value) return;
    saving.value = true;
    errors.value = {};
    failure.value = '';
    try {
        const token = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
        const response = await fetch('/clients', {method:'POST', credentials:'same-origin', headers:{Accept:'application/json', 'Content-Type':'application/json', ...(token ? {'X-XSRF-TOKEN':decodeURIComponent(token)} : {})}, body:JSON.stringify(form.value)});
        if (response.status === 422) {
            const result = await response.json();
            errors.value = Object.fromEntries(Object.entries(result.errors as Record<string,string[]>).map(([key,messages])=>[key,messages[0]]));
            return;
        }
        if (!response.ok) throw new Error('Não foi possível cadastrar o cliente. Verifique sua sessão e tente novamente.');
        const result = await response.json();
        emit('created', {id:result.data.id, name:result.data.name});
        open.value = false;
        form.value = empty();
    } catch (error) {
        failure.value = error instanceof Error ? error.message : 'Falha ao cadastrar cliente.';
    } finally { saving.value = false; }
}
</script>
<template>
    <Dialog :open="open" @update:open="value => {if (!saving) open = value}">
        <DialogContent class="max-h-[90vh] overflow-y-auto" @interact-outside="event => {if (saving) event.preventDefault()}" @escape-key-down="event => {if (saving) event.preventDefault()}">
            <DialogHeader><DialogTitle>Novo cliente</DialogTitle><DialogDescription>Cadastre e selecione o cliente sem sair do contrato.</DialogDescription></DialogHeader>
            <form class="space-y-4" @submit.prevent.stop="submit">
                <ClientFields v-model="form" :client-types="clientTypes" :errors="errors" id-prefix="inline-client-"/>
                <InputError :message="failure"/>
                <div class="flex gap-3"><Button :disabled="saving">Salvar cliente</Button><Button type="button" variant="outline" :disabled="saving" @click="open = false">Cancelar</Button></div>
            </form>
        </DialogContent>
    </Dialog>
</template>
