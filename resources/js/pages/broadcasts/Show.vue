<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    CalendarX,
    MessageSquareReply,
    Pause,
    Play,
    Search,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { dateOnly, longDate, number, shortDate } from '@/lib/format';
import broadcastRoutes from '@/routes/broadcasts';
import mailRoutes from '@/routes/mail';
import type { AttachmentItem, Paginated } from '@/types';

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
type Recipient = {
    id: number;
    email: string;
    name: string | null;
    organization: string | null;
    error: string | null;
    thread_id: number | null;
    status: string;
    label: string;
    sent_at: string | null;
    opened_at: string | null;
    replied_at: string | null;
};

const props = defineProps<{
    broadcast: {
        id: number;
        subject: string;
        body_html: string;
        total: number;
        mailbox_id: number;
        status: string;
        status_label: string;
        mailbox: string;
        author: string | null;
        scheduled_at: string | null;
        started_at: string | null;
        completed_at: string | null;
        attachments: AttachmentItem[];
    };
    stats: Stats;
    forecast: { date: string; count: number }[];
    recipients: Paginated<Recipient>;
    filters: { status: string | null; q: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Siaran', href: broadcastRoutes.index() },
            { title: 'Detail', href: '#' },
        ],
    },
});

const running = computed(() =>
    ['sending', 'waiting_quota', 'scheduled'].includes(props.broadcast.status),
);
const { start, stop } = usePoll(
    10_000,
    { only: ['broadcast', 'stats', 'recipients', 'forecast', 'mail'] },
    { autoStart: false },
);
onMounted(() => running.value && start());
watch(running, (r) => (r ? start() : stop()));

const pct = (n: number) =>
    props.stats.total ? (n / props.stats.total) * 100 : 0;
const done = computed(() => props.stats.total - props.stats.pending);

const tiles = computed(() => [
    {
        label: 'Penerima',
        value: props.stats.total,
        hint: props.stats.skipped ? `${props.stats.skipped} dilewati` : null,
    },
    {
        label: 'Terkirim',
        value: props.stats.sent,
        hint: `${Math.round(pct(props.stats.sent))}%`,
    },
    { label: 'Diterima', value: props.stats.delivered, hint: null },
    {
        label: 'Dibuka',
        value: props.stats.opened,
        hint: props.stats.sent
            ? `${Math.round((props.stats.opened / props.stats.sent) * 100)}% dari terkirim`
            : null,
    },
    { label: 'Balasan', value: props.stats.replied, hint: null },
    {
        label: 'Gagal',
        value: props.stats.failed,
        hint: null,
        danger: props.stats.failed > 0,
    },
]);

const TABS = [
    { key: null, label: 'Semua' },
    { key: 'pending', label: 'Menunggu' },
    { key: 'opened', label: 'Dibuka' },
    { key: 'replied', label: 'Membalas' },
    { key: 'failed', label: 'Gagal' },
];

const q = ref(props.filters.q);
const filter = (params: Record<string, unknown>) =>
    router.get(
        broadcastRoutes.show.url(props.broadcast.id),
        { ...props.filters, ...params, page: undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['recipients', 'filters'],
        },
    );
watch(
    q,
    useDebounceFn(() => filter({ q: q.value || undefined }), 300),
);

const act = (route: { url: (id: number) => string }) =>
    router.post(route.url(props.broadcast.id), {}, { preserveScroll: true });
const showBody = ref(false);
</script>

