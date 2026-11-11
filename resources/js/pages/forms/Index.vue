<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { listForms } from '@/api/forms';
import type { MyFormsPage } from '@/api/forms';
import { create, index, show } from '@/routes/forms';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Todos os formulários', href: index() }] },
});
const result = ref<MyFormsPage | null>(null);
const page = ref(1);
const loading = ref(true);
const error = ref('');
async function load() {
    loading.value = true;
    error.value = '';
    try {
        result.value = await listForms(page.value);
    } catch (cause) {
        error.value =
            cause instanceof Error
                ? cause.message
                : 'Não foi possível carregar os formulários.';
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>

<template>
    <Head title="Todos os formulários" />
    <v-app class="forms-app">
        <v-main>
            <v-container class="forms-content">
                <div class="list-header">
                    <div>
                        <h1 class="page-title">Todos os formulários</h1>
                        <p class="mt-2">
                            Consulte os detalhes e as respostas de qualquer
                            formulário.
                        </p>
                    </div>
                    <v-btn
                        :tag="Link"
                        :href="create().url"
                        color="primary"
                        prepend-icon="mdi-plus"
                        >Criar formulário</v-btn
                    >
                </div>
                <div
                    v-if="loading"
                    role="status"
                    aria-live="polite"
                    class="py-10 text-center"
                >
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        aria-hidden="true"
                    />
                    <p class="mt-3">Carregando formulários…</p>
                </div>
                <v-alert
                    v-else-if="error"
                    type="error"
                    variant="tonal"
                    role="alert"
                >
                    {{ error }}
                    <v-btn class="d-block mt-3" variant="outlined" @click="load"
                        >Tentar novamente</v-btn
                    >
                </v-alert>
                <v-sheet
                    v-else-if="!result?.data.length"
                    border
                    rounded="lg"
                    class="empty-state"
                    >Nenhum formulário cadastrado.</v-sheet
                >
                <template v-else>
                    <v-list lines="three" class="forms-list">
                        <v-list-item
                            v-for="form in result.data"
                            :key="form.id"
                            class="form-item py-4"
                        >
                            <h2 class="section-title">{{ form.title }}</h2>
                            <p class="description mt-2">
                                {{ form.description }}
                            </p>
                            <v-btn
                                :tag="Link"
                                :href="show(form.id).url"
                                variant="text"
                                color="primary"
                                class="mt-2"
                                >Detalhes e respostas</v-btn
                            >
                        </v-list-item>
                    </v-list>
                    <v-pagination
                        v-if="result.meta.last_page > 1"
                        v-model="page"
                        :length="result.meta.last_page"
                        :total-visible="3"
                        aria-label="Páginas de todos os formulários"
                        @update:model-value="load"
                    />
                </template>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.forms-app :deep(.v-application__wrap) {
    min-height: 0;
}
.form-item {
    padding-block: 16px;
    border-bottom: 1px solid
        rgba(var(--v-border-color), var(--v-border-opacity));
}
.description {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
h2 {
    overflow-wrap: anywhere;
}
.forms-content {
    padding: 24px;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.4;
}
.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.5;
}
.list-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}
.empty-state {
    padding: 24px;
    text-align: center;
}
.forms-list {
    padding: 0;
}
@media (max-width: 600px) {
    .forms-content {
        padding: 16px;
    }
}
</style>
