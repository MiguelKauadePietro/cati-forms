<script setup lang="ts">
import type { FormErrors } from '@/api/forms';
import type { QuestionDraft } from '@/types/forms';

const question = defineModel<QuestionDraft>({ required: true });
const props = defineProps<{
    index: number;
    errors: FormErrors;
    processing: boolean;
}>();
const emit = defineEmits<{ remove: []; 'clear-errors': [] }>();
const types = [
    { title: 'Discursiva', value: 'discursive' },
    { title: 'Múltipla escolha', value: 'multiple_choice' },
    {
        title: 'Escolha única com justificativa',
        value: 'single_choice_with_justification',
    },
];
let nextOptionKey = 0;
function addOption() {
    if (props.processing) return;
    question.value.options.push({ key: nextOptionKey++, label: '' });
    emit('clear-errors');
}
function removeOption(index: number) {
    if (props.processing) return;
    question.value.options.splice(index, 1);
    emit('clear-errors');
}
function fieldErrors(field: string) {
    return props.errors[`questions.${props.index}.${field}`];
}
</script>

<template>
    <section :aria-labelledby="`heading-${question.key}`" class="mb-8">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 :id="`heading-${question.key}`" class="font-semibold">
                Pergunta {{ index + 1 }}
            </h3>
            <v-btn
                type="button"
                variant="text"
                :disabled="processing"
                :aria-label="`Remover pergunta ${index + 1}`"
                @click="emit('remove')"
                >Remover</v-btn
            >
        </div>
        <v-select
            v-model="question.type"
            :items="types"
            label="Tipo de pergunta"
            variant="outlined"
            :disabled="processing"
            :error-messages="fieldErrors('type')"
            @update:model-value="emit('clear-errors')"
        />
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
                fieldErrors('prompt') ?? errors[`questions.${index}`]
            "
            class="mb-4"
        />
        <template v-if="question.type !== 'discursive'">
            <p class="mb-4 text-sm">
                {{
                    question.type === 'multiple_choice'
                        ? 'O respondente poderá marcar várias alternativas, sem limite de marcações.'
                        : 'O respondente deverá escolher uma alternativa e justificar sua escolha.'
                }}
            </p>
            <v-alert
                v-if="fieldErrors('options')"
                type="error"
                variant="tonal"
                role="alert"
                class="mb-4"
                >{{ fieldErrors('options')?.join(' ') }}</v-alert
            >
            <div
                v-for="(option, optionIndex) in question.options"
                :key="option.key"
                class="mb-4"
            >
                <v-textarea
                    :id="`question-${question.key}-option-${option.key}`"
                    v-model="option.label"
                    :label="`Alternativa ${optionIndex + 1}`"
                    hint="Obrigatória. Até 10.000 caracteres."
                    persistent-hint
                    variant="outlined"
                    rows="1"
                    auto-grow
                    required
                    maxlength="10000"
                    :disabled="processing"
                    :error-messages="
                        fieldErrors(`options.${optionIndex}.label`) ??
                        fieldErrors(`options.${optionIndex}`)
                    "
                />
                <v-btn
                    type="button"
                    variant="text"
                    :disabled="processing"
                    :aria-label="`Remover alternativa ${optionIndex + 1} da pergunta ${index + 1}`"
                    @click="removeOption(optionIndex)"
                    >Remover alternativa</v-btn
                >
            </div>
            <v-btn
                type="button"
                variant="outlined"
                color="primary"
                :disabled="processing"
                @click="addOption"
                >Adicionar alternativa</v-btn
            >
        </template>
    </section>
</template>
