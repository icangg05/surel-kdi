<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    KeyRound,
    RefreshCw,
    RotateCcw,
    Send,
    Webhook,
    XCircle,
} from '@lucide/vue';
import CopyText from '@/components/CopyText.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { number, relative, shortDate } from '@/lib/format';
import admin from '@/routes/admin';

const props = defineProps<{
    smtp: {
        host: string;
        port: number;
        username: string;
        configured: boolean;
        mailer: string;
        ok: boolean;
        error: string | null;
        checked_at: string;
    };
    api: {
        configured: boolean;
        account: {
            email: string | null;
            company: string | null;
            plan: {
                type: string;
                credits: number | null;
                credits_type: string | null;
            }[];
            synced_at: string;
        } | null;
        error: string | null;
    };
    webhooks:
        | {
              id: number;
              type: string;
              url: string;
              description: string;
              events: string[];
              domain: string | null;
              ours: boolean;
          }[]
        | null;
    expected: {
        type: string;
        description: string;
        domain?: string;
        url: string;
        error: { message: string; at: string } | null;
    }[];
    publicUrl: boolean;
    inbound: {
        id: number;
        from: string | null;
        subject: string | null;
        to: string;
        processed_at: string | null;
        error: string | null;
        created_at: string;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            { title: 'Integrasi Brevo', href: admin.brevo() },
        ],
    },
});

const page = usePage();
const test = useForm({ email: String(page.props.auth.user?.email ?? '') });
const registered = (e: { type: string; domain?: string }) =>
    props.webhooks?.some(
        (w) =>
            w.ours &&
            w.type === e.type &&
            (e.type !== 'inbound' || w.domain === e.domain),
    ) ?? false;
</script>

