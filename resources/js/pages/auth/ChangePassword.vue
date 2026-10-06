<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';

type Props = {
    passwordRules: string;
};

defineProps<Props>();

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/definir-nova-senha', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Definir nova senha" />

    <div class="flex min-h-screen items-center justify-center bg-muted/30 p-4">
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle>Definir nova senha</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="password">Nova senha</Label>
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            autocomplete="new-password"
                            :passwordrules="passwordRules"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirmar nova senha</Label>
                        <PasswordInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            autocomplete="new-password"
                            :passwordrules="passwordRules"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <Button class="w-full" :disabled="form.processing">Salvar senha</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
