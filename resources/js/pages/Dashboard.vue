<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookUser,
    Gauge,
    Inbox,
    Megaphone,
    PenSquare,
    PlugZap,
    Send,
} from '@lucide/vue';
import { computed } from 'vue';
import DailyBars from '@/components/DailyBars.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useCompose } from '@/composables/useCompose';
import { dateOnly, number, shortDate } from '@/lib/format';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import broadcastRoutes from '@/routes/broadcasts';
import mailRoutes from '@/routes/mail';
import type { ListRow } from '@/types';

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

const props = defineProps<{
    quota: {
        used: number;
        limit: number;
        remaining: number;
        resets_at: string;
        history: { date: string; used: number }[];
    };
    brevo: {
        email: string | null;
        company: string | null;
        plan: {
            type: string | null;
            credits: number | null;
            credits_type: string | null;
        }[];
        synced_at: string;
    } | null;
    series: { date: string; sent: number; opened: number; failed: number }[];
    totals: {
        unread: number;
        contacts: number;
        suppressed: number;
        sent_7d: number;
        opened_7d: number;
    };
    unread: (ListRow & { mailbox_id: number })[];
    broadcasts: {
        id: number;
        subject: string;
        total: number;
        status: string;
        status_label: string;
        scheduled_at: string | null;
        stats: Stats;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dasbor', href: dashboard() }] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const compose = useCompose();
const currentMailbox = computed(() => page.props.mail?.current);

const greeting = computed(() => {
    const h = new Date().getHours();

    return h < 11
        ? 'Selamat pagi'
        : h < 15
          ? 'Selamat siang'
          : h < 18
            ? 'Selamat sore'
            : 'Selamat malam';
});
const quotaPct = computed(() =>
    props.quota.limit
        ? Math.min(100, (props.quota.used / props.quota.limit) * 100)
        : 0,
);
const openRate = computed(() =>
    props.totals.sent_7d
        ? Math.round((props.totals.opened_7d / props.totals.sent_7d) * 100)
        : null,
);
const resetTime = computed(() =>
    new Date(props.quota.resets_at).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);
const progress = (s: Stats) =>
    s.total ? Math.round(((s.total - s.pending) / s.total) * 100) : 0;
const brevoCredits = computed(
    () => props.brevo?.plan.find((p) => p.credits !== null) ?? null,
);
</script>

<template>
    <Head title="Dasbor" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ dateOnly(new Date().toISOString()) }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ greeting }}, {{ user?.name?.split(' ')[0] }}
                </h1>
            </div>
            <div class="flex gap-2">
                <Button
                    v-if="currentMailbox"
                    variant="gold"
                    @click="compose.start({ mailboxId: currentMailbox })"
                    ><PenSquare /> Tulis email</Button
                >
                <Button
                    variant="outline"
                    @click="router.post(broadcastRoutes.store.url())"
                    ><Megaphone /> Siaran baru</Button
                >
            </div>
        </div>

        <!-- Kartu ringkas -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Kuota: angka harian terpenting, di permukaan biru malam dengan meter emas. -->
            <div class="surface-navy rounded-xl p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Sisa kuota hari ini
                    </p>
                    <span
                        class="flex size-7 items-center justify-center rounded-lg bg-sidebar-accent text-gold"
                        ><Gauge class="size-4"
                    /></span>
                </div>
                <p class="tabular mt-1 text-3xl font-semibold tracking-tight">
                    {{ number(quota.remaining) }}
                </p>
                <div
                    class="mt-3 h-1.5 overflow-hidden rounded-sm bg-sidebar-accent"
                >
                    <div
                        :class="[
                            'h-full rounded-sm',
                            quotaPct >= 100
                                ? 'bg-danger'
                                : quotaPct >= 80
                                  ? 'bg-warning'
                                  : 'bg-gold',
                        ]"
                        :style="{ width: `${quotaPct}%` }"
                    />
                </div>
                <p class="tabular mt-2 text-xs text-muted-foreground">
                    {{ number(quota.used) }} dari
                    {{ number(quota.limit) }} terpakai · reset {{ resetTime }}
                </p>
            </div>

            <Link
                v-if="currentMailbox"
                :href="
                    mailRoutes.index.url({
                        mailbox: currentMailbox,
                        folder: 'inbox',
                    })
                "
                class="group rounded-xl border bg-card p-4 transition-colors hover:border-primary/40"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Belum dibaca
                    </p>
                    <span
                        :class="[
                            'flex size-7 items-center justify-center rounded-lg',
                            totals.unread
                                ? 'bg-gold-soft text-gold-strong'
                                : 'bg-secondary text-primary',
                        ]"
                        ><Inbox class="size-4"
                    /></span>
                </div>
                <p class="tabular mt-1 text-3xl font-semibold tracking-tight">
                    {{ number(totals.unread) }}
                </p>
                <p class="mt-3 flex items-center gap-1 text-xs text-primary">
                    Buka Kotak Masuk
                    <ArrowRight
                        class="size-3 transition-transform group-hover:translate-x-0.5"
                    />
                </p>
            </Link>

            <div class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Terkirim 7 hari
                    </p>
                    <span
                        class="flex size-7 items-center justify-center rounded-lg bg-secondary text-primary"
                        ><Send class="size-4"
                    /></span>
                </div>
                <p class="tabular mt-1 text-3xl font-semibold tracking-tight">
                    {{ number(totals.sent_7d) }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    <template v-if="openRate !== null"
                        ><span class="tabular font-medium text-foreground"
                            >{{ openRate }}%</span
                        >
                        dibuka</template
                    >
                    <template v-else>Belum ada pengiriman</template>
                </p>
            </div>

            <Link
                href="/contacts"
                class="group rounded-xl border bg-card p-4 transition-colors hover:border-primary/40"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Kontak penerima
                    </p>
                    <span
                        class="flex size-7 items-center justify-center rounded-lg bg-secondary text-primary"
                        ><BookUser class="size-4"
                    /></span>
                </div>
                <p class="tabular mt-1 text-3xl font-semibold tracking-tight">
                    {{ number(totals.contacts) }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    <template v-if="totals.suppressed"
                        ><span class="tabular font-medium text-danger">{{
                            number(totals.suppressed)
                        }}</span>
                        tidak bisa dikirimi</template
                    >
                    <template v-else>Semua alamat aktif</template>
                </p>
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <div class="rounded-xl border bg-card p-5 lg:col-span-3">
                <DailyBars :series="series" />
            </div>

            <div class="flex flex-col rounded-xl border bg-card lg:col-span-2">
                <div
                    class="flex items-center justify-between border-b px-5 py-3.5"
                >
                    <h2 class="text-sm font-semibold">Siaran berjalan</h2>
                    <Link
                        :href="broadcastRoutes.index.url()"
                        class="text-xs font-medium text-primary hover:underline"
                        >Semua siaran</Link
                    >
                </div>
                <ul v-if="broadcasts.length" class="divide-y">
                    <li v-for="b in broadcasts" :key="b.id">
                        <Link
                            :href="broadcastRoutes.show.url(b.id)"
                            class="block px-5 py-3 hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium"
                                    >{{ b.subject }}</span
                                >
                                <StatusBadge
                                    :status="b.status"
                                    :label="b.status_label"
                                />
                            </div>
                            <div
                                class="mt-2 h-1.5 overflow-hidden rounded-sm bg-muted"
                            >
                                <div
                                    class="h-full rounded-sm bg-primary"
                                    :style="{ width: `${progress(b.stats)}%` }"
                                />
                            </div>
                            <p
                                class="tabular mt-1.5 text-xs text-muted-foreground"
                            >
                                {{ number(b.total - b.stats.pending) }} /
                                {{ number(b.total) }} diproses ·
                                {{ number(b.stats.opened) }} dibuka ·
                                {{ number(b.stats.replied) }} balasan
                            </p>
                        </Link>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    :icon="Megaphone"
                    title="Tidak ada siaran berjalan"
                    description="Siaran mengirim email personal ke banyak kontak sekaligus, mengikuti kuota harian."
                    class="flex-1 py-8"
                />
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <div class="rounded-xl border bg-card lg:col-span-3">
                <div
                    class="flex items-center justify-between border-b px-5 py-3.5"
                >
                    <h2 class="text-sm font-semibold">
                        Email masuk belum dibaca
                    </h2>
                    <Link
                        v-if="currentMailbox"
                        :href="
                            mailRoutes.index.url({
                                mailbox: currentMailbox,
                                folder: 'inbox',
                            })
                        "
                        class="text-xs font-medium text-primary hover:underline"
                        >Kotak Masuk</Link
                    >
                </div>
                <ul v-if="unread.length" class="divide-y">
                    <li v-for="t in unread" :key="t.key">
                        <Link
                            :href="
                                mailRoutes.index.url({
                                    mailbox: t.mailbox_id,
                                    folder: 'inbox',
                                    thread: t.id,
                                })
                            "
                            class="flex gap-3 px-5 py-3 hover:bg-muted/50"
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full bg-unread"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline gap-2">
                                    <span
                                        class="min-w-0 flex-1 truncate text-sm font-semibold"
                                        >{{ t.participants }}</span
                                    >
                                    <span
                                        class="tabular shrink-0 text-xs text-muted-foreground"
                                        >{{ shortDate(t.date) }}</span
                                    >
                                </span>
                                <span
                                    class="block truncate text-[13px] font-medium"
                                    >{{ t.subject }}</span
                                >
                                <span
                                    class="block truncate text-[13px] text-muted-foreground"
                                    >{{ t.snippet }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    :icon="Inbox"
                    title="Semua sudah dibaca"
                    description="Balasan baru dari penerima akan muncul di sini."
                    class="py-8"
                />
            </div>

            <div class="rounded-xl border bg-card p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold">Akun Brevo</h2>
                <template v-if="brevo">
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Akun</dt>
                            <dd class="truncate font-medium">
                                {{ brevo.company || brevo.email }}
                            </dd>
                        </div>
                        <div
                            v-for="p in brevo.plan"
                            :key="`${p.type}-${p.credits_type}`"
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-muted-foreground capitalize">
                                Paket {{ p.type }}
                            </dt>
                            <dd class="tabular font-medium">
                                {{
                                    p.credits !== null
                                        ? `${number(p.credits)} kredit`
                                        : '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="brevoCredits"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Diperbarui {{ shortDate(brevo.synced_at) }}. Kredit
                        Brevo adalah batas akun; kuota harian aplikasi diatur
                        admin.
                    </p>
                </template>
                <div
                    v-else
                    class="mt-3 space-y-3 text-sm text-muted-foreground"
                >
                    <p class="text-pretty">
                        Info paket & sisa kredit tampil di sini setelah
                        <span class="font-mono text-xs">BREVO_API_KEY</span>
                        diisi. Email keluar tetap berjalan lewat SMTP.
                    </p>
                    <Button
                        v-if="user?.is_admin"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="admin.brevo.url()"
                            ><PlugZap /> Buka Integrasi Brevo</Link
                        >
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
