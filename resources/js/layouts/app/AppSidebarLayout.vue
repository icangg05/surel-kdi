<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { AlertOctagon } from '@lucide/vue';
import { computed, defineAsyncComponent, watchEffect } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import { useCompose } from '@/composables/useCompose';
import { useShortcuts } from '@/composables/useShortcuts';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    /** Halaman kotak surat mengatur tinggi & toolbar sendiri. */
    bare?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    bare: false,
});

// Editor Tiptap cukup besar — dimuat hanya saat panel tulis pertama kali dibuka.
const ComposePanel = defineAsyncComponent(
    () => import('@/components/mail/ComposePanel.vue'),
);

const page = usePage();
const compose = useCompose();

// Jumlah belum dibaca & kuota di sidebar diperbarui tiap 30 detik tanpa memuat ulang halaman.
usePoll(30_000, { only: ['mail'] });

const smtpError = computed(() => page.props.mail?.smtp_error ?? null);
const unread = computed(() => page.props.mail?.counts?.inbox ?? 0);
watchEffect(() => {
    if (typeof document !== 'undefined') {
        document.title = document.title.replace(/^\(\d+\) /, '');

        if (unread.value > 0) {
            document.title = `(${unread.value}) ${document.title}`;
        }
    }
});

useShortcuts({
    c: () => {
        const id = page.props.mail?.current;

        if (id && !compose.state.open) {
            compose.start({ mailboxId: id });
        }
    },
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <div
                v-if="smtpError"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-danger/20 bg-danger-soft px-4 py-2.5 text-sm text-danger"
                role="alert"
            >
                <AlertOctagon class="size-4 shrink-0" />
                <span class="min-w-0 flex-1"
                    ><strong class="font-semibold">Pengiriman terhenti.</strong>
                    {{ smtpError.message }}</span
                >
                <Link
                    v-if="page.props.auth.user?.is_admin"
                    :href="admin.brevo.url()"
                    class="font-medium underline underline-offset-2"
                    >Periksa Integrasi Brevo</Link
                >
            </div>
            <AppSidebarHeader v-if="!bare" :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <ComposePanel v-if="compose.state.open" />
        <Toaster rich-colors position="bottom-left" />
    </AppShell>
</template>
