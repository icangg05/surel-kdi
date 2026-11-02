<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    Loader2,
    Lock,
    Search,
    Send,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import AttachmentPicker from '@/components/mail/AttachmentPicker.vue';
import RichEditor from '@/components/mail/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dateOnly, groupDot, number } from '@/lib/format';
import { json } from '@/lib/http';
import broadcastRoutes from '@/routes/broadcasts';
import templateRoutes from '@/routes/templates';
import type { AttachmentItem, GroupItem, MailboxSummary } from '@/types';

type ContactLite = {
    id: number;
    name: string;
    email: string;
    organization?: string | null;
    position?: string | null;
};
type ContactOption = {
    id: number;
    name: string;
    email: string;
    organization: string | null;
    sendable: boolean;
    groups: number[];
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
        /** null = siaran baru yang belum disimpan (masih di langkah Penerima). */
        id: number | null;
        mailbox_id: number;
        template_id: number | null;
        subject: string;
        body_html: string;
        status: string;
        scheduled_at: string | null;
        all: boolean;
        groups: number[];
        contacts: number[];
        attachments: AttachmentItem[];
    };
    groups: (GroupItem & { contacts_count: number; sendable_count: number })[];
    contacts: ContactOption[];
    templates: {
        id: number;
        name: string;
        subject: string;
        body_html: string;
    }[];
    mailboxes: MailboxSummary[];
    mergeFields: Record<string, string>;
    step: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Siaran', href: broadcastRoutes.index() },
            { title: 'Susun siaran', href: '#' },
        ],
    },
});

const DEFAULT_BODY = '<p>Yth. {{nama}},</p><p></p>';
const page = usePage();
const STEPS = ['Penerima', 'Isi', 'Pratinjau', 'Kirim'];
const step = ref(props.step);
const isNew = computed(() => props.broadcast.id === null);
// Langkah setelah Penerima hanya terbuka bila draf sudah ada, jadi id pasti terisi.
const broadcastId = () => props.broadcast.id as number;
const form = reactive({
    mailbox_id: props.broadcast.mailbox_id,
    template_id: props.broadcast.template_id,
    subject: props.broadcast.subject,
    body_html: props.broadcast.body_html,
    all: props.broadcast.all,
    groups: [...props.broadcast.groups],
    contacts: [...props.broadcast.contacts],
});
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
const creating = ref(false);

async function save() {
    if (isNew.value) {
        return;
    }

    try {
        const res = await json<{ saved_at: string }>(
            'PUT',
            broadcastRoutes.update.url(broadcastId()),
            form,
        );
        savedAt.value = res.saved_at;
    } catch (e) {
        toast.error(`Gagal menyimpan: ${(e as Error).message}`);
    }
}

// Siaran baru belum punya draf; tidak ada yang disimpan sebelum operator lanjut.
const autosave = useDebounceFn(save, 1200);
watch(form, () => !isNew.value && autosave(), { deep: true });

