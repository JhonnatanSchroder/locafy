<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as clientsIndex, show as clientsShow } from '@/routes/clients';
import type { Client, ClientTypeOption } from '@/types';

type Props = {
    client: Client;
    clientTypes: ClientTypeOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Clientes',
                href: clientsIndex(),
            },
            {
                title: 'Editar cliente',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="`Editar ${client.name}`" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Editar cliente
                </h1>
                <p class="text-sm text-muted-foreground">
                    Atualize os dados cadastrais de {{ client.name }}.
                </p>
            </div>

            <Button variant="outline" as-child>
                <Link :href="clientsShow(client.id)">Voltar</Link>
            </Button>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="text-base">Dados do cliente</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ClientController.update.form(client.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="type">Tipo</Label>
                        <select
                            id="type"
                            name="type"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="clientType in clientTypes"
                                :key="clientType.value"
                                :value="clientType.value"
                                :selected="
                                    clientType.value === props.client.type
                                "
                            >
                                {{ clientType.label }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Nome</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            placeholder="Nome do cliente"
                            :default-value="client.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="document">CPF/CNPJ</Label>
                            <Input
                                id="document"
                                name="document"
                                placeholder="Documento"
                                :default-value="client.document ?? ''"
                            />
                            <InputError :message="errors.document" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Telefone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                placeholder="Telefone"
                                :default-value="client.phone ?? ''"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="residential_address"
                            >Endereço residencial</Label
                        >
                        <textarea
                            id="residential_address"
                            name="residential_address"
                            rows="3"
                            class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Endereço residencial do cliente"
                            :value="client.residential_address ?? ''"
                        />
                        <InputError :message="errors.residential_address" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Observação</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Observações gerais"
                            :value="client.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="processing"
                            >Salvar alterações</Button
                        >
                        <Button variant="outline" as-child>
                            <Link :href="clientsShow(client.id)">Cancelar</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
