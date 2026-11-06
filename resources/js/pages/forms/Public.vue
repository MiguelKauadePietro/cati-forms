<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import {
    getPublicForm,
    PublicFormApiError,
    submitPublicForm,
} from '@/api/publicForms';
import type { PublicForm, SubmissionErrors } from '@/api/publicForms';
import PublicQuestion from '@/components/forms/PublicQuestion.vue';
import type { AnswerDraft } from '@/components/forms/PublicQuestion.vue';

const props = defineProps<{ publicId: string }>();
const form = ref<PublicForm | null>(null);
const answers = ref<AnswerDraft[]>([]);
const contactEmail = ref('');
const loading = ref(true);
const processing = ref(false);
const success = ref(false);
const loadError = ref('');
const message = ref('');
const errors = ref<SubmissionErrors>({});

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        form.value = await getPublicForm(props.publicId);
        answers.value = form.value.questions.map(() => ({
            text: '',
            option_ids: [],
            justification: '',
        }));
    } catch (error) {
        loadError.value =
            error instanceof PublicFormApiError && error.status === 404
                ? 'O formulário não foi encontrado. Confira o link recebido.'
                : 'Não foi possível carregar o formulário. Tente novamente.';
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!form.value || processing.value || success.value) return;
    processing.value = true;
    message.value = '';
    errors.value = {};
    try {
        await submitPublicForm(props.publicId, {
            ...(form.value.request_contact_email
                ? { contact_email: contactEmail.value }
                : {}),
            answers: form.value.questions.map((question, index) => {
                const draft = answers.value[index];
                return {
                    question_id: question.id,
                    ...(question.type === 'discursive'
                        ? { text: draft.text }
                        : { option_ids: draft.option_ids }),
                    ...(question.type === 'single_choice_with_justification'
                        ? { justification: draft.justification }
                        : {}),
                };
            }),
        });
        success.value = true;
        contactEmail.value = '';
        answers.value = [];
    } catch (error) {
        if (error instanceof PublicFormApiError && error.status === 422) {
            errors.value = error.errors;
            message.value = 'Revise as respostas indicadas antes de enviar.';
        } else if (
            error instanceof PublicFormApiError &&
            error.status === 419
        ) {
            message.value =
                'Sua sessão expirou. Abra este mesmo link em outra aba e tente enviar novamente. Seu preenchimento foi mantido.';
        } else if (
            error instanceof PublicFormApiError &&
            error.status === 429
        ) {
            message.value =
                'Muitos envios em pouco tempo. Aguarde um minuto antes de tentar novamente.';
        } else {
            message.value =
                'Não foi possível confirmar o envio. Seu preenchimento foi mantido. Uma nova tentativa pode repetir a resposta se o envio anterior tiver sido concluído.';
        }
    } finally {
        processing.value = false;
    }
}
onMounted(load);
</script>

<template>
    <Head :title="form?.title ?? 'Responder formulário'" />
    <v-app>
        <v-main>
            <v-container class="public-container">
                <p class="brand">cati-forms</p>
                <div v-if="loading" class="loading-state" role="status">
                    <v-progress-circular
                        color="primary"
                        indeterminate
                        aria-hidden="true"
                    />
                    <p>Carregando formulário…</p>
                </div>
                <v-alert
                    v-else-if="loadError"
                    type="error"
                    variant="tonal"
                    role="alert"
                >
                    {{ loadError }}
                    <v-btn class="retry" variant="outlined" @click="load"
                        >Tentar novamente</v-btn
                    >
                </v-alert>
                <template v-else-if="form">
                    <header class="form-header">
                        <h1>{{ form.title }}</h1>
                        <p class="description">{{ form.description }}</p>
                    </header>
                    <v-alert
                        v-if="success"
                        type="success"
                        variant="tonal"
                        role="status"
                    >
                        Resposta enviada com sucesso. Obrigado por participar!
                    </v-alert>
                    <v-form
                        v-else
                        :aria-busy="processing"
                        @submit.prevent="submit"
                    >
                        <v-alert
                            v-if="message"
                            type="error"
                            variant="tonal"
                            role="alert"
                            class="submission-message"
                            >{{ message }}</v-alert
                        >
                        <v-text-field
                            v-if="form.request_contact_email"
                            id="contact-email"
                            v-model="contactEmail"
                            label="E-mail de contato (opcional)"
                            type="email"
                            autocomplete="email"
                            variant="outlined"
                            maxlength="254"
                            :disabled="processing"
                            :error-messages="errors.contact_email"
                            class="contact-email"
                        />
                        <p
                            v-for="error in errors.answers"
                            :key="error"
                            class="field-error"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <PublicQuestion
                            v-for="(question, index) in form.questions"
                            :key="question.id"
                            :model-value="answers[index]"
                            :question="question"
                            :index="index"
                            :processing="processing"
                            :errors="errors"
                            @update:model-value="answers[index] = $event"
                        />
                        <v-btn
                            type="submit"
                            color="primary"
                            size="large"
                            :loading="processing"
                            :disabled="processing"
                            data-test="submit-response"
                            >Enviar resposta</v-btn
                        >
                    </v-form>
                </template>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.public-container {
    max-width: 800px;
    padding: 32px 24px 48px;
}
.brand {
    margin-bottom: 24px;
    font-weight: 600;
    color: rgb(var(--v-theme-primary));
}
h1 {
    font-weight: 600;
    font-size: clamp(1.5rem, 5vw, 2rem);
    overflow-wrap: anywhere;
}
.form-header {
    margin-bottom: 32px;
}
.description {
    margin-top: 12px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.6;
}
.contact-email,
.submission-message {
    margin-bottom: 24px;
}
.loading-state {
    text-align: center;
    padding: 48px 0;
}
.loading-state p {
    margin-top: 16px;
}
.retry {
    display: block;
    margin-top: 16px;
}
.field-error {
    color: rgb(var(--v-theme-error));
    margin-bottom: 16px;
}
@media (max-width: 600px) {
    .public-container {
        padding: 24px 16px;
    }
}
</style>
