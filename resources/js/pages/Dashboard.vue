<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import VuetifyDemo from '@/components/VuetifyDemo.vue';
import { dashboard } from '@/routes';
import { create } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col p-4">
        <div class="mb-4">
            <Link
                :href="create()"
                class="inline-flex rounded-md bg-primary px-4 py-3 font-medium text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Criar formulário
            </Link>
        </div>
        <VuetifyDemo />
    </div>
</template>
