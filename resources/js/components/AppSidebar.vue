<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, Banknote, ClipboardList, Contact, LayoutGrid, Package, Users, Wrench } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as clientsIndex } from '@/routes/clients';
import { index as contractsIndex } from '@/routes/contracts';
import { index as equipmentsIndex } from '@/routes/equipments';
import { index as movementsIndex } from '@/routes/movements';
import { index as productsIndex } from '@/routes/products';
import type { NavItem } from '@/types';

const page = usePage();
const canManageUsers = () => Boolean(page.props.auth?.can?.manageUsers);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Clientes',
        href: clientsIndex(),
        icon: Contact,
    },
    {
        title: 'Produtos',
        href: productsIndex(),
        icon: Package,
    },
    {
        title: 'Equipamentos',
        href: equipmentsIndex(),
        icon: Wrench,
    },
    {
        title: 'Contratos',
        href: contractsIndex(),
        icon: ClipboardList,
    },
    { title: 'Cobranças', href: '/charges', icon: ClipboardList },
    { title: 'Pagamentos', href: '/pagamentos', icon: Banknote },
    {
        title: 'Movimentações',
        href: movementsIndex(),
        icon: ArrowLeftRight,
    },
];

const adminNavItems = (): NavItem[] =>
    canManageUsers()
        ? [{ title: 'Usuários', href: '/usuarios', icon: Users }]
        : [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="[...mainNavItems, ...adminNavItems()]" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
