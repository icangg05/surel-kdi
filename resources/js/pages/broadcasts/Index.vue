<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Megaphone, Plus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { number, shortDate } from '@/lib/format';
import broadcastRoutes from '@/routes/broadcasts';
import type { Paginated } from '@/types';

type Stats = {
    total: number;
    pending: number;
    sent: number;
    delivered: number;
    opened: number;
    failed: number;
    skipped: number;
    replied: number;
};
type Row = {
    id: number;
    subject: string;
    total: number;
    status: string;
    status_label: string;
    mailbox: string;
    author: string | null;
    stats: Stats | null;
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
};

defineProps<{ broadcasts: Paginated<Row> }>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Siaran', href: broadcastRoutes.index() }],
    },
});

const pct = (part: number, total: number) =>
    total ? Math.round((part / total) * 100) : 0;
const href = (b: Row) =>
    b.status === 'draft' || b.status === 'scheduled'
        ? broadcastRoutes.edit.url(b.id)
        : broadcastRoutes.show.url(b.id);
</script>

<template>
    <Head title="Siaran" />
    <div class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
        <PageHeader
            title="Siaran"
            description="Satu isi, dikirim personal ke banyak kontak — tiap penerima mendapat email sendiri. Pengiriman otomatis dibagi mengikuti kuota harian Brevo."
        >
            <Button @click="router.post(broadcastRoutes.store.url())"
                ><Plus /> Siaran baru</Button
            >
        </PageHeader>

        <div class="rounded-xl border bg-card">
            <div v-if="broadcasts.data.length" class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead
                        class="bg-muted text-left text-xs text-secondary-foreground"
                    >
                        <tr class="border-b">
                            <th class="py-2.5 pl-4 font-medium">Subjek</th>
                            <th class="py-2.5 font-medium">Status</th>
                            <th class="w-56 py-2.5 font-medium">Progres</th>
                            <th class="py-2.5 text-right font-medium">
                                Dibuka
                            </th>
                            <th class="py-2.5 text-right font-medium">
                                Balasan
                            </th>
                            <th class="py-2.5 pr-4 text-right font-medium">
                                Tanggal
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="b in broadcasts.data"
                            :key="b.id"
                            class="border-b last:border-0 hover:bg-muted/40"
                        >
                            <td class="max-w-80 py-3 pl-4">
                                <Link
                                    :href="href(b)"
                                    class="block truncate font-medium hover:underline"
                                    >{{ b.subject || '(tanpa subjek)' }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ b.mailbox
                                    }}<template v-if="b.author">
                                        · {{ b.author }}</template
                                    ></span
                                >
                            </td>
                            <td class="py-3">
                                <StatusBadge
                                    :status="b.status"
                                    :label="b.status_label"
                                />
                            </td>
                            <td class="py-3 pr-6">
                                <template v-if="b.stats">
                                    <div
                                        class="flex h-1.5 overflow-hidden rounded-sm bg-muted"
                                        :title="`${b.stats.sent} terkirim dari ${b.total}`"
                                    >
                                        <div
                                            class="bg-primary"
                                            :style="{
                                                width: `${pct(b.stats.sent, b.total)}%`,
                                            }"
                                        />
                                        <div
                                            class="bg-danger"
                                            :style="{
                                                width: `${pct(b.stats.failed, b.total)}%`,
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="tabular mt-1 block text-xs text-muted-foreground"
                                        >{{ number(b.stats.sent) }} /
                                        {{ number(b.total) }} terkirim<template
                                            v-if="b.stats.failed"
                                        >
                                            ·
                                            <span class="text-danger"
                                                >{{
                                                    b.stats.failed
                                                }}
                                                gagal</span
                                            ></template
                                        ></span
                                    >
                                </template>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="tabular py-3 text-right">
                                {{
                                    b.stats
                                        ? `${pct(b.stats.opened, b.stats.sent)}%`
                                        : '—'
                                }}
                            </td>
                            <td class="tabular py-3 text-right">
                                {{ b.stats ? number(b.stats.replied) : '—' }}
                            </td>
                            <td
                                class="py-3 pr-4 text-right text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    shortDate(
                                        b.completed_at ??
                                            b.started_at ??
                                            b.scheduled_at ??
                                            b.created_at,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="Megaphone"
                title="Belum ada siaran"
                description="Kirim surat edaran atau undangan ke grup kontak. Balasan masuk ke Kotak Masuk dan tertaut ke siarannya."
            >
                <Button @click="router.post(broadcastRoutes.store.url())"
                    ><Plus /> Buat siaran pertama</Button
                >
            </EmptyState>
            <div v-if="broadcasts.last_page > 1" class="border-t px-4 py-2.5">
                <Pagination :meta="broadcasts" noun="siaran" />
            </div>
        </div>
    </div>
</template>
