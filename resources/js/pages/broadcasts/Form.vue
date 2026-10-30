<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    Loader2,
    Send,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import AttachmentPicker from '@/components/mail/AttachmentPicker.vue';
import RichEditor from '@/components/mail/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateOnly, groupDot, number } from '@/lib/format';
import { json } from '@/lib/http';
import broadcastRoutes from '@/routes/broadcasts';
import { search as searchRecipients } from '@/routes/recipients';
import type { AttachmentItem, GroupItem, MailboxSummary } from '@/types';

type ContactLite = {
    id: number;
    name: string;
    email: string;
    organization?: string | null;
    position?: string | null;
};
type Estimate = {
    total: number;
    sendable: number;
    suppressed: number;
    sample: ContactLite[];
    forecast: { date: string; count: number }[];
    remaining_today: number;
    limit: number;
};

const props = defineProps<{
    broadcast: {
        id: number;
        mailbox_id: number;
        subject: string;
        body_html: string;
        status: string;
        scheduled_at: string | null;
        groups: number[];
        contacts: ContactLite[];
        attachments: AttachmentItem[];
    };
    groups: (GroupItem & { contacts_count: number; sendable_count: number })[];
    templates: {
        id: number;
        name: string;
        subject: string;
        body_html: string;
    }[];
    mailboxes: MailboxSummary[];
    mergeFields: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Siaran', href: broadcastRoutes.index() },
            { title: 'Susun siaran', href: '#' },
        ],
    },
});

const page = usePage();
const STEPS = ['Penerima', 'Isi', 'Pratinjau', 'Kirim'];
const step = ref(0);
const form = reactive({
    mailbox_id: props.broadcast.mailbox_id,
    subject: props.broadcast.subject,
    body_html: props.broadcast.body_html,
    groups: [...props.broadcast.groups],
    contacts: props.broadcast.contacts.map((c) => c.id),
});
const pickedContacts = ref<ContactLite[]>([...props.broadcast.contacts]);
const attachments = ref<AttachmentItem[]>(props.broadcast.attachments);
const estimate = ref<Estimate | null>(null);
const savedAt = ref<string | null>(null);
const mode = ref<'now' | 'later'>(
    props.broadcast.scheduled_at ? 'later' : 'now',
);
const scheduleAt = ref(
    props.broadcast.scheduled_at
        ? props.broadcast.scheduled_at.slice(0, 16)
        : '',
);
const launching = ref(false);

async function save() {
    try {
        const res = await json<{ saved_at: string }>(
            'PUT',
            broadcastRoutes.update.url(props.broadcast.id),
            form,
        );
        savedAt.value = res.saved_at;
    } catch (e) {
        toast.error(`Gagal menyimpan: ${(e as Error).message}`);
    }
}

const autosave = useDebounceFn(save, 1200);
watch(form, autosave, { deep: true });

const refreshEstimate = useDebounceFn(async () => {
    estimate.value = await json<Estimate>(
        'POST',
        broadcastRoutes.estimate.url(props.broadcast.id),
        {
            groups: form.groups,
            contacts: form.contacts,
            scheduled_at:
                mode.value === 'later' && scheduleAt.value
                    ? new Date(scheduleAt.value).toISOString()
                    : null,
        },
    );
}, 250);
watch(
    () => [form.groups, form.contacts, mode.value, scheduleAt.value],
    refreshEstimate,
    { deep: true },
);
onMounted(refreshEstimate);

function toggleGroup(id: number) {
    form.groups = form.groups.includes(id)
        ? form.groups.filter((g) => g !== id)
        : [...form.groups, id];
}

// Pencarian kontak tambahan (di luar grup).
const contactQuery = ref('');
const contactHits = ref<ContactLite[]>([]);
const searchContacts = useDebounceFn(async () => {
    if (contactQuery.value.trim().length < 2) {
        contactHits.value = [];

        return;
    }

    const res = await json<{
        contacts: (ContactLite & { suppressed: boolean })[];
    }>('GET', searchRecipients.url({ query: { q: contactQuery.value } }));
    contactHits.value = res.contacts.filter(
        (c) => !form.contacts.includes(c.id),
    );
}, 200);
watch(contactQuery, searchContacts);

function addContact(c: ContactLite) {
    form.contacts = [...form.contacts, c.id];
    pickedContacts.value = [...pickedContacts.value, c];
    contactQuery.value = '';
    contactHits.value = [];
}

