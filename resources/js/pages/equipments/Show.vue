<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    edit as equipmentsEdit,
    index as equipmentsIndex,
} from '@/routes/equipments';
import type { Equipment } from '@/types';
import type { BadgeVariants } from '@/components/ui/badge';

type Props = {
    equipment: Equipment;
};

defineProps<Props>();

const equipmentStatusVariant = (
    status: Equipment['status'],
): BadgeVariants['variant'] => {
    if (status === 'AVAILABLE') {
        return 'success';
    }

    if (status === 'RENTED') {
        return 'info';
    }

    if (status === 'MAINTENANCE') {
        return 'warning';
    }

    return 'muted';
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Equipamentos',
                href: equipmentsIndex(),
            },
            {
                title: 'Detalhes do equipamento',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-5 p-4 sm:p-6">
        <Head :title="equipment.name" />

        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ equipment.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Dados da unidade física identificável.
                </p>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="equipmentsIndex()">Voltar</Link>
                </Button>
                <Button as-child>
                    <Link :href="equipmentsEdit(equipment.id)">Editar</Link>
                </Button>
            </div>
        </div>

        <Card class="max-w-3xl">
            <CardHeader>
                <CardTitle class="text-base">Informações</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Nome
                        </dt>
                        <dd>{{ equipment.name }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Produto
                        </dt>
                        <dd>{{ equipment.product.name }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Marca
                        </dt>
                        <dd>{{ equipment.brand || '—' }}</dd>
                    </div>

                    <div class="grid gap-1">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Status
                        </dt>
                        <dd>
                            <Badge
                                :variant="
                                    equipmentStatusVariant(equipment.status)
                                "
                            >
                                {{ equipment.status_label }}
                            </Badge>
                        </dd>
                    </div>

                    <div class="grid gap-1 sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Observação
                        </dt>
                        <dd class="whitespace-pre-line">
                            {{ equipment.notes || '—' }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
