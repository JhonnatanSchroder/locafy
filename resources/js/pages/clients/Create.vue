<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import ClientFields from '@/components/clients/ClientFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as clientsIndex } from '@/routes/clients';
import type { ClientTypeOption } from '@/types';

type Props = {
    clientTypes: ClientTypeOption[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Clientes',
                href: clientsIndex(),
            },
            {
                title: 'Novo cliente',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Novo cliente" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Novo cliente
                </h1>
                <p class="text-sm text-muted-foreground">
                    Cadastre um cliente para a sua empresa.
                </p>
            </div>

            <Button variant="outline" as-child>
                <Link :href="clientsIndex()">Voltar</Link>
            </Button>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="text-base">Dados do cliente</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ClientController.store.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <ClientFields :client-types="clientTypes" :errors="errors"/>

                    <div class="flex items-center gap-3">
                        <Button :disabled="processing">Salvar cliente</Button>
                        <Button variant="outline" as-child>
                            <Link :href="clientsIndex()">Cancelar</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
