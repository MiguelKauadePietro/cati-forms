<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ url: string }>();
const copied = ref(false);
const manualCopy = ref(false);

async function copy(url: string) {
    copied.value = false;
    try {
        await navigator.clipboard.writeText(url);
        manualCopy.value = false;
        copied.value = true;
    } catch {
        manualCopy.value = true;
    }
}
</script>

<template>
    <div>
        <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-content-copy"
            @click="copy(url)"
        >
            Copiar link
        </v-btn>
        <p v-if="copied" role="status" class="share-feedback">Link copiado.</p>
        <v-text-field
            v-if="manualCopy"
            :model-value="url"
            label="Link público"
            hint="Selecione e copie o endereço para compartilhar."
            persistent-hint
            readonly
            variant="outlined"
            class="manual-link"
        />
    </div>
</template>

<style scoped>
.share-feedback {
    margin-top: 8px;
}
.manual-link {
    margin-top: 16px;
}
</style>
