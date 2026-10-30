<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{ icon?: LucideIcon; title: string; description?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-14 text-center"
    >
        <div
            v-if="icon"
            class="mb-4 flex size-11 items-center justify-center rounded bg-secondary text-primary"
        >
            <component :is="icon" class="size-5" />
        </div>
        <p class="text-sm font-medium">{{ title }}</p>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-pretty text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
