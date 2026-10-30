<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Globe,
    Mailbox as MailboxIcon,
    Pencil,
    Plus,
    RefreshCw,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import CopyText from '@/components/CopyText.vue';
import InputError from '@/components/InputError.vue';
import RichEditor from '@/components/mail/RichEditor.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { relative } from '@/lib/format';
import admin from '@/routes/admin';

type Dns = {
    spf: boolean;
    dkim: boolean;
    dmarc: boolean;
    mx: boolean;
    brevo_code: boolean;
    records: {
        spf: string | null;
        dkim: string | null;
        dmarc: string | null;
        mx: string[];
    };
};
type DomainRow = {
    id: number;
    name: string;
    inbound_domain: string;
    is_active: boolean;
    dns_status: Dns | null;
    dns_checked_at: string | null;
    mailboxes_count: number;
};
type MailboxRow = {
    id: number;
    domain_id: number;
    local_part: string;
    display_name: string;
    signature_html: string | null;
    is_active: boolean;
    threads_count: number;
    address: string;
    inbound_address: string;
    users: { id: number; name: string }[];
    user_ids: number[];
};

const props = defineProps<{
    domains: DomainRow[];
    mailboxes: MailboxRow[];
    users: { id: number; name: string; email: string }[];
    inboundMx: string[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            { title: 'Domain & Mailbox', href: admin.mailboxes.index() },
        ],
    },
});

// Domain
const domainOpen = ref(false);
const editingDomain = ref<DomainRow | null>(null);
const domainForm = useForm({ name: '', inbound_domain: '', is_active: true });

function editDomain(d: DomainRow | null) {
    editingDomain.value = d;
    domainForm.clearErrors();
    domainForm.defaults({
        name: d?.name ?? '',
        inbound_domain: d?.inbound_domain ?? '',
        is_active: d?.is_active ?? true,
    });
    domainForm.reset();
    domainOpen.value = true;
}

function submitDomain() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (domainOpen.value = false),
    };
    if (editingDomain.value) {
        domainForm.put(admin.domains.update.url(editingDomain.value.id), opts);
    } else {
        domainForm.post(admin.domains.store.url(), opts);
    }
}

// Mailbox
const mailboxOpen = ref(false);
const editingMailbox = ref<MailboxRow | null>(null);
const mailboxForm = useForm({
    domain_id: 0,
    local_part: '',
    display_name: '',
    signature_html: '',
    is_active: true,
    user_ids: [] as number[],
});

function editMailbox(m: MailboxRow | null) {
    editingMailbox.value = m;
    mailboxForm.clearErrors();
    mailboxForm.defaults({
        domain_id: m?.domain_id ?? props.domains[0]?.id ?? 0,
        local_part: m?.local_part ?? '',
        display_name: m?.display_name ?? '',
        signature_html: m?.signature_html ?? '',
        is_active: m?.is_active ?? true,
        user_ids: m?.user_ids ?? [],
    });
    mailboxForm.reset();
    mailboxOpen.value = true;
}

function submitMailbox() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (mailboxOpen.value = false),
    };
    if (editingMailbox.value) {
        mailboxForm.put(
            admin.mailboxes.update.url(editingMailbox.value.id),
            opts,
        );
    } else {
        mailboxForm.post(admin.mailboxes.store.url(), opts);
    }
}

function toggleUser(id: number) {
    mailboxForm.user_ids = mailboxForm.user_ids.includes(id)
        ? mailboxForm.user_ids.filter((x) => x !== id)
        : [...mailboxForm.user_ids, id];
}

const domainOf = (id: number) => props.domains.find((d) => d.id === id);
const checks = (d: DomainRow) =>
    [
        {
            key: 'spf',
            label: 'SPF',
            host: d.name,
            need: 'Disarankan: TXT berisi include:spf.brevo.com (Brevo tidak mewajibkan)',
            found: d.dns_status?.records.spf,
        },
        {
            key: 'dkim',
            label: 'DKIM',
            host: `brevo1._domainkey.${d.name}`,
            need: 'CNAME/TXT dari Brevo › Senders & Domains',
            found: d.dns_status?.records.dkim,
        },
        {
            key: 'dmarc',
            label: 'DMARC',
            host: `_dmarc.${d.name}`,
            need: 'TXT v=DMARC1; p=none; rua=mailto:…',
            found: d.dns_status?.records.dmarc,
        },
        {
            key: 'mx',
            label: 'MX penerima',
            host: d.inbound_domain,
            need: `MX 10 ${props.inboundMx[0]} · MX 20 ${props.inboundMx[1]}`,
            found: d.dns_status?.records.mx.join(', ') || null,
        },
    ] as const;
