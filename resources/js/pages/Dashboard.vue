<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import VuetifyDemo from '@/components/VuetifyDemo.vue';
import { dashboard } from '@/routes';

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
        <VuetifyDemo />
    </div>
</template>