function removeContact(id: number) {
    form.contacts = form.contacts.filter((x) => x !== id);
    pickedContacts.value = pickedContacts.value.filter((c) => c.id !== id);
}

// Templat
function applyTemplate(e: Event) {
    const id = Number((e.target as HTMLSelectElement).value);
    (e.target as HTMLSelectElement).value = '';

    if (!id) {
        return;
    }

    if (
        form.body_html.replace(/<[^>]+>/g, '').trim().length > 20 &&
        !confirm('Ganti isi saat ini dengan templat?')
    ) {
        return;
    }

    const template = props.templates.find((t) => t.id === id);

    if (template) {
        form.subject = template.subject;
        form.body_html = template.body_html;
    }
}

// Pratinjau
const previewContact = ref<number | null>(null);
const preview = ref<{ subject: string; html: string } | null>(null);

async function loadPreview() {
    preview.value = await json(
        'POST',
        broadcastRoutes.preview.url(props.broadcast.id),
        {
            contact_id: previewContact.value,
            subject: form.subject,
            body_html: form.body_html,
        },
    );
}

watch(step, (s) => {
    if (s === 2) {
        previewContact.value ??= estimate.value?.sample[0]?.id ?? null;
        loadPreview();
    }
});
watch(previewContact, () => step.value === 2 && loadPreview());

const previewDoc = computed(() =>
    preview.value
        ? `<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{margin:0;padding:20px;font-family:ui-sans-serif,system-ui,sans-serif;font-size:15px;line-height:1.65;color:#1f2328;background:#fff}img{max-width:100%}blockquote{border-left:3px solid #d0d7de;padding-left:12px;color:#57606a;margin:12px 0}</style></head><body>${preview.value.html}</body></html>`
        : '',
);

const testEmail = ref(String(page.props.auth.user?.email ?? ''));
const testing = ref(false);

async function sendTest() {
    testing.value = true;
    await save();
    router.post(
        broadcastRoutes.test.url(props.broadcast.id),
        { email: testEmail.value },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Gagal'),
            onFinish: () => (testing.value = false),
        },
    );
}

// Validasi per langkah
const issues = computed(() => {
    const list: string[] = [];

    if (!estimate.value?.sendable) {
        list.push('Belum ada penerima aktif.');
    }

    if (!form.subject.trim()) {
        list.push('Subjek belum diisi.');
    }

    if (form.body_html.replace(/<[^>]+>/g, '').trim().length < 5) {
        list.push('Isi siaran masih kosong.');
    }

    return list;
});

const canNext = computed(() =>
    step.value === 0
        ? (estimate.value?.sendable ?? 0) > 0
        : step.value === 1
          ? !!form.subject.trim()
          : true,
);

async function launch() {
    launching.value = true;
    await save();
    router.post(
        broadcastRoutes.launch.url(props.broadcast.id),
        {
            scheduled_at:
                mode.value === 'later' && scheduleAt.value
                    ? new Date(scheduleAt.value).toISOString()
                    : null,
        },
        {
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Gagal memulai siaran'),
            onFinish: () => (launching.value = false),
        },
    );
}

function discard() {
    router.delete(broadcastRoutes.destroy.url(props.broadcast.id));
}

const days = computed(() => estimate.value?.forecast.length ?? 0);
</script>

