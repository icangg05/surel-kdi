<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ScrollText, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { longDate, shortDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    causer: string;
    action: string;
    subject: string | null;
    changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    } | null;
    properties: Record<string, unknown> | null;
    created_at: string;
};
const props = defineProps<{
    activities: Paginated<Row>;
    filters: { q: string | null };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            { title: 'Log Aktivitas', href: admin.activity() },
        ],
    },
});

const q = ref(props.filters.q ?? '');
watch(
    q,
    useDebounceFn(
        () =>
            router.get(
                admin.activity.url(),
                { q: q.value || undefined },
                { preserveState: true, replace: true },
            ),
        300,
    ),
);

const fmt = (v: unknown) =>
    v === null || v === undefined || v === ''
        ? '—'
        : typeof v === 'object'
          ? JSON.stringify(v)
          : String(v);
const changedKeys = (r: Row) =>
    Object.keys(r.changes?.attributes ?? {}).filter(
        (k) => !['updated_at', 'created_at'].includes(k),
    );
</script>

<template>
    <Head title="Log Aktivitas" />
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-6">
        <PageHeader
            title="Log Aktivitas"
            description="Jejak audit: siapa mengubah kontak, mailbox, pengguna, siaran, dan kapan — termasuk impor & ekspor data kontak."
        />
        <div class="rounded-xl border bg-card">
            <div class="border-b px-3 py-2">
                <label class="relative block">
                    <span class="sr-only">Cari log</span>
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="q"
                        type="search"
                        placeholder="Cari aktivitas"
                        class="h-9 w-full bg-transparent pr-3 pl-9 text-sm outline-none"
                    />
                </label>
            </div>
            <ol v-if="activities.data.length" class="divide-y">
                <li v-for="a in activities.data" :key="a.id" class="px-5 py-3">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="font-medium">{{ a.causer }}</span>
                        <span class="text-sm">{{ a.action }}</span>
                        <span
                            v-if="a.subject"
                            class="font-mono text-xs text-muted-foreground"
                            >{{ a.subject }}</span
                        >
                        <time
                            class="tabular ml-auto text-xs text-muted-foreground"
                            :datetime="a.created_at"
                            :title="longDate(a.created_at)"
                            >{{ shortDate(a.created_at) }}</time
                        >
                    </div>
                    <dl
                        v-if="changedKeys(a).length"
                        class="mt-1.5 grid gap-0.5 text-xs"
                    >
                        <div
                            v-for="k in changedKeys(a).slice(0, 6)"
                            :key="k"
                            class="flex gap-2"
                        >
                            <dt
                                class="w-32 shrink-0 font-mono text-muted-foreground"
                            >
                                {{ k }}
                            </dt>
                            <dd class="min-w-0 truncate">
                                <span
                                    v-if="a.changes?.old"
                                    class="text-muted-foreground line-through"
                                    >{{ fmt(a.changes.old[k]) }}</span
                                >
                                {{ fmt(a.changes?.attributes?.[k]) }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-else-if="
                            a.properties && Object.keys(a.properties).length
                        "
                        class="mt-1 truncate font-mono text-xs text-muted-foreground"
                    >
                        {{ JSON.stringify(a.properties) }}
                    </p>
                </li>
            </ol>
            <EmptyState v-else :icon="ScrollText" title="Belum ada aktivitas" />
            <div v-if="activities.last_page > 1" class="border-t px-4 py-2.5">
                <Pagination :meta="activities" noun="aktivitas" />
            </div>
        </div>
    </div>
</template>