</script>

<template>
    <Head title="Domain & Mailbox" />
    <div class="mx-auto w-full max-w-5xl space-y-8 p-4 sm:p-6">
        <section class="space-y-4">
            <PageHeader
                title="Domain"
                description="Domain pengirim harus terautentikasi di Brevo. Brevo hanya menerima email di subdomain terpisah yang MX-nya diarahkan ke server inbound Brevo."
            >
                <Button variant="outline" @click="editDomain(null)"
                    ><Plus /> Tambah domain</Button
                >
            </PageHeader>

            <article
                v-for="d in domains"
                :key="d.id"
                class="rounded-xl border bg-card"
            >
                <header
                    class="flex flex-wrap items-center gap-3 border-b px-5 py-3.5"
                >
                    <Globe class="size-5 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">
                            {{ d.name }}
                            <span
                                v-if="!d.is_active"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >(nonaktif)</span
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Menerima di
                            <span class="font-mono">{{
                                d.inbound_domain
                            }}</span>
                            · {{ d.mailboxes_count }} mailbox
                        </p>
                    </div>
                    <span class="text-xs text-muted-foreground">{{
                        d.dns_checked_at
                            ? `Dicek ${relative(d.dns_checked_at)}`
                            : 'Belum dicek'
                    }}</span>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="
                            router.post(
                                admin.domains.check.url(d.id),
                                {},
                                { preserveScroll: true },
                            )
                        "
                        ><RefreshCw /> Cek DNS</Button
                    >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Ubah ${d.name}`"
                        @click="editDomain(d)"
                        ><Pencil
                    /></Button>
                </header>
                <ul class="divide-y">
                    <li
                        v-for="c in checks(d)"
                        :key="c.key"
                        class="grid gap-2 px-5 py-3 sm:grid-cols-[9rem_1fr]"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <CheckCircle2
                                v-if="d.dns_status?.[c.key]"
                                class="size-4 text-success"
                                aria-label="OK"
                            />
                            <XCircle
                                v-else-if="c.key === 'dkim' || c.key === 'mx'"
                                class="size-4 text-danger"
                                aria-label="Belum"
                            />
                            <AlertTriangle
                                v-else
                                class="size-4 text-warning"
                                aria-label="Disarankan"
                            />
                            {{ c.label }}
                        </span>
                        <div class="min-w-0 space-y-1 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-muted-foreground">Host</span
                                ><CopyText :value="c.host" />
                            </div>
                            <p class="text-muted-foreground">
                                Perlu:
                                <span class="text-foreground">{{
                                    c.need
                                }}</span>
                            </p>
                            <p
                                v-if="c.found"
                                class="truncate font-mono text-muted-foreground"
                                :title="c.found"
                            >
                                Ditemukan: {{ c.found }}
                            </p>
                        </div>
                    </li>
                </ul>
            </article>
        </section>

        <section class="space-y-4">
            <PageHeader
                title="Mailbox"
                description="Identitas pengirim yang dipakai operator. Satu mailbox bisa dipakai bersama beberapa operator."
            >
                <Button :disabled="!domains.length" @click="editMailbox(null)"
                    ><Plus /> Tambah mailbox</Button
                >
            </PageHeader>
            <div class="divide-y rounded-xl border bg-card">
                <div
                    v-for="m in mailboxes"
                    :key="m.id"
                    class="flex flex-wrap items-center gap-3 px-5 py-3.5"
                >
                    <MailboxIcon class="size-5 text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ m.display_name }}
                            <span
                                v-if="!m.is_active"
                                class="text-xs font-normal text-muted-foreground"
                                >(nonaktif)</span
                            >
                        </p>
                        <p
                            class="flex flex-wrap gap-x-3 text-xs text-muted-foreground"
                        >
                            <span
                                >Kirim:
                                <span class="font-mono text-foreground/80">{{
                                    m.address
                                }}</span></span
                            >
                            <span
                                >Terima:
                                <span class="font-mono text-foreground/80">{{
                                    m.inbound_address
                                }}</span></span
                            >
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                m.users.length
                                    ? `Operator: ${m.users.map((u) => u.name).join(', ')}`
                                    : 'Belum ada operator (admin tetap bisa)'
                            }}
                            · {{ m.threads_count }} utas
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Ubah ${m.display_name}`"
                        @click="editMailbox(m)"
                        ><Pencil
                    /></Button>
                </div>
                <p
                    v-if="!mailboxes.length"
                    class="px-5 py-8 text-center text-sm text-muted-foreground"
                >
                    Belum ada mailbox.
                </p>
            </div>
        </section>
    </div>

    <Dialog v-model:open="domainOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editingDomain ? 'Ubah domain' : 'Tambah domain'
                }}</DialogTitle>
                <DialogDescription
                    >Contoh: kirim dari
                    <span class="font-mono">kendarikota.go.id</span>, terima di
                    <span class="font-mono">surat.kendarikota.go.id</span
                    >.</DialogDescription
                >
            </DialogHeader>
            <form
                id="domain-form"
                class="grid gap-4"
                @submit.prevent="submitDomain"
            >
                <div class="grid gap-1.5">
                    <Label for="d-name">Domain pengirim</Label
                    ><Input
                        id="d-name"
                        v-model="domainForm.name"
                        required
                        class="font-mono text-[13px]"
                        placeholder="kendarikota.go.id"
                    /><InputError :message="domainForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="d-in">Subdomain penerima</Label
                    ><Input
                        id="d-in"
                        v-model="domainForm.inbound_domain"
                        required
                        class="font-mono text-[13px]"
                        placeholder="surat.kendarikota.go.id"
                    /><InputError :message="domainForm.errors.inbound_domain" />
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><Switch v-model="domainForm.is_active" /> Aktif</label
                >
            </form>
            <DialogFooter class="gap-2">
                <Button
                    v-if="editingDomain"
                    variant="ghost"
                    class="mr-auto text-destructive hover:text-destructive"
                    @click="
                        router.delete(
                            admin.domains.destroy.url(editingDomain.id),
                            { onSuccess: () => (domainOpen = false) },
                        )
                    "
                    >Hapus</Button
                >
                <Button variant="outline" @click="domainOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="domain-form"
                    :disabled="domainForm.processing"
                    >Simpan</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="mailboxOpen">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader
                ><DialogTitle>{{
                    editingMailbox ? 'Ubah mailbox' : 'Tambah mailbox'
                }}</DialogTitle></DialogHeader
            >
            <form
                id="mailbox-form"
                class="grid gap-4"
                @submit.prevent="submitMailbox"
            >
                <div class="grid gap-1.5">
                    <Label for="m-local">Alamat</Label>
                    <div class="flex items-center gap-1.5">
                        <Input
                            id="m-local"
                            v-model="mailboxForm.local_part"
                            required
                            class="font-mono text-[13px]"
                            placeholder="kominfo"
                        />
                        <span class="text-muted-foreground">@</span>
                        <select
                            v-model.number="mailboxForm.domain_id"
                            class="h-9 rounded-md border bg-background px-2 font-mono text-[13px]"
                        >
                            <option
                                v-for="d in domains"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.name }}
                            </option>
                        </select>
                    </div>
                    <p
                        v-if="
                            mailboxForm.local_part &&
                            domainOf(mailboxForm.domain_id)
                        "
                        class="text-xs text-muted-foreground"
                    >
                        Balasan diterima di
                        <span class="font-mono"
                            >{{ mailboxForm.local_part }}@{{
                                domainOf(mailboxForm.domain_id)?.inbound_domain
                            }}</span
                        >
                    </p>
                    <InputError :message="mailboxForm.errors.local_part" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="m-name">Nama tampilan pengirim</Label
                    ><Input
                        id="m-name"
                        v-model="mailboxForm.display_name"
                        required
                        placeholder="Dinas Kominfo Kota Kendari"
                    /><InputError :message="mailboxForm.errors.display_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Tanda tangan</Label>
                    <div class="overflow-hidden rounded-lg border">
                        <RichEditor
                            v-model="mailboxForm.signature_html"
                            min-height="90px"
                            placeholder="Disisipkan otomatis di email baru"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>Operator</Label>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        <label
                            v-for="u in users"
                            :key="u.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="mailboxForm.user_ids.includes(u.id)"
                                class="size-4 accent-[var(--primary)]"
                                @change="toggleUser(u.id)"
                            />{{ u.name }}
                        </label>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><Switch v-model="mailboxForm.is_active" /> Aktif</label
                >
            </form>
            <DialogFooter>
                <Button variant="outline" @click="mailboxOpen = false"
                    >Batal</Button
                >
                <Button
                    type="submit"
                    form="mailbox-form"
                    :disabled="mailboxForm.processing"
                    >Simpan</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
