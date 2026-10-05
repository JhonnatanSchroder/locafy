<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    create as clientsCreate,
    edit as clientsEdit,
    index as clientsIndex,
    show as clientsShow,
} from '@/routes/clients';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { Client, ClientTypeOption } from '@/types';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedClients = {
    data: Client[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Props = {
    clients: PaginatedClients;
    filters: {
        search: string;
    };
    clientTypes: ClientTypeOption[];
};

const props = defineProps<Props>();
const search = ref(props.filters.search);

const submitSearch = () => {
    router.get(
        clientsIndex.url(),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Clientes',
                href: clientsIndex(),
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head title="Clientes" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Clientes</h1>
                <p class="text-sm text-muted-foreground">
                    Cadastre e consulte os clientes da sua empresa.
                </p>
            </div>

            <Button as-child>
                <Link :href="clientsCreate()">Novo cliente</Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Buscar clientes</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="flex flex-col gap-3 sm:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="search"
                        name="search"
                        placeholder="Buscar por nome, telefone ou documento"
                        class="sm:max-w-md"
                    />
                    <div class="flex gap-2">
                        <Button type="submit">Buscar</Button>
                        <Button
                            v-if="filters.search"
                            variant="outline"
                            as-child
                        >
                            <Link :href="clientsIndex()">Limpar</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/60 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 font-medium">Telefone</th>
                                <th class="px-4 py-3 font-medium">Documento</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="clients.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Nenhum cliente encontrado.
                                </td>
                            </tr>
                            <tr
                                v-for="client in clients.data"
                                :key="client.id"
                                class="border-b transition-colors hover:bg-muted/40 last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ client.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ client.type_label }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ client.phone || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ client.document || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="clientsShow(client.id)"
                                                >Ver</Link
                                            >
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="clientsEdit(client.id)"
                                                >Editar</Link
                                            >
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="clients.links.length > 3"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Mostrando {{ clients.from }} a {{ clients.to }} de
                {{ clients.total }} clientes
            </p>
            <div class="flex flex-wrap gap-2">
                <template v-for="link in clients.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        as-child
                    >
                        <Link :href="link.url" v-html="link.label" />
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        size="sm"
                        disabled
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
