<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit as clientsEdit, index as clientsIndex } from '@/routes/clients';
import type { Client } from '@/types';

type Props = {
    client: Client;
};

const props = defineProps<Props>();
const page = usePage<{ auth?: { user?: { role?: string } } }>();
const isAdmin = () => page.props.auth?.user?.role === 'ADMIN';
const changeStatus = (status: string) => {
    if (window.confirm(`Confirmar alteração para ${status}?`)) router.patch(`/clients/${props.client.id}/status`, { status }, { preserveScroll: true });
};
const archive = () => {
    if (window.confirm('Esta operação pode arquivar o cliente e não poderá ser desfeita automaticamente. Continuar?')) router.delete(`/clients/${props.client.id}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Clientes',
                href: clientsIndex(),
            },
            {
                title: 'Detalhes do cliente',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head :title="client.name" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ client.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Dados cadastrais do cliente.
                </p>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="clientsIndex()">Voltar</Link>
                </Button>
                <Button as-child>
                    <Link :href="clientsEdit(client.id)">Editar</Link>
                </Button>
                <template v-if="isAdmin()">
                    <Button v-if="client.status === 'ACTIVE'" variant="outline" @click="changeStatus('BLOCKED')">Bloquear</Button>
                    <Button v-else variant="outline" @click="changeStatus('ACTIVE')">Reativar</Button>
                    <Button v-if="client.status !== 'INACTIVE'" variant="outline" @click="changeStatus('INACTIVE')">Inativar</Button>
                    <Button variant="destructive" @click="archive">Arquivar</Button>
                </template>
            </div>
        </div>

        <Card class="max-w-4xl rounded-xl">
            <CardHeader>
                <CardTitle class="text-base">Informações</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">Status</dt>
                        <dd><Badge :variant="client.status === 'ACTIVE' ? 'success' : client.status === 'BLOCKED' ? 'warning' : 'muted'">{{ client.status === 'ACTIVE' ? 'Ativo' : client.status === 'BLOCKED' ? 'Bloqueado' : 'Inativo' }}</Badge></dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Nome
                        </dt>
                        <dd>{{ client.name }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Tipo
                        </dt>
                        <dd><Badge variant="muted">{{ client.type_label }}</Badge></dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            CPF/CNPJ
                        </dt>
                        <dd>{{ client.document || '—' }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Telefone
                        </dt>
                        <dd>{{ client.phone || '—' }}</dd>
                    </div>

                    <div class="grid gap-1 sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Endereço residencial
                        </dt>
                        <dd class="whitespace-pre-line">
                            {{ client.residential_address || '—' }}
                        </dd>
                    </div>

                    <div class="grid gap-1 sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Observação
                        </dt>
                        <dd class="whitespace-pre-line">
                            {{ client.notes || '—' }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
