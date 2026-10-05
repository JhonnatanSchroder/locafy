<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Banknote,
    ClipboardList,
    PackageCheck,
    ReceiptText,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

const overviewCards = [
    {
        title: 'Contratos ativos',
        value: '—',
        icon: ClipboardList,
    },
    {
        title: 'Cobranças',
        value: '—',
        icon: ReceiptText,
    },
    {
        title: 'Financeiro',
        value: '—',
        icon: Banknote,
    },
    {
        title: 'Equipamentos',
        value: '—',
        icon: PackageCheck,
    },
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-2 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Dashboard
                </h1>
                <p class="text-sm text-muted-foreground">
                    Visão geral do Locafy.
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card v-for="card in overviewCards" :key="card.title">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ card.title }}
                    </CardTitle>
                    <div
                        class="flex size-9 items-center justify-center rounded-md bg-accent text-accent-foreground"
                    >
                        <component :is="card.icon" class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold tracking-tight">
                        {{ card.value }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Operação</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        class="rounded-md border border-border bg-secondary/40 p-4"
                    >
                        <p class="text-sm font-medium">Contratos</p>
                        <p class="mt-2 text-2xl font-semibold">—</p>
                    </div>
                    <div
                        class="rounded-md border border-border bg-secondary/40 p-4"
                    >
                        <p class="text-sm font-medium">Cobranças</p>
                        <p class="mt-2 text-2xl font-semibold">—</p>
                    </div>
                    <div
                        class="rounded-md border border-border bg-secondary/40 p-4"
                    >
                        <p class="text-sm font-medium">Equipamentos</p>
                        <p class="mt-2 text-2xl font-semibold">—</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
