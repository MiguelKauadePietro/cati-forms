<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { home } from '@/routes';

const props = defineProps<{ status: 403 | 404 | 500 }>();
const content = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: 'Acesso não permitido',
                message: 'Você não tem permissão para acessar esta página.',
            };
        case 404:
            return {
                title: 'Página não encontrada',
                message:
                    'O endereço acessado não corresponde a uma página disponível.',
            };
        default:
            return {
                title: 'Não foi possível carregar a página',
                message:
                    'Ocorreu um erro no servidor. Tente novamente mais tarde.',
            };
    }
});
</script>

<template>
    <Head :title="content.title" />
    <v-app>
        <v-main class="error-page">
            <v-container class="error-content">
                <p class="error-status">Erro {{ status }}</p>
                <h1>{{ content.title }}</h1>
                <p class="error-message">{{ content.message }}</p>
                <v-btn :tag="Link" :href="home.url()" color="primary">
                    Ir para o início
                </v-btn>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.error-page {
    display: flex;
    align-items: center;
}
.error-content {
    max-width: 640px;
    padding: 32px 24px;
    text-align: center;
}
.error-status {
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
    margin-bottom: 12px;
}
h1 {
    font-size: clamp(1.5rem, 5vw, 2rem);
    font-weight: 600;
    line-height: 1.3;
}
.error-message {
    margin: 16px 0 24px;
    line-height: 1.6;
}
</style>