<template>
    <Head title="Susun siaran" />

    <div class="mx-auto w-full max-w-6xl p-4 sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ form.subject || 'Siaran baru' }}
                </h1>
                <p class="text-xs text-muted-foreground">
                    {{
                        savedAt
                            ? `Draf tersimpan otomatis ${new Date(savedAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`
                            : 'Draf — tersimpan otomatis'
                    }}
                </p>
            </div>
            <ConfirmDialog
                title="Buang draf siaran ini?"
                confirm-label="Buang"
                destructive
                @confirm="discard"
            >
                <Button variant="ghost" size="sm" class="text-muted-foreground"
                    ><Trash2 /> Buang draf</Button
                >
            </ConfirmDialog>
        </div>

        <!-- Langkah -->
        <ol class="mb-6 grid grid-cols-4 gap-2" aria-label="Langkah siaran">
            <li v-for="(label, i) in STEPS" :key="label">
                <button
                    type="button"
                    :aria-current="i === step ? 'step' : undefined"
                    :class="[
                        'flex w-full flex-col gap-2 text-left',
                        i > step && !canNext ? 'cursor-default' : '',
                    ]"
                    @click="i <= step || canNext ? (step = i) : null"
                >
                    <span
                        :class="[
                            'h-1 rounded-sm',
                            i <= step ? 'bg-primary' : 'bg-muted',
                        ]"
                    />
                    <span
                        :class="[
                            'flex items-center gap-1.5 text-xs sm:text-sm',
                            i === step
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                        ]"
                    >
                        <span
                            :class="[
                                'hidden size-5 items-center justify-center rounded-sm text-[11px] sm:flex',
                                i < step
                                    ? 'bg-primary text-primary-foreground'
                                    : i === step
                                      ? 'border-2 border-primary text-primary'
                                      : 'border',
                            ]"
                        >
                            <Check v-if="i < step" class="size-3" /><template
                                v-else
                                >{{ i + 1 }}</template
                            >
                        </span>
                        {{ label }}
                    </span>
                </button>
            </li>
        </ol>

        <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
            <div class="min-w-0">
                <!-- 1. Penerima -->
                <section v-show="step === 0" class="space-y-5">
                    <div>
                        <h2 class="font-semibold">Pilih grup</h2>
                        <p class="text-sm text-muted-foreground">
                            Kontak yang ada di beberapa grup tetap hanya
                            dikirimi sekali.
                        </p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="g in groups"
                                :key="g.id"
                                type="button"
                                :aria-pressed="form.groups.includes(g.id)"
                                :class="[
                                    'flex items-center gap-3 rounded-lg border px-3.5 py-3 text-left transition-colors',
                                    form.groups.includes(g.id)
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                        : 'hover:bg-muted/50',
                                ]"
                                @click="toggleGroup(g.id)"
                            >
                                <span
                                    :class="[
                                        'flex size-5 shrink-0 items-center justify-center rounded border',
                                        form.groups.includes(g.id)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : '',
                                    ]"
                                >
                                    <Check
                                        v-if="form.groups.includes(g.id)"
                                        class="size-3.5"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex items-center gap-2 font-medium"
                                        ><span
                                            :class="[
                                                'size-2 rounded-full',
                                                groupDot(g.color),
                                            ]"
                                        />{{ g.name }}</span
                                    >
                                    <span
                                        class="tabular text-xs text-muted-foreground"
                                        >{{
                                            number(g.sendable_count)
                                        }}
                                        aktif<template
                                            v-if="
                                                g.contacts_count >
                                                g.sendable_count
                                            "
                                        >
                                            ·
                                            {{
                                                g.contacts_count -
                                                g.sendable_count
                                            }}
                                            dilewati</template
                                        ></span
                                    >
                                </span>
                            </button>
                        </div>
                        <p
                            v-if="!groups.length"
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            Belum ada grup. Buat grup di halaman Kontak & Grup.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-semibold">
                            Tambah kontak satu per satu
                        </h2>
                        <div class="relative mt-3">
                            <Input
                                v-model="contactQuery"
                                placeholder="Cari nama, email, atau instansi"
                            />
                            <div
                                v-if="contactHits.length"
                                class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border bg-popover p-1 shadow-lg"
                            >
                                <button
                                    v-for="c in contactHits"
                                    :key="c.id"
                                    type="button"
                                    class="flex w-full flex-col rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                    @click="addContact(c)"
                                >
                                    <span class="font-medium">{{
                                        c.name
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ c.email }}</span
                                    >
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="pickedContacts.length"
                            class="mt-3 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="c in pickedContacts"
                                :key="c.id"
                                class="inline-flex items-center gap-1 rounded bg-secondary py-0.5 pr-1 pl-2.5 text-[13px]"
                            >
                                {{ c.name }}
                                <button
                                    type="button"
                                    class="rounded-sm p-0.5 hover:bg-foreground/10"
                                    :aria-label="`Hapus ${c.name}`"
                                    @click="removeContact(c.id)"
                                >
                                    <X class="size-3" />
                                </button>
                            </span>
                        </div>
                    </div>
                </section>

                <!-- 2. Isi -->
                <section v-show="step === 1" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="b-from">Dikirim dari</Label>
                            <select
                                id="b-from"
                                v-model.number="form.mailbox_id"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="m in mailboxes"
                                    :key="m.id"
                                    :value="m.id"
                                >
                                    {{ m.name }} — {{ m.address }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="b-template">Mulai dari templat</Label>
                            <select
                                id="b-template"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                                @change="applyTemplate"
                            >
                                <option value="">— pilih templat —</option>
                                <option
                                    v-for="t in templates"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="b-subject">Subjek</Label>
                        <Input
                            id="b-subject"
                            v-model="form.subject"
                            placeholder="mis. Undangan Rapat Koordinasi Website OPD"
                            class="text-[15px]"
                        />
                    </div>
                    <div class="overflow-hidden rounded-xl border bg-card">
                        <RichEditor
                            v-model="form.body_html"
                            :merge-fields="mergeFields"
                            min-height="320px"
                        />
                        <div class="border-t px-4 py-2.5">
                            <AttachmentPicker
                                v-model="attachments"
                                :broadcast-id="broadcast.id"
                                :max-mb="
                                    page.props.mail?.max_attachments_mb ?? 10
                                "
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Isian seperti
                        <code
                            class="rounded bg-info-soft px-1 font-mono text-info"
                            v-text="'{{nama}}'"
                        />
                        diganti nama masing-masing penerima. Lampiran ikut ke
                        setiap email dan dihitung ke batas ukuran Brevo.
                    </p>
                </section>

                <!-- 3. Pratinjau -->
                <section v-show="step === 2" class="space-y-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="grid min-w-56 flex-1 gap-1.5">
                            <Label for="b-sample">Lihat sebagai</Label>
                            <select
                                id="b-sample"
                                v-model="previewContact"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="c in estimate?.sample ?? []"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }} —
                                    {{ c.organization || c.email }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-xl border bg-card">
                        <div class="border-b px-5 py-3">
                            <p class="text-xs text-muted-foreground">Subjek</p>
                            <p class="font-medium">
                                {{ preview?.subject || '—' }}
                            </p>
                        </div>
                        <iframe
                            v-if="preview"
                            :srcdoc="previewDoc"
                            sandbox="allow-popups"
                            title="Pratinjau siaran"
                            class="h-[460px] w-full bg-white"
                        />
                        <div
                            v-else
                            class="flex h-40 items-center justify-center"
                        >
                            <Loader2
                                class="size-5 animate-spin text-muted-foreground"
                            />
                        </div>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-sm font-medium">Kirim email uji</p>
                        <p class="text-xs text-muted-foreground">
                            Dikirim sungguhan lewat Brevo dengan subjek
                            berawalan [UJI]. Memakai 1 kredit kuota.
                        </p>
                        <div class="mt-3 flex gap-2">
                            <Input
                                v-model="testEmail"
                                type="email"
                                class="max-w-sm"
                            />
                            <Button
                                variant="outline"
                                :disabled="testing || !testEmail"
                                @click="sendTest"
                                ><Loader2 v-if="testing" class="animate-spin" />
                                Kirim uji</Button
                            >
                        </div>
                    </div>
                </section>

                <!-- 4. Jadwal & kirim -->
                <section v-show="step === 3" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            :aria-pressed="mode === 'now'"
                            :class="[
                                'rounded-xl border p-4 text-left',
                                mode === 'now'
                                    ? 'border-primary ring-1 ring-primary'
                                    : 'hover:bg-muted/40',
                            ]"
                            @click="mode = 'now'"
                        >
                            <Send class="mb-2 size-5 text-primary" />
                            <p class="font-medium">Mulai sekarang</p>
                            <p class="text-sm text-muted-foreground">
                                Dikirim bertahap mulai menit ini.
                            </p>
                        </button>
                        <button
                            type="button"
                            :aria-pressed="mode === 'later'"
                            :class="[
                                'rounded-xl border p-4 text-left',
                                mode === 'later'
                                    ? 'border-primary ring-1 ring-primary'
                                    : 'hover:bg-muted/40',
                            ]"
                            @click="mode = 'later'"
                        >
                            <Clock class="mb-2 size-5 text-primary" />
                            <p class="font-medium">Jadwalkan</p>
                            <p class="text-sm text-muted-foreground">
                                Pilih tanggal & jam mulai.
                            </p>
                        </button>
                    </div>
                    <div v-if="mode === 'later'" class="grid max-w-xs gap-1.5">
                        <Label for="b-at">Mulai pada</Label>
                        <Input
                            id="b-at"
                            v-model="scheduleAt"
                            type="datetime-local"
                        />
                    </div>

                    <div v-if="estimate" class="rounded-xl border bg-card p-4">
                        <p class="font-medium">Perkiraan jadwal pengiriman</p>
                        <p class="text-sm text-muted-foreground">
                            {{ number(estimate.sendable) }} email dengan kuota
                            {{ number(estimate.limit) }}/hari
                            <template v-if="days > 1"
                                >—
                                <strong class="font-semibold text-foreground"
                                    >selesai dalam {{ days }} hari</strong
                                >. Sisa penerima otomatis dilanjutkan setelah
                                kuota reset.</template
                            >
                            <template v-else>— selesai hari ini.</template>
                        </p>
                        <ul class="mt-3 space-y-1.5">
                            <li
                                v-for="d in estimate.forecast"
                                :key="d.date"
                                class="flex items-center gap-3 text-sm"
                            >
                                <span
                                    class="w-40 shrink-0 text-muted-foreground"
                                    >{{ dateOnly(d.date) }}</span
                                >
                                <span
                                    class="h-2 flex-1 overflow-hidden rounded-sm bg-muted"
                                    ><span
                                        class="block h-full rounded-sm bg-primary"
                                        :style="{
                                            width: `${(d.count / estimate.limit) * 100}%`,
                                        }"
                                /></span>
                                <span class="tabular w-14 text-right">{{
                                    number(d.count)
                                }}</span>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="issues.length"
                        class="rounded-lg bg-warning-soft p-3 text-sm text-warning"
                    >
                        <p
                            v-for="i in issues"
                            :key="i"
                            class="flex items-center gap-2"
                        >
                            <AlertTriangle class="size-4" />{{ i }}
                        </p>
                    </div>
                </section>

                <div
                    class="mt-6 flex items-center justify-between border-t pt-4"
                >
                    <Button v-if="step > 0" variant="outline" @click="step--"
                        ><ChevronLeft /> Kembali</Button
                    >
                    <span v-else />
                    <Button v-if="step < 3" :disabled="!canNext" @click="step++"
                        >Lanjut: {{ STEPS[step + 1] }} <ChevronRight
                    /></Button>
                    <ConfirmDialog
                        v-else
                        :title="
                            mode === 'later'
                                ? 'Jadwalkan siaran ini?'
                                : `Kirim ke ${number(estimate?.sendable ?? 0)} penerima?`
                        "
                        :description="`Setiap penerima mendapat email personal dari ${mailboxes.find((m) => m.id === form.mailbox_id)?.address}. Siaran bisa dijeda atau dibatalkan selama berjalan.`"
                        :confirm-label="
                            mode === 'later' ? 'Jadwalkan' : 'Mulai kirim'
                        "
                        @confirm="launch"
                    >
                        <Button
                            :disabled="
                                issues.length > 0 ||
                                launching ||
                                (mode === 'later' && !scheduleAt)
                            "
                        >
                            <Loader2
                                v-if="launching"
                                class="animate-spin"
                            /><Send v-else />
                            {{
                                mode === 'later'
                                    ? 'Jadwalkan siaran'
                                    : 'Mulai kirim'
                            }}
                        </Button>
                    </ConfirmDialog>
                </div>
            </div>

            <!-- Ringkasan -->
            <aside class="h-fit space-y-3 lg:sticky lg:top-4">
                <div class="rounded-xl border bg-card p-4">
                    <p
                        class="flex items-center gap-2 text-xs font-medium text-muted-foreground"
                    >
                        <Users class="size-4" /> Penerima
                    </p>
                    <p
                        class="tabular mt-1 text-3xl font-semibold tracking-tight"
                    >
                        {{ estimate ? number(estimate.sendable) : '…' }}
                    </p>
                    <p
                        v-if="estimate?.suppressed"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ estimate.suppressed }} kontak dilewati (pernah
                        bounce/spam)
                    </p>
                    <div
                        v-if="estimate"
                        class="mt-4 border-t pt-3 text-xs text-muted-foreground"
                    >
                        <p>
                            Sisa kuota hari ini:
                            <span class="tabular font-medium text-foreground">{{
                                number(estimate.remaining_today)
                            }}</span>
                        </p>
                        <p v-if="days > 1" class="mt-1 text-warning">
                            Perlu {{ days }} hari pengiriman.
                        </p>
                    </div>
                </div>
                <div
                    class="rounded-xl border bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground"
                >
                    Balasan dari penerima masuk ke Kotak Masuk mailbox pengirim
                    dan tertaut ke siaran ini, lengkap dengan salinan email yang
                    ia terima.
                </div>
            </aside>
        </div>
    </div>
</template>
