<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: '',
        description: '',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Entrar | Locafy" />

    <div class="w-full">
        <!-- Logo -->
        <img
            src="/images/logo2.png"
            alt="Locafy"
            class="mx-auto mb-7 h-28 w-auto object-contain sm:h-32"
        />

        <!-- Status -->
        <div
            v-if="status"
            class="mb-5 rounded-xl border border-emerald-200
                   bg-emerald-50 px-4 py-3 text-center
                   text-sm font-medium text-emerald-700
                   dark:border-emerald-500/20
                   dark:bg-emerald-500/10
                   dark:text-emerald-400"
        >
            {{ status }}
        </div>

        <!-- Card -->
        <div
            class="rounded-3xl border border-slate-200
                   bg-white/95 p-6
                   shadow-xl shadow-slate-200/50
                   backdrop-blur-xl
                   dark:border-slate-800
                   dark:bg-slate-900/70
                   dark:shadow-black/20
                   sm:p-8"
        >
            <div class="mb-6">
                <h2
                    class="text-xl font-semibold
                           text-slate-900
                           dark:text-slate-100"
                >
                    Acesse sua conta
                </h2>

                <p
                    class="mt-1 text-sm
                           text-slate-500
                           dark:text-slate-400"
                >
                    Informe seus dados para continuar.
                </p>
            </div>

            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-5"
            >
                <!-- Email -->
                <div class="grid gap-2">
                    <Label
                        for="email"
                        class="text-sm font-medium
                               text-slate-700
                               dark:text-slate-300"
                    >
                        E-mail
                    </Label>

                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        v-focus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="seuemail@exemplo.com"
                        class="h-12 rounded-xl
                               border-slate-300
                               bg-white px-4
                               text-slate-900
                               placeholder:text-slate-400
                               focus-visible:border-blue-500
                               focus-visible:ring-blue-500/20
                               dark:border-slate-700
                               dark:bg-slate-950/70
                               dark:text-slate-100
                               dark:placeholder:text-slate-600"
                    />

                    <InputError :message="errors.email" />
                </div>

                <!-- Senha -->
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label
                            for="password"
                            class="text-sm font-medium
                                   text-slate-700
                                   dark:text-slate-300"
                        >
                            Senha
                        </Label>

                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-xs font-medium
                                   text-blue-600 transition
                                   hover:text-blue-500
                                   dark:text-blue-400
                                   dark:hover:text-blue-300"
                            :tabindex="5"
                        >
                            Esqueceu a senha?
                        </TextLink>
                    </div>

                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Digite sua senha"
                        class="h-12 rounded-xl
                               border-slate-300
                               bg-white px-4
                               text-slate-900
                               placeholder:text-slate-400
                               focus-visible:border-blue-500
                               focus-visible:ring-blue-500/20
                               dark:border-slate-700
                               dark:bg-slate-950/70
                               dark:text-slate-100
                               dark:placeholder:text-slate-600"
                    />

                    <InputError :message="errors.password" />
                </div>

                <!-- Manter conectado -->
                <div class="flex items-center">
                    <Label
                        for="remember"
                        class="flex cursor-pointer items-center gap-3
                               text-sm font-normal
                               text-slate-600
                               dark:text-slate-400"
                    >
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                            class="border-slate-400
                                   data-[state=checked]:border-blue-600
                                   data-[state=checked]:bg-blue-600
                                   dark:border-slate-600
                                   dark:data-[state=checked]:border-blue-500
                                   dark:data-[state=checked]:bg-blue-500"
                        />

                        <span>Manter conectado</span>
                    </Label>
                </div>

                <!-- Botão -->
                <Button
                    type="submit"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                    class="mt-2 h-12 w-full rounded-xl
                           bg-gradient-to-r
                           from-blue-600 to-cyan-500
                           text-base font-semibold text-white
                           shadow-lg shadow-blue-600/20
                           transition-all duration-200
                           hover:from-blue-500
                           hover:to-cyan-400
                           hover:shadow-blue-500/30
                           disabled:opacity-60"
                >
                    <Spinner v-if="processing" />

                    <span>
                        {{ processing ? 'Entrando...' : 'Entrar' }}
                    </span>
                </Button>
            </Form>
        </div>

        <!-- Rodapé -->
        <div class="mt-6 text-center">
            <p
                class="text-xs
                       text-slate-400
                       dark:text-slate-600"
            >
                © 2026 Locafy • Gestão de locações
            </p>
        </div>
    </div>
</template>
