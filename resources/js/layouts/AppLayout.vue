<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [], bare = false } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    bare?: boolean;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :bare="bare">
        <slot />
    </AppLayout>
</template>
