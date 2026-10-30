<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const appName = usePage().props.name;
</script>

<template>
    <!-- Dibungkus div: tombol sidebar memaksa svg anak langsung menjadi 16px. -->
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center">
        <AppLogoIcon class="size-8 text-sidebar-foreground" />
    </div>
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span class="truncate text-[15px] font-semibold tracking-tight">{{
            appName
        }}</span>
        <span class="truncate text-xs text-sidebar-foreground/70"
            >Dinas Kominfo Kota Kendari</span
        >
    </div>
</template>
