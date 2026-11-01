<template>
    <v-app>
        <v-main class="auth-main">
            <v-container class="auth-container">
                <p class="mb-6 text-center text-xl font-semibold">CATI Forms</p>
                <v-card variant="outlined" rounded="lg" class="auth-card">
                    <slot />
                </v-card>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.auth-card {
    padding: 24px;
}
.auth-card :deep(.v-card-title),
.auth-card :deep(.v-card-subtitle) {
    padding-inline: 0;
}
.auth-main {
    display: flex;
    align-items: center;
}
.auth-container {
    width: 100%;
    max-width: 480px;
    margin-inline: auto;
    padding: 24px 16px;
}
</style>
