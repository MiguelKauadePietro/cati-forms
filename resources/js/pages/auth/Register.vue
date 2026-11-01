<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{ passwordRules: string }>();

const form = useForm({ username: '', password: '', password_confirmation: '' });

function submit() {
    if (form.processing) return;
    form.post(store.url(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Criar conta" />
    <v-card-title class="px-0"
        ><h1 class="text-2xl font-semibold">Criar conta</h1></v-card-title
    >
    <v-card-subtitle class="auth-description px-0"
        >Escolha um nome de usuário e uma senha.</v-card-subtitle
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
            autocomplete="new-password"
            variant="outlined"
            required
            :disabled="form.processing"
            :error-messages="form.errors.password"
            :passwordrules="passwordRules"
        />
        <v-text-field
            id="password_confirmation"
            v-model="form.password_confirmation"
            name="password_confirmation"
            label="Confirmar senha"
            type="password"
            autocomplete="new-password"
            variant="outlined"
            required
            :disabled="form.processing"
            :error-messages="form.errors.password_confirmation"
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
            data-test="register-user-button"
            >Criar conta</v-btn
        >
    </v-form>
    <p class="mt-6 text-center">
        Já possui conta?
        <Link :href="login()" class="auth-link">Entrar</Link>
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
