<script setup lang="ts">
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from '@lucide/vue';

const props = defineProps<{ value: string }>();
const { copy, copied } = useClipboard({ legacy: true });
</script>

<template>
    <button
        type="button"
        class="group inline-flex max-w-full items-center gap-1.5 rounded-md border bg-muted/40 px-2 py-1 text-left font-mono text-xs hover:bg-accent"
        :title="`Salin ${props.value}`"
        @click="copy(props.value)"
    >
        <span class="truncate">{{ props.value }}</span>
        <Check v-if="copied" class="size-3.5 shrink-0 text-success" />
        <Copy
            v-else
            class="size-3.5 shrink-0 text-muted-foreground group-hover:text-foreground"
        />
    </button>
</template>
