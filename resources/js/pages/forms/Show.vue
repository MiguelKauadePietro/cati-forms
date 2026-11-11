<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { downloadResponses, getForm, listSubmissions } from '@/api/forms';
import type { ExportFormat, FormRecord, SubmissionsPage } from '@/api/forms';
import ShareFormLink from '@/components/forms/ShareFormLink.vue';
import { index } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Todos os formulários', href: index() },
            { title: 'Detalhes e respostas' },
        ],
    },
});
const props = defineProps<{ formId: number }>();
const form = ref<FormRecord | null>(null);
const result = ref<SubmissionsPage | null>(null);
const page = ref(1);
const loading = ref(true);
const error = ref('');
const responsesLoading = ref(false);
const responsesError = ref('');
const downloading = ref<ExportFormat | null>(null);
const downloadError = ref('');
const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const typeLabels = {
    discursive: 'Discursiva',
    multiple_choice: 'Múltipla escolha',
    single_choice_with_justification: 'Escolha única com justificativa',
};
async function loadResponses() {
    responsesLoading.value = true;
    responsesError.value = '';
    try {
        result.value = await listSubmissions(props.formId, page.value);
    } catch (cause) {
        responsesError.value =
            cause instanceof Error
                ? cause.message
                : 'Não foi possível carregar as respostas.';
    } finally {
        responsesLoading.value = false;
    }
}
async function load() {
    loading.value = true;
    error.value = '';
    try {
        form.value = await getForm(props.formId);
        await loadResponses();
    } catch (cause) {
        error.value =
            cause instanceof Error
                ? cause.message
                : 'Não foi possível carregar o formulário.';
    } finally {
        loading.value = false;
    }
}
async function exportResponses(format: ExportFormat) {
    if (downloading.value) return;
    downloading.value = format;
    downloadError.value = '';
    try {
        await downloadResponses(props.formId, format);
    } catch (cause) {
        downloadError.value =
            cause instanceof Error
                ? cause.message
                : 'Não foi possível baixar o arquivo. Tente novamente.';
    } finally {
        downloading.value = null;
    }
}
onMounted(load);
</script>

<template>
    <Head :title="form?.title ?? 'Detalhes e respostas'" />
    <v-app class="forms-app">
        <v-main>
            <v-container class="forms-content content">
                <v-btn
                    :tag="Link"
                    :href="index().url"
                    variant="text"
                    prepend-icon="mdi-arrow-left"
                    class="mb-4"
                    >Todos os formulários</v-btn
                >
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
                    <p class="mt-3">Carregando formulário…</p>
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
                <template v-else-if="form">
                    <h1 class="page-title">{{ form.title }}</h1>
                    <p class="text-content mt-3">{{ form.description }}</p>
                    <ShareFormLink :url="form.public_url" class="mt-4" />
                    <h2 class="section-title mt-8 mb-4">Perguntas</h2>
                    <p v-if="!form.questions.length">
                        Este formulário não possui perguntas.
                    </p>
                    <section
                        v-for="question in form.questions"
                        :key="question.id"
                        class="mb-5"
                    >
                        <h3 class="question-title">
                            {{ question.position }}. {{ question.prompt }}
                        </h3>
                        <p class="metadata">
                            {{ typeLabels[question.type] }}
                        </p>
                        <ul v-if="question.options.length" class="mt-2 pl-6">
                            <li
                                v-for="option in question.options"
                                :key="option.id"
                            >
                                {{ option.label }}
                            </li>
                        </ul>
                    </section>
                    <h2 class="section-title mt-8 mb-4">
                        Respostas recebidas<span v-if="result">
                            ({{ result.meta.total }})</span
                        >
                    </h2>
                    <div class="export-actions">
                        <v-btn
                            variant="outlined"
                            color="primary"
                            prepend-icon="mdi-file-delimited-outline"
                            :loading="downloading === 'csv'"
                            :disabled="downloading !== null"
                            @click="exportResponses('csv')"
                            >Exportar CSV</v-btn
                        >
                        <v-btn
                            variant="outlined"
                            color="primary"
                            prepend-icon="mdi-file-pdf-box"
                            :loading="downloading === 'pdf'"
                            :disabled="downloading !== null"
                            @click="exportResponses('pdf')"
                            >Exportar PDF</v-btn
                        >
                    </div>
                    <v-alert
                        v-if="downloadError"
                        type="error"
                        variant="tonal"
                        role="alert"
                        class="mb-4"
                    >
                        {{ downloadError }}
                    </v-alert>
                    <p v-if="responsesLoading" role="status" aria-live="polite">
                        Carregando respostas…
                    </p>
                    <v-alert
                        v-else-if="responsesError"
                        type="error"
                        variant="tonal"
                        role="alert"
                    >
                        {{ responsesError }}
                        <v-btn
                            class="d-block mt-3"
                            variant="outlined"
                            @click="loadResponses"
                            >Tentar novamente</v-btn
                        >
                    </v-alert>
                    <p v-else-if="!result?.data.length">
                        Nenhuma resposta recebida nesta página.
                    </p>
                    <template v-else>
                        <v-card
                            v-for="submission in result.data"
                            :key="submission.id"
                            tag="article"
                            variant="outlined"
                            class="response-card"
                        >
                            <h3 class="question-title">
                                Envio #{{ submission.id }}
                            </h3>
                            <p class="metadata mt-1">
                                <time :datetime="submission.created_at">{{
                                    dateFormatter.format(
                                        new Date(submission.created_at),
                                    )
                                }}</time>
                            </p>
                            <p v-if="submission.contact_email" class="mt-2">
                                E-mail: {{ submission.contact_email }}
                            </p>
                            <section
                                v-for="question in form.questions"
                                :key="question.id"
                                class="mt-5"
                            >
                                <h4 class="question-title">
                                    {{ question.position }}.
                                    {{ question.prompt }}
                                </h4>
                                <template
                                    v-for="answer in submission.answers.filter(
                                        (item) =>
                                            item.question_id === question.id,
                                    )"
                                    :key="answer.id"
                                >
                                    <p
                                        v-if="answer.text !== null"
                                        class="text-content mt-2"
                                    >
                                        {{ answer.text }}
                                    </p>
                                    <ul
                                        v-if="answer.options.length"
                                        class="mt-2 pl-6"
                                    >
                                        <li
                                            v-for="option in answer.options"
                                            :key="option.id"
                                        >
                                            {{ option.label }}
                                        </li>
                                    </ul>
                                    <p
                                        v-if="answer.justification !== null"
                                        class="text-content mt-2"
                                    >
                                        <strong>Justificativa:</strong>
                                        {{ answer.justification }}
                                    </p>
                                </template>
                            </section>
                            <p v-if="!submission.answers.length" class="mt-3">
                                Envio sem perguntas.
                            </p>
                        </v-card>
                    </template>
                    <v-pagination
                        v-if="result && result.meta.last_page > 1"
                        v-model="page"
                        :length="result.meta.last_page"
                        :total-visible="3"
                        :disabled="responsesLoading"
                        aria-label="Páginas de respostas"
                        @update:model-value="loadResponses"
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
.content {
    max-width: 1000px;
    overflow-wrap: anywhere;
}
.text-content {
    white-space: pre-wrap;
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
.question-title {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.5;
}
.metadata {
    font-size: 0.875rem;
    color: rgba(var(--v-theme-on-surface), 0.7);
}
.export-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.response-card {
    padding: 20px;
    margin-bottom: 16px;
}
@media (max-width: 600px) {
    .forms-content {
        padding: 16px;
    }
}
</style>
