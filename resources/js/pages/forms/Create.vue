<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { createForm, FormApiError } from '@/api/forms';
import type { FormErrors } from '@/api/forms';
import { dashboard, login } from '@/routes';
import { create } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Criar formulário', href: create() },
        ],
    },
});

const form = reactive({ title: '', description: '' });
const questions = ref<{ key: number; prompt: string }[]>([]);
let nextQuestionKey = 0;

function addQuestion() {
    if (processing.value) return;
    errors.value = {};
    success.value = false;
    questions.value.push({ key: nextQuestionKey++, prompt: '' });
}

function removeQuestion(index: number) {
    if (processing.value) return;
    questions.value.splice(index, 1);
    // Server errors use array positions; discard stale mappings after removal.
    errors.value = {};
}
const errors = ref<FormErrors>({});
const processing = ref(false);
const success = ref(false);
const message = ref('');
const needsLogin = ref(false);

async function submit() {
    if (processing.value) return;

    processing.value = true;
    errors.value = {};
    success.value = false;
    message.value = '';
    needsLogin.value = false;

    try {
        await createForm({
            ...form,
            questions: questions.value.map(({ prompt }) => ({
                type: 'discursive',
                prompt,
            })),
        });
        form.title = '';
        form.description = '';
        questions.value = [];
        success.value = true;
    } catch (error) {
        if (error instanceof FormApiError && error.status === 422) {
            errors.value = error.errors;
            message.value = 'Revise os campos indicados e tente novamente.';
        } else if (
            error instanceof FormApiError &&
            [401, 419].includes(error.status)
        ) {
            needsLogin.value = true;
            message.value =
                'Seu acesso precisa ser renovado. Entre novamente em outra aba para manter este preenchimento e depois tente enviar.';
        } else {
            message.value =
                'Não foi possível confirmar a criação. Seu preenchimento foi mantido. Uma nova tentativa pode criar outro formulário se o envio anterior tiver sido concluído.';
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head title="Criar formulário" />

    <v-app class="form-creation">
        <v-main>
            <v-container class="creation-container">
                <header class="mb-8">
                    <h1 class="text-2xl font-semibold">Criar formulário</h1>
                    <p class="text-body-1 text-medium-emphasis mt-2">
                        Defina o título, a descrição e as perguntas do
                        formulário.
                    </p>
                </header>

                <v-alert
                    v-if="success"
                    type="success"
                    variant="tonal"
                    role="status"
                    class="mb-6"
                >
                    Formulário criado com sucesso.
                </v-alert>
                <v-alert
                    v-if="message"
                    type="error"
                    variant="tonal"
                    role="alert"
                    class="mb-6"
                >
                    {{ message }}
                    <a
                        v-if="needsLogin"
                        :href="login.url()"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="login-link"
                        >Entrar em outra aba</a
                    >
                </v-alert>

                <v-form :aria-busy="processing" @submit.prevent="submit">
                    <v-text-field
                        id="title"
                        v-model="form.title"
                        name="title"
                        label="Título"
                        hint="Obrigatório. Até 255 caracteres."
                        persistent-hint
                        variant="outlined"
                        required
                        maxlength="255"
                        :disabled="processing"
                        :error-messages="errors.title"
                        class="mb-6"
                    />
                    <v-textarea
                        id="description"
                        v-model="form.description"
                        name="description"
                        label="Descrição"
                        hint="Obrigatória. Até 10.000 caracteres."
                        persistent-hint
                        variant="outlined"
                        required
                        maxlength="10000"
                        rows="5"
                        auto-grow
                        :disabled="processing"
                        :error-messages="errors.description"
                        class="mb-6"
                    />
                    <section aria-labelledby="questions-heading" class="mb-8">
                        <h2
                            id="questions-heading"
                            class="mb-2 text-xl font-semibold"
                        >
                            Perguntas
                        </h2>
                        <p class="mb-6">
                            Adicione perguntas discursivas. Todas serão
                            obrigatórias para quem responder.
                        </p>
                        <v-alert
                            v-if="errors.questions"
                            type="error"
                            variant="tonal"
                            class="mb-4"
                            role="alert"
                        >
                            {{ errors.questions.join(' ') }}
                        </v-alert>
                        <div
                            v-for="(question, index) in questions"
                            :key="question.key"
                            class="mb-6"
                        >
                            <div
                                class="mb-2 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h3 class="font-semibold">
                                    Pergunta {{ index + 1 }} · Discursiva
                                </h3>
                                <v-btn
                                    type="button"
                                    variant="text"
                                    :disabled="processing"
                                    :aria-label="`Remover pergunta ${index + 1}`"
                                    @click="removeQuestion(index)"
                                    >Remover</v-btn
                                >
                            </div>
                            <v-textarea
                                :id="`question-${question.key}`"
                                v-model="question.prompt"
                                label="Enunciado"
                                hint="Obrigatório. Até 10.000 caracteres."
                                persistent-hint
                                variant="outlined"
                                required
                                maxlength="10000"
                                rows="3"
                                auto-grow
                                :disabled="processing"
                                :error-messages="
                                    errors[`questions.${index}.prompt`] ??
                                    errors[`questions.${index}.type`] ??
                                    errors[`questions.${index}`]
                                "
                            />
                        </div>
                        <v-btn
                            type="button"
                            color="primary"
                            variant="outlined"
                            :disabled="processing"
                            @click="addQuestion"
                            >Adicionar pergunta discursiva</v-btn
                        >
                    </section>
                    <p class="text-body-2 text-medium-emphasis mb-6">
                        Confira as informações antes de criar. O título e a
                        descrição e as perguntas não poderão ser editados
                        depois.
                    </p>
                    <v-btn
                        type="submit"
                        color="primary"
                        size="large"
                        :loading="processing"
                        :disabled="processing"
                        class="create-button"
                    >
                        Criar formulário
                    </v-btn>
                    <span role="status" class="sr-only">
                        {{ processing ? 'Criando formulário…' : '' }}
                    </span>
                </v-form>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.form-creation :deep(.v-application__wrap) {
    min-height: auto;
}
.creation-container {
    max-width: 760px;
    margin-inline: auto;
    padding: 32px 24px;
}
.creation-container :deep(.v-messages) {
    opacity: 1;
}
.creation-container :deep(.v-input:not(.v-input--error) .v-messages) {
    color: rgba(var(--v-theme-on-surface), 0.72);
}
.login-link {
    display: inline-block;
    margin-inline-start: 4px;
    text-decoration: underline;
}
@media (max-width: 600px) {
    .creation-container {
        padding: 24px 16px;
    }
    .create-button {
        width: 100%;
    }
}
</style>