<template>
    <Head title="Integrasi Brevo" />
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <PageHeader
            title="Integrasi Brevo"
            description="Brevo menjadi server email: SMTP untuk mengirim, Inbound Parsing untuk menerima, webhook untuk status pengiriman."
        />

        <div class="grid gap-4 md:grid-cols-2">
            <!-- SMTP -->
            <section class="rounded-xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <Send class="size-5 text-primary" />
                    <h2 class="flex-1 font-semibold">Kirim (SMTP)</h2>
                    <StatusBadge
                        :status="smtp.ok ? 'delivered' : 'failed'"
                        :label="
                            smtp.ok
                                ? 'Login berhasil'
                                : smtp.configured
                                  ? 'Ditolak'
                                  : 'Belum diatur'
                        "
                    />
                </div>
                <dl class="mt-4 grid grid-cols-[6rem_1fr] gap-y-1.5 text-sm">
                    <dt class="text-muted-foreground">Server</dt>
                    <dd class="font-mono text-xs leading-5">
                        {{ smtp.host }}:{{ smtp.port }}
                    </dd>
                    <dt class="text-muted-foreground">Login</dt>
                    <dd class="font-mono text-xs leading-5">
                        {{ smtp.username || '—' }}
                    </dd>
                    <dt class="text-muted-foreground">Mailer</dt>
                    <dd class="font-mono text-xs leading-5">
                        {{ smtp.mailer }}
                    </dd>
                </dl>
                <div
                    v-if="!smtp.ok && smtp.error"
                    class="mt-4 rounded-lg bg-danger-soft p-3 text-sm text-danger"
                >
                    <p class="font-medium">{{ smtp.error }}</p>
                    <p
                        v-if="smtp.error.includes('IP')"
                        class="mt-1 text-xs opacity-90"
                    >
                        Brevo › klik nama akun › Security › Authorized IPs ›
                        tambahkan IP publik server ini (atau matikan pemblokiran
                        IP). Brevo juga biasanya mengirim email berisi tautan
                        "authorize" saat ada IP baru yang ditolak.
                    </p>
                </div>
                <p
                    class="mt-3 flex items-center gap-2 text-xs text-muted-foreground"
                >
                    Dicek {{ relative(smtp.checked_at) }} tanpa mengirim email.
                    <button
                        type="button"
                        class="font-medium text-foreground underline-offset-2 hover:underline"
                        @click="
                            router.get(
                                admin.brevo.url(),
                                { recheck: 1 },
                                { preserveScroll: true },
                            )
                        "
                    >
                        Cek ulang
                    </button>
                </p>
                <form
                    class="mt-4 border-t pt-4"
                    @submit.prevent="
                        test.post(admin.brevo.test.url(), {
                            preserveScroll: true,
                        })
                    "
                >
                    <p class="text-sm font-medium">Kirim email uji</p>
                    <div class="mt-2 flex gap-2">
                        <Input v-model="test.email" type="email" required />
                        <Button variant="outline" :disabled="test.processing"
                            >Kirim</Button
                        >
                    </div>
                    <InputError :message="test.errors.email" class="mt-1" />
                </form>
            </section>

            <!-- API -->
            <section class="rounded-xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <KeyRound class="size-5 text-primary" />
                    <h2 class="flex-1 font-semibold">API v3</h2>
                    <StatusBadge
                        :status="
                            api.configured
                                ? api.error
                                    ? 'failed'
                                    : 'delivered'
                                : 'waiting_quota'
                        "
                        :label="
                            api.configured
                                ? api.error
                                    ? 'Error'
                                    : 'Terhubung'
                                : 'API key belum diisi'
                        "
                    />
                </div>
                <template v-if="api.configured">
                    <dl
                        v-if="api.account"
                        class="mt-4 grid grid-cols-[6rem_1fr] gap-y-1.5 text-sm"
                    >
                        <dt class="text-muted-foreground">Akun</dt>
                        <dd>{{ api.account.company || api.account.email }}</dd>
                        <template v-for="p in api.account.plan" :key="p.type"
                            ><dt class="text-muted-foreground capitalize">
                                {{ p.type }}
                            </dt>
                            <dd class="tabular">
                                {{
                                    p.credits !== null
                                        ? `${number(p.credits)} kredit`
                                        : '—'
                                }}
                            </dd></template
                        >
                    </dl>
                    <p v-if="api.error" class="mt-3 text-sm text-danger">
                        {{ api.error }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-4"
                        @click="
                            router.post(
                                admin.brevo.sync.url(),
                                {},
                                { preserveScroll: true },
                            )
                        "
                        ><RefreshCw /> Perbarui info akun</Button
                    >
                    <p
                        v-if="api.account"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Disinkron {{ relative(api.account.synced_at) }}
                    </p>
                </template>
                <div
                    v-else
                    class="mt-4 space-y-2 text-sm text-muted-foreground"
                >
                    <p>
                        Diperlukan untuk
                        <strong class="font-medium text-foreground"
                            >menerima email</strong
                        >, mengunduh lampiran, dan mendaftarkan webhook.
                        Kredensial di trade-history hanya kunci SMTP.
                    </p>
                    <ol class="list-decimal space-y-1 pl-5">
                        <li>
                            Brevo › SMTP & API › tab <em>API Keys</em> ›
                            Generate (diawali
                            <span class="font-mono text-xs">xkeysib-</span>).
                        </li>
                        <li>
                            Isi
                            <span class="font-mono text-xs"
                                >BREVO_API_KEY=</span
                            >
                            di <span class="font-mono text-xs">.env</span>, lalu
                            muat ulang halaman ini.
                        </li>
                    </ol>
                </div>
            </section>
        </div>

        <!-- Webhook -->
        <section class="rounded-xl border bg-card">
            <header
                class="flex flex-wrap items-center gap-3 border-b px-5 py-3.5"
            >
                <Webhook class="size-5 text-primary" />
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Webhook</h2>
                    <p class="text-xs text-muted-foreground">
                        Brevo memanggil URL ini saat email masuk dan saat status
                        kirim berubah.
                    </p>
                </div>
                <Button
                    :disabled="!api.configured"
                    @click="
                        router.post(
                            admin.brevo.webhooks.url(),
                            {},
                            { preserveScroll: true },
                        )
                    "
                    >Daftarkan webhook</Button
                >
            </header>
            <div
                v-if="!publicUrl"
                class="flex gap-2 border-b bg-warning-soft px-5 py-3 text-sm text-warning"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <p>
                    APP_URL belum HTTPS publik, jadi Brevo tidak bisa menjangkau
                    aplikasi. Saat pengembangan jalankan
                    <span class="font-mono text-xs"
                        >docker compose --profile tunnel up tunnel</span
                    >, salin URL
                    <span class="font-mono text-xs">*.trycloudflare.com</span>
                    ke APP_URL, lalu daftarkan ulang.
                </p>
            </div>
            <ul class="divide-y">
                <li
                    v-for="e in expected"
                    :key="e.url + (e.domain ?? '')"
                    class="flex flex-wrap items-center gap-3 px-5 py-3"
                >
                    <CheckCircle2
                        v-if="registered(e)"
                        class="size-4 text-success"
                        aria-label="Terdaftar"
                    />
                    <XCircle
                        v-else
                        class="size-4 text-muted-foreground"
                        aria-label="Belum terdaftar"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{
                                e.type === 'inbound'
                                    ? `Email masuk — ${e.domain}`
                                    : 'Status pengiriman (transaksional)'
                            }}
                        </p>
                        <CopyText :value="e.url" class="mt-1" />
                        <div
                            v-if="e.error && !registered(e)"
                            class="mt-2 rounded-md bg-danger-soft px-3 py-2 text-xs text-danger"
                        >
                            <p>
                                <span class="font-semibold">Ditolak Brevo</span>
                                ({{ relative(e.error.at) }}):
                                <span class="font-mono">{{
                                    e.error.message
                                }}</span>
                            </p>
                            <p
                                v-if="e.type === 'inbound'"
                                class="mt-1 text-danger/80"
                            >
                                <template
                                    v-if="
                                        /domain is not found/i.test(
                                            e.error.message,
                                        )
                                    "
                                >
                                    Tambahkan {{ e.domain }} sebagai domain
                                    tersendiri di Brevo › Senders, domains, IPs
                                    › Domains, pasang rekaman DNS yang diberikan
                                    Brevo, klik Verify, lalu daftarkan ulang.
                                </template>
                                <template v-else>
                                    Pastikan MX {{ e.domain }} sudah mengarah ke
                                    inbound1/inbound2.sendinblue.com dan Inbound
                                    Parsing tersedia untuk akun ini.
                                </template>
                            </p>
                        </div>
                    </div>
                    <span class="text-xs text-muted-foreground">{{
                        registered(e)
                            ? 'Terdaftar di Brevo'
                            : webhooks === null
                              ? 'Tidak bisa dicek tanpa API key'
                              : 'Belum terdaftar'
                    }}</span>
                </li>
            </ul>
        </section>

        <!-- Log inbound -->
        <section class="rounded-xl border bg-card">
            <header class="border-b px-5 py-3.5">
                <h2 class="font-semibold">Email masuk terakhir</h2>
                <p class="text-xs text-muted-foreground">
                    Payload mentah dari Brevo. Email ke alamat yang tidak cocok
                    dengan mailbox mana pun tercatat di sini.
                </p>
            </header>
            <div v-if="inbound.length" class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <tbody>
                        <tr
                            v-for="p in inbound"
                            :key="p.id"
                            class="border-b last:border-0"
                        >
                            <td
                                class="tabular py-2.5 pl-5 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{ shortDate(p.created_at) }}
                            </td>
                            <td class="max-w-72 py-2.5 pl-4">
                                <span class="block truncate">{{
                                    p.subject || '(tanpa subjek)'
                                }}</span>
                                <span
                                    class="block truncate font-mono text-xs text-muted-foreground"
                                    >{{ p.from }} → {{ p.to }}</span
                                >
                            </td>
                            <td class="py-2.5 pl-4">
                                <StatusBadge
                                    v-if="p.error"
                                    status="failed"
                                    :label="
                                        p.error.length > 40
                                            ? p.error.slice(0, 40) + '…'
                                            : p.error
                                    "
                                    :title="p.error"
                                />
                                <StatusBadge
                                    v-else-if="p.processed_at"
                                    status="delivered"
                                    label="Masuk mailbox"
                                />
                                <StatusBadge
                                    v-else
                                    status="queued"
                                    label="Diproses"
                                />
                            </td>
                            <td class="py-2.5 pr-5 text-right">
                                <Button
                                    v-if="p.error"
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        router.post(
                                            admin.brevo.reprocess.url(p.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    "
                                    ><RotateCcw /> Proses ulang</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-else
                class="px-5 py-8 text-center text-sm text-muted-foreground"
            >
                Belum ada email masuk. Uji tanpa DNS:
                <span class="font-mono text-xs"
                    >php artisan brevo:simulate-inbound</span
                >
            </p>
        </section>
    </div>
</template>
