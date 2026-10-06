<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatDateTime } from '@/lib/dates';
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import type { ContractAttachment } from '@/types';

const props = defineProps<{
    contractId: number;
    attachments: ContractAttachment[];
    locked: boolean;
}>();

const selected = ref<ContractAttachment | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const uploadForm = useForm<{ attachments: File[] }>({ attachments: [] });

const submitFiles = (files: FileList | null) => {
    if (!files?.length || props.locked) return;

    uploadForm.attachments = Array.from(files);
    uploadForm.post(`/contracts/${props.contractId}/attachments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const remove = (attachment: ContractAttachment) => {
    if (props.locked || !confirm(`Remover ${attachment.original_name}?`)) return;

    router.delete(`/contracts/${props.contractId}/attachments/${attachment.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader class="flex flex-row items-center justify-between gap-3">
            <div>
                <CardTitle class="text-base">Fotos / Anexos</CardTitle>
                <p class="mt-1 text-xs text-muted-foreground">{{ attachments.length }} / 10 fotos</p>
            </div>
            <div v-if="!locked">
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="submitFiles(($event.target as HTMLInputElement).files)" />
                <Button type="button" variant="outline" :disabled="uploadForm.processing || attachments.length >= 10" @click="fileInput?.click()">
                    <ImagePlus class="mr-2 size-4" />Adicionar fotos
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <InputError class="mb-3" :message="uploadForm.errors.attachments" />
            <div v-if="attachments.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="attachment in attachments" :key="attachment.id" class="overflow-hidden rounded-lg border bg-card">
                    <button type="button" class="block aspect-video w-full bg-muted" @click="selected = attachment">
                        <img :src="attachment.view_url" :alt="attachment.original_name" class="h-full w-full object-cover" />
                    </button>
                    <div class="space-y-2 p-3">
                        <p class="truncate text-sm font-medium">{{ attachment.original_name }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(attachment.created_at) }}<span v-if="attachment.uploaded_by"> · {{ attachment.uploaded_by }}</span></p>
                        <Button v-if="!locked" type="button" variant="outline" size="sm" class="w-full" @click="remove(attachment)">
                            <Trash2 class="mr-2 size-4" />Remover
                        </Button>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed px-5 py-10 text-center text-sm text-muted-foreground">
                Nenhuma foto anexada.
            </div>
        </CardContent>
    </Card>

    <Dialog :open="selected !== null" @update:open="value => { if (!value) selected = null }">
        <DialogContent class="max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ selected?.original_name }}</DialogTitle>
            </DialogHeader>
            <img v-if="selected" :src="selected.view_url" :alt="selected.original_name" class="max-h-[75vh] w-full rounded-md object-contain" />
        </DialogContent>
    </Dialog>
</template>