<template>
    <Head :title="broadcast.subject" />

    <div class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <StatusBadge
                        :status="broadcast.status"
                        :label="broadcast.status_label"
                    />
                </div>
                <h1
                    class="mt-2 text-xl font-semibold tracking-tight text-balance"
                >
                    {{ broadcast.subject }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Dari
                    <span class="font-mono text-xs">{{
                        broadcast.mailbox
                    }}</span>
                    <template v-if="broadcast.author">
                        · disusun {{ broadcast.author }}</template
                    >
                    <template
                        v-if="
                            broadcast.scheduled_at &&
                            broadcast.status === 'scheduled'
                        "
                    >
                        · dijadwalkan
                        {{ longDate(broadcast.scheduled_at) }}</template
                    >
                    <template v-else-if="broadcast.started_at">
                        · mulai {{ longDate(broadcast.started_at) }}</template
                    >
                    <template v-if="broadcast.completed_at">
                        · selesai
                        {{ shortDate(broadcast.completed_at) }}</template
                    >
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button
                    v-if="broadcast.status === 'scheduled'"
                    variant="outline"
                    @click="act(broadcastRoutes.unschedule)"
                    ><CalendarX /> Batalkan jadwal</Button
                >
                <Button
                    v-if="
                        ['sending', 'waiting_quota'].includes(broadcast.status)
                    "
                    variant="outline"
                    @click="act(broadcastRoutes.pause)"
                    ><Pause /> Jeda</Button
                >
                <Button
                    v-if="broadcast.status === 'paused'"
                    @click="act(broadcastRoutes.resume)"
                    ><Play /> Lanjutkan</Button
                >
                <ConfirmDialog
                    v-if="
                        [
                            'sending',
                            'waiting_quota',
                            'paused',
                            'scheduled',
                        ].includes(broadcast.status)
                    "
                    title="Batalkan siaran?"
                    :description="`${number(stats.pending)} penerima yang belum dikirimi tidak akan menerima email ini. Email yang sudah terkirim tidak bisa ditarik.`"
                    confirm-label="Batalkan siaran"
                    destructive
                    @confirm="act(broadcastRoutes.cancel)"
                >
                    <Button
                        variant="ghost"
                        class="text-destructive hover:text-destructive"
                        ><XCircle /> Batalkan</Button
                    >
                </ConfirmDialog>
            </div>
        </div>

        <div class="rounded-xl border bg-card p-5">
            <div class="flex items-baseline justify-between text-sm">
                <span class="font-medium">{{
                    broadcast.status === 'completed'
                        ? 'Selesai'
                        : 'Progres pengiriman'
                }}</span>
                <span class="tabular text-muted-foreground"
                    >{{ number(done) }} / {{ number(stats.total) }}</span
                >
            </div>
            <div
                class="mt-2 flex h-2.5 gap-[2px] overflow-hidden rounded-sm bg-muted"
                role="progressbar"
                :aria-valuenow="done"
                :aria-valuemax="stats.total"
            >
                <div
                    class="bg-primary"
                    :style="{ width: `${pct(stats.sent)}%` }"
                />
                <div
                    class="bg-danger"
                    :style="{ width: `${pct(stats.failed)}%` }"
                />
                <div
                    class="bg-muted-foreground/30"
                    :style="{ width: `${pct(stats.skipped)}%` }"
                />
            </div>
            <div
                class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-1.5"
                    ><span class="size-2 rounded-sm bg-primary" />Terkirim</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="size-2 rounded-sm bg-danger" />Gagal</span
                >
                <span class="flex items-center gap-1.5"
                    ><span
                        class="size-2 rounded-sm bg-muted-foreground/30"
                    />Dilewati/dibatalkan</span
                >
            </div>
            <div
                v-if="
                    broadcast.status === 'waiting_quota' || forecast.length > 1
                "
                class="mt-4 rounded-lg bg-warning-soft p-3 text-sm text-warning"
            >
                Kuota harian Brevo terpakai. Sisa
                {{ number(stats.pending) }} email dikirim otomatis:
                <span v-for="(d, i) in forecast" :key="d.date"
                    ><template v-if="i">, </template>{{ dateOnly(d.date) }} ({{
                        number(d.count)
                    }})</span
                >.
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div
                v-for="t in tiles"
                :key="t.label"
                class="rounded-xl border bg-card p-3.5"
            >
                <p class="text-xs text-muted-foreground">{{ t.label }}</p>
                <p
                    :class="[
                        'tabular mt-1 text-2xl font-semibold tracking-tight',
                        t.danger ? 'text-danger' : '',
                    ]"
                >
                    {{ number(t.value) }}
                </p>
                <p v-if="t.hint" class="mt-0.5 text-xs text-muted-foreground">
                    {{ t.hint }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border bg-card">
            <div class="flex flex-wrap items-center gap-2 border-b px-3 py-2">
                <div class="flex gap-1 overflow-x-auto" role="tablist">
                    <button
                        v-for="t in TABS"
                        :key="String(t.key)"
                        type="button"
                        role="tab"
                        :aria-selected="filters.status === t.key"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm whitespace-nowrap',
                            (filters.status ?? null) === t.key
                                ? 'bg-accent font-medium'
                                : 'text-muted-foreground hover:bg-accent/60',
                        ]"
                        @click="filter({ status: t.key ?? undefined })"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <label class="relative ml-auto w-full sm:w-64">
                    <span class="sr-only">Cari penerima</span>
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="q"
                        type="search"
                        placeholder="Cari penerima"
                        class="h-9 w-full rounded-md border bg-transparent pr-3 pl-9 text-sm outline-none focus:border-ring"
                    />
                </label>
            </div>
            <div v-if="recipients.data.length" class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead
                        class="bg-muted text-left text-xs text-secondary-foreground"
                    >
                        <tr class="border-b">
                            <th class="py-2.5 pl-4 font-medium">Penerima</th>
                            <th class="py-2.5 font-medium">Status</th>
                            <th class="py-2.5 font-medium">Dikirim</th>
                            <th class="py-2.5 font-medium">Dibuka</th>
                            <th class="py-2.5 pr-4 font-medium">Balasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in recipients.data"
                            :key="r.id"
                            class="border-b last:border-0"
                        >
                            <td class="max-w-72 py-2.5 pl-4">
                                <span class="block truncate font-medium">{{
                                    r.name || r.email
                                }}</span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    ><span class="font-mono">{{ r.email }}</span
                                    ><template v-if="r.organization">
                                        · {{ r.organization }}</template
                                    ></span
                                >
                            </td>
                            <td class="py-2.5">
                                <StatusBadge
                                    :status="r.status"
                                    :label="r.label"
                                />
                                <span
                                    v-if="r.error"
                                    class="mt-0.5 block max-w-56 truncate text-xs text-danger"
                                    :title="r.error"
                                    >{{ r.error }}</span
                                >
                            </td>
                            <td
                                class="tabular py-2.5 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ shortDate(r.sent_at) || '—' }}
                            </td>
                            <td
                                class="tabular py-2.5 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ shortDate(r.opened_at) || '—' }}
                            </td>
                            <td class="py-2.5 pr-4">
                                <Link
                                    v-if="r.thread_id"
                                    :href="
                                        mailRoutes.index.url({
                                            mailbox: broadcast.mailbox_id,
                                            folder: 'inbox',
                                            thread: r.thread_id,
                                        })
                                    "
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                >
                                    <MessageSquareReply class="size-3.5" /> Buka
                                    balasan
                                </Link>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="Search"
                title="Tidak ada penerima pada filter ini"
            />
            <div v-if="recipients.last_page > 1" class="border-t px-4 py-2.5">
                <Pagination :meta="recipients" noun="penerima" />
            </div>
        </div>

        <div class="rounded-xl border bg-card">
            <button
                type="button"
                class="flex w-full items-center justify-between px-5 py-3.5 text-left text-sm font-medium"
                :aria-expanded="showBody"
                @click="showBody = !showBody"
            >
                Isi siaran
                <span class="text-xs font-normal text-muted-foreground">{{
                    showBody ? 'Sembunyikan' : 'Tampilkan'
                }}</span>
            </button>
            <div v-if="showBody" class="border-t px-5 py-4">
                <div class="mail-prose" v-html="broadcast.body_html" />
                <p
                    v-if="broadcast.attachments.length"
                    class="mt-3 text-xs text-muted-foreground"
                >
                    Lampiran:
                    {{
                        broadcast.attachments.map((a) => a.filename).join(', ')
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
