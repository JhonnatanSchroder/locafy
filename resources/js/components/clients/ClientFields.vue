<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ClientTypeOption } from '@/types';
export type ClientFormData = { type: string; name: string; document: string; phone: string; residential_address: string; notes: string };
const model = defineModel<ClientFormData>({ default: () => ({type:'INDIVIDUAL',name:'',document:'',phone:'',residential_address:'',notes:''}) });
defineProps<{clientTypes: ClientTypeOption[]; errors: Record<string, string | undefined>; idPrefix?: string}>();
</script>
<template>
    <div class="grid gap-3">
        <Label :for="`${idPrefix ?? ''}type`">Tipo</Label>
        <select :id="`${idPrefix ?? ''}type`" v-model="model.type" name="type" class="rounded border p-2"><option v-for="type in clientTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select><InputError :message="errors.type"/>
        <Label :for="`${idPrefix ?? ''}name`">Nome</Label><Input :id="`${idPrefix ?? ''}name`" v-model="model.name" name="name" required/><InputError :message="errors.name"/>
        <Label :for="`${idPrefix ?? ''}document`">CPF/CNPJ</Label><Input :id="`${idPrefix ?? ''}document`" v-model="model.document" name="document"/><InputError :message="errors.document"/>
        <Label :for="`${idPrefix ?? ''}phone`">Telefone</Label><Input :id="`${idPrefix ?? ''}phone`" v-model="model.phone" name="phone"/><InputError :message="errors.phone"/>
        <Label :for="`${idPrefix ?? ''}residential_address`">Endereço residencial</Label><textarea :id="`${idPrefix ?? ''}residential_address`" v-model="model.residential_address" name="residential_address" class="rounded border p-2"/><InputError :message="errors.residential_address"/>
        <Label :for="`${idPrefix ?? ''}notes`">Observações</Label><textarea :id="`${idPrefix ?? ''}notes`" v-model="model.notes" name="notes" class="rounded border p-2"/><InputError :message="errors.notes"/>
    </div>
</template>
