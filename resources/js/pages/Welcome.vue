<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Locafy" />

    <main
        class="flex min-h-screen flex-col bg-background text-foreground"
    >
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5"
        >
            <p class="text-sm font-semibold tracking-wide">LOCAFY</p>
            <Button as-child>
                <Link v-if="$page.props.auth.user" :href="dashboard()">
                    Dashboard
                </Link>
                <Link v-else :href="login()">Entrar</Link>
            </Button>
        </header>

        <section
            class="mx-auto flex w-full max-w-6xl flex-1 items-center px-6 py-12"
        >
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-primary">
                    Gestão de locações
                </p>
                <h1
                    class="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl"
                >
                    Locafy
                </h1>
                <p class="mt-4 max-w-2xl text-base text-muted-foreground">
                    Controle clientes, produtos e equipamentos com uma base
                    preparada para contratos, cobranças e operações de locação.
                </p>
                <div class="mt-8">
                    <Button as-child>
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : login()
                            "
                        >
                            Acessar sistema
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    </main>
</template>
