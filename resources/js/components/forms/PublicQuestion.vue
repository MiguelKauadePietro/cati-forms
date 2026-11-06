<script setup lang="ts">
import type { PublicQuestion } from '@/api/publicForms';

export type AnswerDraft = {
    text: string;
    option_ids: number[];
    justification: string;
};
const answer = defineModel<AnswerDraft>({ required: true });
const props = defineProps<{
    question: PublicQuestion;
    index: number;
    errors: Record<string, string[]>;
    processing: boolean;
}>();
function optionErrors(): string[] {
    const prefix = `answers.${props.index}.option_ids`;
    return Object.entries(props.errors)
        .filter(([key]) => key === prefix || key.startsWith(prefix + '.'))
        .flatMap(([, values]) => values);
}
</script>

<template>
    <v-card
        tag="section"
        variant="outlined"
        class="question-card"
        :aria-labelledby="`prompt-${question.id}`"
    >
        <h2 :id="`prompt-${question.id}`" class="question-title">
            {{ index + 1 }}. {{ question.prompt }}
        </h2>
        <p class="question-hint">Obrigatória</p>
        <v-textarea
            v-if="question.type === 'discursive'"
            :id="`answer-${question.id}`"
            v-model="answer.text"
            :aria-labelledby="`prompt-${question.id}`"
            label="Sua resposta"
            variant="outlined"
            maxlength="10000"
            rows="3"
            auto-grow
            :disabled="processing"
            :error-messages="errors[`answers.${index}.text`]"
        />
        <template v-else-if="question.type === 'multiple_choice'">
            <p class="options-hint">
                Selecione uma ou mais alternativas, sem limite de marcações.
            </p>
            <v-checkbox
                v-for="option in question.options"
                :key="option.id"
                v-model="answer.option_ids"
                :value="option.id"
                :label="option.label"
                :disabled="processing"
                hide-details
            />
            <p
                v-for="error in optionErrors()"
                :key="error"
                class="field-error"
                role="alert"
            >
                {{ error }}
            </p>
        </template>
        <template v-else>
            <v-radio-group
                :model-value="answer.option_ids[0] ?? null"
                label="Selecione uma alternativa"
                :disabled="processing"
                :error-messages="optionErrors()"
                @update:model-value="
                    answer.option_ids = $event == null ? [] : [Number($event)]
                "
            >
                <v-radio
                    v-for="option in question.options"
                    :key="option.id"
                    :value="option.id"
                    :label="option.label"
                />
            </v-radio-group>
            <v-textarea
                :id="`justification-${question.id}`"
                v-model="answer.justification"
                label="Justificativa obrigatória"
                variant="outlined"
                maxlength="10000"
                rows="3"
                auto-grow
                :disabled="processing"
                :error-messages="errors[`answers.${index}.justification`]"
            />
        </template>
        <p
            v-for="error in errors[`answers.${index}.question_id`]"
            :key="error"
            class="field-error"
            role="alert"
        >
            {{ error }}
        </p>
    </v-card>
</template>

<style scoped>
.question-card {
    padding: 24px;
    margin-bottom: 20px;
    overflow-wrap: anywhere;
}
.question-title {
    font-weight: 600;
    font-size: 1.125rem;
    white-space: pre-wrap;
}
.question-hint {
    margin: 4px 0 20px;
    font-size: 0.75rem;
    color: rgba(var(--v-theme-on-surface), 0.7);
}
.options-hint {
    margin-bottom: 8px;
    font-size: 0.875rem;
}
.field-error {
    color: rgb(var(--v-theme-error));
    font-size: 0.875rem;
    margin-top: 8px;
}
.question-card :deep(.v-label) {
    white-space: normal;
    opacity: 1;
    overflow-wrap: anywhere;
}
</style>
