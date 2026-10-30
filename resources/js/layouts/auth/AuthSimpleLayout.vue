<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Inbox, Megaphone, ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name;

const points = [
    {
        icon: Inbox,
        text: 'Kotak masuk berutas untuk balasan dari OPD dan instansi',
    },
    {
        icon: Megaphone,
        text: 'Siaran personal ke ratusan penerima, otomatis mengikuti kuota',
    },
    { icon: ShieldCheck, text: 'Akun dibuat admin, log aktivitas tercatat' },
];
</script>

<template>
    <div
        class="grid min-h-svh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]"
    >
        <!-- Panel merek: pulau biru malam dengan tanda bercap emas. -->
        <aside
            class="surface-navy relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12"
        >
            <!-- Motif lipatan amplop dari logo, sama dengan sampul Design System. -->
            <svg
                class="pointer-events-none absolute -right-12 -bottom-10 size-[30rem] text-white/6"
                viewBox="0 0 400 400"
                fill="none"
                stroke="currentColor"
                stroke-width="16"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M40 40 200 150 360 40" />
                <path d="M40 120 200 230 360 120" />
                <path d="M40 200 200 310 360 200" />
                <path d="M40 280 200 390 360 280" />
            </svg>
            <div class="relative flex items-center gap-3">
                <AppLogoIcon class="size-12 text-sidebar-foreground" />
                <div class="leading-tight">
                    <p class="text-lg font-semibold tracking-tight text-white">
                        {{ appName }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Dinas Komunikasi dan Informatika Kota Kendari
                    </p>
                </div>
            </div>
            <div class="relative max-w-md">
                <p
                    class="text-3xl leading-tight font-semibold tracking-tight text-balance text-white"
                >
                    Surat elektronik dinas, terkirim rapi dan terlacak.
                </p>
                <ul class="mt-8 space-y-4">
                    <li
                        v-for="p in points"
                        :key="p.text"
                        class="flex items-start gap-3 text-[15px]"
                    >
                        <component
                            :is="p.icon"
                            class="mt-0.5 size-5 shrink-0 text-gold"
                        />
                        {{ p.text }}
                    </li>
                </ul>
            </div>
            <p class="relative text-xs text-muted-foreground">
                Pengiriman melalui Brevo · Pemerintah Kota Kendari
            </p>
        </aside>

        <!-- Formulir -->
        <main class="flex flex-col items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <AppLogoIcon
                        class="size-10 text-sidebar dark:text-sidebar-foreground"
                    />
                    <div class="leading-tight">
                        <p class="font-semibold">{{ appName }}</p>
                        <p class="text-xs text-muted-foreground">
                            Dinas Kominfo Kota Kendari
                        </p>
                    </div>
                </div>
                <div class="mb-7 space-y-1.5">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
