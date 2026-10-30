<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Jam WITA seperti topbar portal Diskominfo: jadwal kirim dan reset kuota mengikuti waktu ini.
const now = ref(new Date());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => (now.value = new Date()), 30_000);
});
onBeforeUnmount(() => clearInterval(timer));

const date = computed(() =>
    now.value.toLocaleDateString('id-ID', {
        timeZone: 'Asia/Makassar',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);
const time = computed(() =>
    now.value.toLocaleTimeString('id-ID', {
        timeZone: 'Asia/Makassar',
        hour: '2-digit',
        minute: '2-digit',
    }),
);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b bg-background px-4 sm:px-6"
    >
        <SidebarTrigger class="-ml-1.5 text-muted-foreground" />
        <span class="h-5 w-px bg-border" aria-hidden="true" />
        <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
        <p
            class="tabular ml-auto hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex"
        >
            <Clock class="size-3.5" />
            {{ date }} ·
            <span class="font-medium text-foreground">{{ time }} WITA</span>
        </p>
    </header>
</template>
