<script setup lang="ts">
import { ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

const { updateAppearance } = useAppearance();
const name = ref('');
const submitted = ref(false);
</script>

<template>
    <v-app class="vuetify-demo">
        <v-main>
            <v-container>
                <v-card variant="outlined" title="Experimente os componentes">
                    <v-card-text>
                        <p class="mb-6">
                            Demonstração local da interface do CATI Forms. Os
                            dados preenchidos aqui não são enviados nem
                            armazenados.
                        </p>
                        <v-form @submit.prevent="submitted = true">
                            <v-text-field
                                v-model="name"
                                label="Seu nome"
                                prepend-inner-icon="mdi-account-outline"
                                variant="outlined"
                                @update:model-value="submitted = false"
                            />
                            <v-btn
                                type="submit"
                                color="primary"
                                prepend-icon="mdi-check"
                            >
                                Testar interação
                            </v-btn>
                        </v-form>
                        <v-alert
                            v-if="submitted"
                            class="mt-4"
                            type="success"
                            variant="tonal"
                            role="status"
                        >
                            {{ name ? `Olá, ${name}!` : 'Tudo pronto!' }} Os
                            componentes estão funcionando.
                        </v-alert>
                    </v-card-text>
                    <v-card-actions class="flex-wrap">
                        <v-btn
                            prepend-icon="mdi-white-balance-sunny"
                            @click="updateAppearance('light')"
                        >
                            Claro
                        </v-btn>
                        <v-btn
                            prepend-icon="mdi-weather-night"
                            @click="updateAppearance('dark')"
                        >
                            Escuro
                        </v-btn>
                        <v-btn
                            prepend-icon="mdi-monitor"
                            @click="updateAppearance('system')"
                        >
                            Sistema
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.vuetify-demo :deep(.v-application__wrap) {
    min-height: auto;
}
</style>
