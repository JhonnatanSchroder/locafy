<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Copy } from '@lucide/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    active: boolean;
    status_label: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    users: {
        data: ManagedUser[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    roles: Array<{ value: string; label: string }>;
    passwordRules: string;
};

const props = defineProps<Props>();
const page = usePage();

const createOpen = ref(false);
const editOpen = ref(false);
const resetOpen = ref(false);
const selectedUser = ref<ManagedUser | null>(null);

const temporaryPassword = computed(() => page.props.temporaryPassword as string | undefined);

const createForm = useForm({
    name: '',
    email: '',
    role: 'OPERATOR',
    generate_password: false,
    password: '',
    password_confirmation: '',
});

const editForm = useForm({
    name: '',
    email: '',
    role: 'OPERATOR',
});

const resetForm = useForm({
    generate_password: true,
    password: '',
    password_confirmation: '',
});

const openEdit = (user: ManagedUser) => {
    selectedUser.value = user;
    editForm.defaults({
        name: user.name,
        email: user.email,
        role: user.role,
    });
    editForm.reset();
    editOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/usuarios', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createOpen.value = false;
        },
    });
};

const submitEdit = () => {
    if (!selectedUser.value) return;

    editForm.patch(`/usuarios/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
        },
    });
};

const deactivate = (user: ManagedUser) => {
    if (!confirm(`Desativar acesso de ${user.name}?`)) return;

    router.patch(`/usuarios/${user.id}/deactivate`, {}, { preserveScroll: true });
};

const activate = (user: ManagedUser) => {
    router.patch(`/usuarios/${user.id}/activate`, {}, { preserveScroll: true });
};

const resetPassword = (user: ManagedUser) => {
    selectedUser.value = user;
    resetForm.defaults({
        generate_password: true,
        password: '',
        password_confirmation: '',
    });
    resetForm.reset();
    resetOpen.value = true;
};

const submitResetPassword = () => {
    if (!selectedUser.value) return;

    resetForm.post(`/usuarios/${selectedUser.value.id}/reset-password`, {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.reset();
            resetOpen.value = false;
        },
    });
};

const copyTemporaryPassword = async () => {
    if (!temporaryPassword.value) return;

    await navigator.clipboard?.writeText(temporaryPassword.value);
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Usuários" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Usuários</h1>
                <p class="text-sm text-muted-foreground">Administre os acessos da empresa atual.</p>
            </div>

            <Button @click="createOpen = true">Cadastrar usuário</Button>
        </div>

        <Card v-if="temporaryPassword" class="border-amber-300 bg-amber-50 text-amber-950">
            <CardContent class="p-4">
                <p class="text-sm font-medium">Senha temporária</p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <p class="rounded-md bg-white px-3 py-2 font-mono text-lg">{{ temporaryPassword }}</p>
                    <Button type="button" variant="outline" size="sm" @click="copyTemporaryPassword"><Copy class="mr-2 size-4" />Copiar</Button>
                </div>
                <p class="mt-1 text-xs">Exibida somente agora. O usuário deverá trocar no primeiro acesso.</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Usuários da empresa</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/60 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">E-mail</th>
                                <th class="px-4 py-3 font-medium">Perfil</th>
                                <th class="px-4 py-3 font-medium">Situação</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                                <td class="px-4 py-3">{{ user.email }}</td>
                                <td class="px-4 py-3"><Badge variant="secondary">{{ user.role_label }}</Badge></td>
                                <td class="px-4 py-3"><Badge :variant="user.active ? 'success' : 'muted'">{{ user.status_label }}</Badge></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col justify-end gap-2 sm:flex-row sm:flex-wrap">
                                        <Button variant="outline" size="sm" @click="openEdit(user)">Editar</Button>
                                        <Button variant="outline" size="sm" @click="resetPassword(user)">Redefinir senha</Button>
                                        <Button v-if="user.active" variant="outline" size="sm" @click="deactivate(user)">Desativar</Button>
                                        <Button v-else variant="outline" size="sm" @click="activate(user)">Ativar</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="users.links.length > 3" class="flex flex-wrap gap-2">
            <template v-for="link in users.links" :key="link.label">
                <Button v-if="link.url" :variant="link.active ? 'default' : 'outline'" size="sm" as-child>
                    <Link :href="link.url" v-html="link.label" />
                </Button>
                <Button v-else variant="outline" size="sm" disabled v-html="link.label" />
            </template>
        </div>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent>
            <DialogHeader><DialogTitle>Novo usuário</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitCreate">
                <div class="grid gap-2"><Label for="create-name">Nome</Label><Input id="create-name" v-model="createForm.name" required /><InputError :message="createForm.errors.name" /></div>
                <div class="grid gap-2"><Label for="create-email">E-mail</Label><Input id="create-email" v-model="createForm.email" type="email" required /><InputError :message="createForm.errors.email" /></div>
                <div class="grid gap-2"><Label for="create-role">Perfil</Label><select id="create-role" v-model="createForm.role" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option></select><InputError :message="createForm.errors.role" /></div>
                <label class="flex items-center gap-2 text-sm font-medium"><input v-model="createForm.generate_password" type="checkbox" class="h-4 w-4 rounded border-input" />Gerar senha temporária</label>
                <template v-if="!createForm.generate_password">
                    <div class="grid gap-2"><Label for="create-password">Senha inicial</Label><PasswordInput id="create-password" v-model="createForm.password" autocomplete="new-password" :passwordrules="passwordRules" /><InputError :message="createForm.errors.password" /></div>
                    <div class="grid gap-2"><Label for="create-password-confirmation">Confirmar senha</Label><PasswordInput id="create-password-confirmation" v-model="createForm.password_confirmation" autocomplete="new-password" :passwordrules="passwordRules" /><InputError :message="createForm.errors.password_confirmation" /></div>
                </template>
                <DialogFooter><Button type="submit" :disabled="createForm.processing">Salvar</Button></DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="editOpen">
        <DialogContent>
            <DialogHeader><DialogTitle>Editar usuário</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-2"><Label for="edit-name">Nome</Label><Input id="edit-name" v-model="editForm.name" required /><InputError :message="editForm.errors.name" /></div>
                <div class="grid gap-2"><Label for="edit-email">E-mail</Label><Input id="edit-email" v-model="editForm.email" type="email" required /><InputError :message="editForm.errors.email" /></div>
                <div class="grid gap-2"><Label for="edit-role">Perfil</Label><select id="edit-role" v-model="editForm.role" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option></select><InputError :message="editForm.errors.role" /></div>
                <DialogFooter><Button type="submit" :disabled="editForm.processing">Salvar</Button></DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="resetOpen">
        <DialogContent>
            <DialogHeader><DialogTitle>Redefinir senha</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submitResetPassword">
                <label class="flex items-center gap-2 text-sm font-medium"><input v-model="resetForm.generate_password" type="checkbox" class="h-4 w-4 rounded border-input" />Gerar senha temporária</label>
                <template v-if="!resetForm.generate_password">
                    <div class="grid gap-2"><Label for="reset-password">Nova senha</Label><PasswordInput id="reset-password" v-model="resetForm.password" autocomplete="new-password" :passwordrules="passwordRules" /><InputError :message="resetForm.errors.password" /></div>
                    <div class="grid gap-2"><Label for="reset-password-confirmation">Confirmar senha</Label><PasswordInput id="reset-password-confirmation" v-model="resetForm.password_confirmation" autocomplete="new-password" :passwordrules="passwordRules" /><InputError :message="resetForm.errors.password_confirmation" /></div>
                </template>
                <DialogFooter><Button type="submit" :disabled="resetForm.processing">Redefinir</Button></DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
