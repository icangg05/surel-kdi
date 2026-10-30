<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { number } from '@/lib/format';

defineProps<{
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    noun?: string;
}>();
</script>

<template>
    <div
        v-if="meta.total > 0"
        class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
    >
        <span class="tabular"
            >{{ number(meta.from ?? 0) }}–{{ number(meta.to ?? 0) }} dari
            {{ number(meta.total) }} {{ noun }}</span
        >
        <div class="flex items-center gap-1">
            <component
                :is="meta.prev_page_url ? Link : 'span'"
                :href="meta.prev_page_url ?? undefined"
                preserve-scroll
                :class="[
                    'inline-flex size-8 items-center justify-center rounded-md border',
                    meta.prev_page_url ? 'hover:bg-accent' : 'opacity-40',
                ]"
                aria-label="Halaman sebelumnya"
            >
                <ChevronLeft class="size-4" />
            </component>
            <component
                :is="meta.next_page_url ? Link : 'span'"
                :href="meta.next_page_url ?? undefined"
                preserve-scroll
                :class="[
                    'inline-flex size-8 items-center justify-center rounded-md border',
                    meta.next_page_url ? 'hover:bg-accent' : 'opacity-40',
                ]"
                aria-label="Halaman berikutnya"
            >
                <ChevronRight class="size-4" />
            </component>
        </div>
    </div>
</template>
