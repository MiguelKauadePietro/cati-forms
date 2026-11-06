<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import ShareFormLink from '@/components/forms/ShareFormLink.vue';
import { listMyForms } from '@/api/forms';
import type { MyFormsPage } from '@/api/forms';
import { dashboard } from '@/routes';
import { create } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const result = ref<MyFormsPage | null>(null);
const page = ref(1);
const loading = ref(true);
const error = ref('');
const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'medium',
});

async function loadForms() {
    loading.value = true;
    error.value = '';
    try {
        result.value = await listMyForms(page.value);
    } catch (cause) {
        error.value =
            cause instanceof Error
                ? cause.message
                : 'Não foi possível carregar seus formulários. Tente novamente.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadForms);
</script>

<template>
    <Head title="Dashboard" />

    <v-app class="dashboard-app">
        <v-main>
            <v-container class="dashboard-content">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Meus formulários</h1>
                        <p class="dashboard-intro">
                            Formulários criados por você, dos mais recentes aos
                            mais antigos.
                        </p>
                    </div>
                    <v-btn
                        :tag="Link"
                        :href="create().url"
                        color="primary"
                        prepend-icon="mdi-plus"
                    >
                        Criar formulário
                    </v-btn>
                </div>

                <div
                    v-if="loading"
                    class="loading-state"
                    role="status"
                    aria-live="polite"
                >
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        aria-hidden="true"
                    />
                    <p class="state-action">Carregando formulários…</p>
                </div>
                <v-alert
                    v-else-if="error"
                    type="error"
                    variant="tonal"
                    role="alert"
                >
                    {{ error }}
                    <div class="state-action">
                        <v-btn variant="outlined" @click="loadForms"
                            >Tentar novamente</v-btn
                        >
                    </div>
                </v-alert>
                <v-sheet
                    v-else-if="!result?.data.length"
                    rounded="lg"
                    border
                    class="empty-state"
                >
                    <h2 class="section-title">Nenhum formulário por aqui</h2>
                    <p class="empty-description">
                        Você ainda não criou formulários. Use “Criar formulário”
                        para começar.
                    </p>
                </v-sheet>
                <template v-else>
                    <v-row>
                        <v-col
                            v-for="form in result.data"
                            :key="form.id"
                            cols="12"
                            md="6"
                            lg="4"
                        >
                            <v-card
                                tag="article"
                                variant="outlined"
                                class="form-card"
                            >
                                <h2 class="section-title">{{ form.title }}</h2>
                                <p class="description">
                                    {{ form.description }}
                                </p>
                                <p class="created-date">
                                    Criado em
                                    <time :datetime="form.created_at">{{
                                        dateFormatter.format(
                                            new Date(form.created_at),
                                        )
                                    }}</time>
                                </p>
                                <ShareFormLink
                                    :url="form.public_url"
                                    class="share-action"
                                />
                            </v-card>
                        </v-col>
                    </v-row>
                    <v-pagination
                        v-if="result.meta.last_page > 1"
                        v-model="page"
                        class="forms-pagination"
                        :length="result.meta.last_page"
                        :total-visible="3"
                        aria-label="Páginas de meus formulários"
                        @update:model-value="loadForms"
                    />
                </template>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.dashboard-app :deep(.v-application__wrap) {
    min-height: 0;
}
.dashboard-content {
    padding: 24px;
}
.dashboard-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}
.dashboard-title {
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.4;
}
.dashboard-intro,
.empty-description {
    margin-top: 8px;
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.875rem;
}
.loading-state {
    padding: 40px 0;
    text-align: center;
}
.state-action {
    margin-top: 12px;
}
.empty-state {
    padding: 32px 24px;
    text-align: center;
}
.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.5;
}
.form-card {
    height: 100%;
    padding: 20px;
    overflow-wrap: anywhere;
}
.description {
    margin-top: 12px;
    font-size: 0.875rem;
    line-height: 1.6;
    white-space: pre-wrap;
}
.share-action {
    margin-top: 16px;
}
.created-date {
    margin-top: 20px;
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.75rem;
}
.forms-pagination {
    margin-top: 24px;
}
@media (max-width: 600px) {
    .dashboard-content {
        padding: 16px;
    }
}
</style>
