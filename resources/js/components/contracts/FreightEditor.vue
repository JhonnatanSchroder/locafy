<script setup lang="ts">
import { formatDateTime } from '@/lib/dates';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import type { Freight } from '@/types';
const props = defineProps<{contractId: number; freight: Freight}>();
const form = useForm({quantity:props.freight.quantity,unit_amount:props.freight.unit_amount,notes:props.freight.notes ?? ''});
</script>
<template><form class="grid gap-3 rounded border p-4 sm:grid-cols-4" @submit.prevent="form.patch(`/contracts/${contractId}/freights/${freight.id}`,{preserveScroll:true})">
 <p class="sm:col-span-4">Frete #{{ freight.id }} · {{ formatDateTime(freight.occurred_at) }} · Total: {{ freight.total }}</p>
 <label>Quantidade <input v-model="form.quantity" type="number" min="1" step="1" required class="w-full rounded border p-2"/></label>
 <label>Valor unit?rio <input v-model="form.unit_amount" type="number" min="0.01" step="0.01" required class="w-full rounded border p-2"/></label>
 <label>Observações <input v-model="form.notes" class="w-full rounded border p-2"/></label><Button :disabled="form.processing">Salvar frete</Button><InputError class="sm:col-span-4" :message="Object.values(form.errors).join(' ')"/>
</form></template>
