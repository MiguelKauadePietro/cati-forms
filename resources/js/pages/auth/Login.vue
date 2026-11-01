<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { register } from '@/routes';
import { store } from '@/routes/login';

const form = useForm({ username: '', password: '', remember: false });

function submit() {
    if (form.processing) return;
    form.post(store.url(), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Entrar" />
    <v-card-title class="px-0"
        ><h1 class="text-2xl font-semibold">Entrar</h1></v-card-title
    >
    <v-card-subtitle class="auth-description px-0"
        >Acesse com seu nome de usuário e senha.</v-card-subtitle
    >
    <v-form class="mt-6" @submit.prevent="submit">
        <v-text-field
            id="username"
            v-model="form.username"
            name="username"
            label="Nome de usuário"
            type="text"
            autocomplete="username"
            variant="outlined"
            required
            :disabled="form.processing"
            :error-messages="form.errors.username"
            autofocus
            maxlength="255"
        />
        <v-text-field
            id="password"
            v-model="form.password"
            name="password"
            label="Senha"
            type="password"
            autocomplete="current-password"
            variant="outlined"
            required
            :disabled="form.processing"
            :error-messages="form.errors.password"
        />
        <v-checkbox
            v-model="form.remember"
            label="Manter conectado"
            :disabled="form.processing"
            hide-details
            class="mb-4"
        />
        <div aria-live="polite" class="sr-only">
            {{
                form.processing
                    ? 'Enviando…'
                    : Object.values(form.errors).join('. ')
            }}
        </div>
        <v-btn
            type="submit"
            color="primary"
            block
            size="large"
            :loading="form.processing"
            :disabled="form.processing"
            data-test="login-button"
            >Entrar</v-btn
        >
    </v-form>
    <p class="mt-6 text-center">
        Ainda não possui conta?
        <Link :href="register()" class="auth-link">Criar conta</Link>
    </p>
</template>

<style scoped>
.auth-description {
    white-space: normal;
    opacity: 1;
}
.auth-link {
    color: var(--link);
    text-decoration: underline;
    text-underline-offset: 3px;
}
.auth-link:hover {
    color: var(--link-hover);
    text-decoration-thickness: 2px;
}
.auth-link:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 4px;
}
</style>