const refreshEstimate = useDebounceFn(async () => {
    estimate.value = await json<Estimate>(
        'POST',
        broadcastRoutes.estimate.url(),
        {
            all: form.all,
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
    () => [form.all, form.groups, form.contacts, mode.value, scheduleAt.value],
    refreshEstimate,
    { deep: true },
);
onMounted(refreshEstimate);

// Penerima
function toggleGroup(id: number) {
    form.groups = form.groups.includes(id)
        ? form.groups.filter((g) => g !== id)
        : [...form.groups, id];
}

const sendableTotal = computed(
    () => props.contacts.filter((c) => c.sendable).length,
);
const contactFilter = ref('');
const filteredContacts = computed(() => {
    const q = contactFilter.value.trim().toLowerCase();

    return q
        ? props.contacts.filter((c) =>
              [c.name, c.email, c.organization ?? ''].some((v) =>
                  v.toLowerCase().includes(q),
              ),
          )
        : props.contacts;
});
const viaGroup = (c: ContactOption) =>
    c.groups.some((g) => form.groups.includes(g));
const isPicked = (c: ContactOption) =>
    c.sendable && (viaGroup(c) || form.contacts.includes(c.id));

function toggleContact(id: number) {
    form.contacts = form.contacts.includes(id)
        ? form.contacts.filter((x) => x !== id)
        : [...form.contacts, id];
}

// "Pilih semua" di daftar berlaku untuk kontak yang sedang tampil (hasil saringan).
const pickableVisible = computed(() =>
    filteredContacts.value.filter((c) => c.sendable && !viaGroup(c)),
);
const allVisiblePicked = computed(
    () =>
        pickableVisible.value.length > 0 &&
        pickableVisible.value.every((c) => form.contacts.includes(c.id)),
);

function toggleVisible() {
    const ids = pickableVisible.value.map((c) => c.id);

    form.contacts = allVisiblePicked.value
        ? form.contacts.filter((id) => !ids.includes(id))
        : [...new Set([...form.contacts, ...ids])];
}

// Templat: siaran bertemplat terkunci, subjek & isi hanya diubah dari menu Templat.
const template = computed(
    () => props.templates.find((t) => t.id === form.template_id) ?? null,
);

function chooseTemplate(value: unknown) {
    const next = props.templates.find((t) => t.id === Number(value)) ?? null;

    if ((next?.id ?? null) === form.template_id) {
        return;
    }

    if (!next) {
        form.template_id = null;
        form.subject = '';
        form.body_html = DEFAULT_BODY;

        return;
    }

    const written =
        !template.value &&
        (form.subject.trim() !== '' ||
            form.body_html.replace(/<[^>]+>/g, '').trim().length > 20);

    if (written && !confirm('Ganti subjek dan isi saat ini dengan templat?')) {
        return;
    }

    form.template_id = next.id;
    form.subject = next.subject;
    form.body_html = next.body_html;
}

// Pratinjau
const previewContact = ref<number | null>(null);
const preview = ref<{ subject: string; html: string } | null>(null);

async function loadPreview() {
    preview.value = await json(
        'POST',
        broadcastRoutes.preview.url(broadcastId()),
        {
            contact_id: previewContact.value,
            subject: form.subject,
            body_html: form.body_html,
        },
    );
}

watch(
    step,
    (s) => {
        if (s === 2) {
            previewContact.value ??= estimate.value?.sample[0]?.id ?? null;
            loadPreview();
        }
    },
    { immediate: props.step === 2 },
);
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
        broadcastRoutes.test.url(broadcastId()),
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

// Siaran baru: draf dibuat di sini, lalu server membuka langkah Isi.
function next() {
    if (!isNew.value) {
        step.value++;

        return;
    }

    router.post(
        broadcastRoutes.store.url(),
        {
            mailbox_id: form.mailbox_id,
            template_id: form.template_id,
            all: form.all,
            groups: form.groups,
            contacts: form.contacts,
        },
        {
            onStart: () => (creating.value = true),
            onFinish: () => (creating.value = false),
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Gagal membuat draf'),
        },
    );
}

function goTo(i: number) {
    if (i <= step.value) {
        step.value = i;
    } else if (canNext.value && isNew.value) {
        next();
    } else if (canNext.value) {
        step.value = i;
    }
}

async function launch() {
    launching.value = true;
    await save();
    router.post(
        broadcastRoutes.launch.url(broadcastId()),
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
    router.delete(broadcastRoutes.destroy.url(broadcastId()));
}

const days = computed(() => estimate.value?.forecast.length ?? 0);
</script>

<template>
    <Head title="Susun siaran" />

    <div class="mx-auto w-full max-w-6xl p-4 sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold tracking-tight">
                    {{ form.subject || 'Siaran baru' }}
                </h1>
                <p class="text-xs text-muted-foreground">
                    <template v-if="isNew"
                        >Belum disimpan. Draf dibuat setelah Anda lanjut ke
                        langkah Isi.</template
                    >
                    <template v-else-if="savedAt"
                        >Draf tersimpan otomatis
                        {{
                            new Date(savedAt).toLocaleTimeString('id-ID', {
                                hour: '2-digit',
                                minute: '2-digit',
                            })
                        }}</template
                    >
                    <template v-else>Draf tersimpan otomatis</template>
                </p>
            </div>
            <Button
                v-if="isNew"
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
                as-child
                ><Link :href="broadcastRoutes.index()">Batal</Link></Button
            >
            <ConfirmDialog
                v-else
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
                    @click="goTo(i)"
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
                <section v-show="step === 0" class="space-y-4">
                    <button
                        type="button"
                        :aria-pressed="form.all"
                        :class="[
                            'flex w-full items-center gap-3 rounded-xl border bg-card px-4 py-3.5 text-left transition-colors',
                            form.all
                                ? 'border-primary ring-1 ring-primary'
                                : 'hover:bg-muted/40',
                        ]"
                        @click="form.all = !form.all"
                    >
                        <span
                            :class="[
                                'flex size-5 shrink-0 items-center justify-center rounded border',
                                form.all
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : '',
                            ]"
                        >
                            <Check v-if="form.all" class="size-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium"
                                >Pilih semua kontak</span
                            >
                            <span class="block text-sm text-muted-foreground"
                                >Kirim ke seluruh
                                {{ number(sendableTotal) }} kontak aktif tanpa
                                memilih satu per satu.</span
                            >
                        </span>
                    </button>

                    <template v-if="!form.all">
                        <div class="rounded-xl border bg-card">
                            <div class="border-b px-4 py-3">
                                <h2 class="font-semibold">Grup</h2>
                                <p class="text-sm text-muted-foreground">
                                    Kontak yang ada di beberapa grup tetap hanya
                                    dikirimi sekali.
                                </p>
                            </div>
                            <div
                                v-if="groups.length"
                                class="grid gap-2 p-4 sm:grid-cols-2"
                            >
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
                                                    'size-2 shrink-0 rounded-full',
                                                    groupDot(g.color),
                                                ]"
                                            /><span class="truncate">{{
                                                g.name
                                            }}</span></span
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
                            <p v-else class="p-4 text-sm text-muted-foreground">
                                Belum ada grup. Buat grup di halaman Kontak &
                                Grup.
                            </p>
                        </div>

                        <div class="overflow-hidden rounded-xl border bg-card">
                            <div
                                class="flex flex-wrap items-center gap-3 border-b px-4 py-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-semibold">Kontak</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Centang nama untuk menambah penerima di
                                        luar grup.
                                    </p>
                                </div>
                                <label class="relative w-full sm:w-64">
                                    <span class="sr-only">Saring kontak</span>
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="contactFilter"
                                        type="search"
                                        placeholder="Saring nama, email, instansi"
                                        class="pl-9"
                                    />
                                </label>
                            </div>
                            <label
                                v-if="pickableVisible.length"
                                class="flex cursor-pointer items-center gap-3 border-b bg-muted/30 px-4 py-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="allVisiblePicked"
                                    class="size-4 rounded accent-[var(--primary)]"
                                    @change="toggleVisible"
                                />
                                <span class="flex-1 font-medium"
                                    >Pilih semua
                                    {{
                                        contactFilter.trim()
                                            ? 'hasil saringan'
                                            : 'kontak'
                                    }}
                                    ({{ number(pickableVisible.length) }})</span
                                >
                                <span
                                    class="tabular text-xs text-muted-foreground"
                                    >{{
                                        number(form.contacts.length)
                                    }}
                                    dipilih</span
                                >
                            </label>
                            <ul
                                v-if="filteredContacts.length"
                                class="max-h-96 divide-y overflow-y-auto"
                            >
                                <li v-for="c in filteredContacts" :key="c.id">
                                    <label
                                        :class="[
                                            'flex items-center gap-3 px-4 py-2.5',
                                            c.sendable && !viaGroup(c)
                                                ? 'cursor-pointer hover:bg-muted/40'
                                                : 'opacity-70',
                                        ]"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="isPicked(c)"
                                            :disabled="
                                                !c.sendable || viaGroup(c)
                                            "
                                            class="size-4 shrink-0 rounded accent-[var(--primary)]"
                                            @change="toggleContact(c.id)"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{{ c.name }}</span
                                            >
                                            <span
                                                class="block truncate text-xs text-muted-foreground"
                                                >{{
                                                    c.organization
                                                        ? `${c.organization} · ${c.email}`
                                                        : c.email
                                                }}</span
                                            >
                                        </span>
                                        <span
                                            v-if="!c.sendable"
                                            class="shrink-0 text-xs text-warning"
                                            >Dilewati</span
                                        >
                                        <span
                                            v-else-if="viaGroup(c)"
                                            class="shrink-0 text-xs text-muted-foreground"
                                            >Lewat grup</span
                                        >
                                    </label>
                                </li>
                            </ul>
                            <p v-else class="p-4 text-sm text-muted-foreground">
                                {{
                                    contacts.length
                                        ? 'Tidak ada kontak yang cocok.'
                                        : 'Belum ada kontak. Tambahkan di halaman Kontak & Grup.'
                                }}
                            </p>
                        </div>
                    </template>
                </section>

                <!-- 2. Isi -->
                <section v-show="step === 1" class="space-y-3">
                    <div class="overflow-hidden rounded-xl border bg-card">
                        <div class="grid gap-4 border-b p-4 sm:grid-cols-2">
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="b-from">Dikirim dari</Label>
                                <Select v-model="form.mailbox_id">
                                    <SelectTrigger id="b-from" class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="m in mailboxes"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.name }} &lt;{{ m.address }}&gt;
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="b-template">Templat</Label>
                                <Select
                                    :model-value="form.template_id ?? 0"
                                    :disabled="!templates.length"
                                    @update:model-value="chooseTemplate"
                                >
                                    <SelectTrigger
                                        id="b-template"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="0">
                                            {{
                                                templates.length
                                                    ? 'Tanpa templat (tulis sendiri)'
                                                    : 'Belum ada templat'
                                            }}
                                        </SelectItem>
                                        <SelectItem
                                            v-for="t in templates"
                                            :key="t.id"
                                            :value="t.id"
                                        >
                                            {{ t.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div
                            v-if="template"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b bg-info-soft px-4 py-2.5 text-sm text-info"
                        >
                            <Lock class="size-4 shrink-0" />
                            <p class="min-w-0 flex-1">
                                Subjek dan isi mengikuti templat
                                <strong class="font-semibold">{{
                                    template.name
                                }}</strong>
                                dan hanya bisa diubah dari menu Templat.
                            </p>
                            <Link
                                :href="templateRoutes.edit.url(template.id)"
                                class="font-medium underline underline-offset-2"
                                >Ubah templat</Link
                            >
                        </div>
                        <div class="grid gap-1.5 border-b p-4">
                            <Label for="b-subject">Subjek</Label>
                            <Input
                                id="b-subject"
                                v-model="form.subject"
                                :readonly="!!template"
                                placeholder="mis. Undangan Rapat Koordinasi Website OPD"
                                :class="[
                                    'text-[15px]',
                                    template ? 'bg-muted/50' : '',
                                ]"
                            />
                        </div>
                        <RichEditor
                            v-model="form.body_html"
                            :merge-fields="mergeFields"
                            :readonly="!!template"
                            min-height="320px"
                        />
                        <div
                            v-if="broadcast.id !== null"
                            class="border-t px-4 py-2.5"
                        >
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
                            <Select v-model="previewContact">
                                <SelectTrigger id="b-sample" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="c in estimate?.sample ?? []"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }} ({{
                                            c.organization || c.email
                                        }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
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
                            {{ number(estimate.limit) }}/hari,
                            <template v-if="days > 1"
                                ><strong class="font-semibold text-foreground"
                                    >selesai dalam {{ days }} hari</strong
                                >. Sisa penerima otomatis dilanjutkan setelah
                                kuota reset.</template
                            >
                            <template v-else>selesai hari ini.</template>
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
                    <Button
                        v-if="step < 3"
                        :disabled="!canNext || creating"
                        @click="next"
                        >Lanjut: {{ STEPS[step + 1] }}
                        <Loader2
                            v-if="creating"
                            class="animate-spin" /><ChevronRight v-else
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
