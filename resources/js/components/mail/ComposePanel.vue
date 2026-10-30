<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    addDays,
    format,
    nextMonday,
    setHours,
    setMinutes,
    startOfHour,
} from 'date-fns';
import { id as localeId } from 'date-fns/locale';
import {
    CalendarClock,
    ChevronDown,
    Loader2,
    Maximize2,
    Minus,
    Paperclip,
    Send,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AttachmentPicker from '@/components/mail/AttachmentPicker.vue';
import RecipientInput from '@/components/mail/RecipientInput.vue';
import RichEditor from '@/components/mail/RichEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useCompose } from '@/composables/useCompose';
import { json } from '@/lib/http';
import {
    broadcast as toBroadcast,
    destroy as destroyDraft,
    send as sendDraft,
    update as updateDraft,
} from '@/routes/drafts';
import type { AttachmentItem, Recipient } from '@/types';

const page = usePage();
const { state, close } = useCompose();
const mail = computed(() => page.props.mail);

const form = reactive({
    mailbox_id: 0,
    to: [] as Recipient[],
    cc: [] as Recipient[],
    bcc: [] as Recipient[],
    subject: '',
    body_html: '',
});
const attachments = ref<AttachmentItem[]>([]);
const showCc = ref(false);
const savedAt = ref<string | null>(null);
const saving = ref(false);
const sending = ref(false);
const scheduleOpen = ref(false);
const scheduleAt = ref('');
const picker = ref<InstanceType<typeof AttachmentPicker> | null>(null);
let hydrating = false;

watch(
    () => state.draft,
    (draft) => {
        if (!draft) {
            return;
        }

        hydrating = true;
        Object.assign(form, {
            mailbox_id: draft.mailbox_id,
            to: draft.to,
            cc: draft.cc,
            bcc: draft.bcc,
            subject: draft.subject,
            body_html: draft.body_html,
        });
        attachments.value = draft.attachments;
        showCc.value = draft.cc.length > 0 || draft.bcc.length > 0;
        savedAt.value = null;
        queueMicrotask(() => (hydrating = false));
    },
    { immediate: true },
);

async function save() {
    if (!state.draft) {
        return;
    }

    saving.value = true;

    try {
        const res = await json<{ saved_at: string }>(
            'PUT',
            updateDraft.url(state.draft.id),
            form,
        );
        savedAt.value = res.saved_at;
    } catch (e) {
        toast.error(`Draf gagal disimpan: ${(e as Error).message}`);
    } finally {
        saving.value = false;
    }
}

const autosave = useDebounceFn(save, 1200);
watch(form, () => !hydrating && autosave(), { deep: true });

const recipientCount = computed(
    () => form.to.length + form.cc.length + form.bcc.length,
);
const manyRecipients = computed(
    () => recipientCount.value > (mail.value?.broadcast_hint ?? 10),
);
const savedLabel = computed(() =>
    saving.value
        ? 'Menyimpan…'
        : savedAt.value
          ? `Tersimpan ${format(new Date(savedAt.value), 'HH.mm')}`
          : 'Draf',
);

async function send(scheduledAt?: string) {
    if (!state.draft || sending.value) {
        return;
    }

    if (!form.subject.trim() && !confirm('Kirim tanpa subjek?')) {
        return;
    }

    sending.value = true;
    await save();

    router.post(
        sendDraft.url(state.draft.id),
        { scheduled_at: scheduledAt ?? null },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => close(),
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Gagal mengirim.'),
            onFinish: () => (sending.value = false),
        },
    );
}

function schedule() {
    if (!scheduleAt.value) {
        return;
    }

    scheduleOpen.value = false;
    send(new Date(scheduleAt.value).toISOString());
}

const presets = computed(() => {
    const now = new Date();
    const tomorrow8 = setMinutes(setHours(addDays(now, 1), 8), 0);
    const monday8 = setMinutes(setHours(nextMonday(now), 8), 0);
    const inHour = startOfHour(addDays(now, 0));
    inHour.setHours(now.getHours() + 2);

    return [
        { label: 'Besok pagi', at: tomorrow8 },
        { label: 'Senin pagi', at: monday8 },
        { label: 'Dua jam lagi', at: inHour },
    ].map((p) => ({
        ...p,
        text: format(p.at, 'EEE d MMM, HH.mm', { locale: localeId }),
    }));
});

async function discard() {
    if (!state.draft || !confirm('Buang draf ini?')) {
        return;
    }

    await json('DELETE', destroyDraft.url(state.draft.id));
    close();
    router.reload({ only: ['mail', 'rows'] });
    toast.success('Draf dibuang.');
}

async function closePanel() {
    await save();
    close();
    router.reload({ only: ['mail', 'rows'] });
}

async function convertToBroadcast() {
    if (!state.draft) {
        return;
    }

    await save();
    const id = state.draft.id;
    close();
    router.post(toBroadcast.url(id));
}
</script>

<template>
    <Teleport to="body">
        <!-- Bilah kecil saat diminimalkan -->
        <div
            v-if="state.open && state.minimized"
            class="surface-navy fixed right-4 bottom-0 z-40 flex w-80 items-center gap-2 rounded-t-lg border border-b-0 px-4 py-2.5 shadow-lg"
        >
            <button
                type="button"
                class="min-w-0 flex-1 truncate text-left text-sm font-medium"
                @click="state.minimized = false"
            >
                {{ form.subject || 'Pesan baru' }}
            </button>
            <button
                type="button"
                class="rounded p-1 hover:bg-accent"
                aria-label="Perbesar"
                @click="state.minimized = false"
            >
                <Maximize2 class="size-4" />
            </button>
            <button
                type="button"
                class="rounded p-1 hover:bg-accent"
                aria-label="Tutup"
                @click="closePanel"
            >
                <X class="size-4" />
            </button>
        </div>

        <section
            v-if="state.open && !state.minimized && state.draft"
            class="fixed inset-0 z-40 flex flex-col bg-card sm:inset-auto sm:right-4 sm:bottom-4 sm:h-[min(640px,calc(100vh-2rem))] sm:w-[min(680px,calc(100vw-2rem))] sm:rounded-xl sm:border sm:shadow-2xl"
            role="dialog"
            aria-label="Tulis email"
            @keydown.ctrl.enter.prevent="send()"
            @keydown.meta.enter.prevent="send()"
            @keydown.esc="state.minimized = true"
        >
            <header
                class="surface-navy flex items-center gap-2 px-4 py-2.5 sm:rounded-t-[11px]"
            >
                <h2 class="flex-1 truncate text-sm font-semibold">
                    {{
                        state.draft.is_reply
                            ? 'Balas'
                            : form.subject || 'Pesan baru'
                    }}
                </h2>
                <span
                    class="tabular text-xs text-muted-foreground"
                    aria-live="polite"
                    >{{ savedLabel }}</span
                >
                <button
                    type="button"
                    class="hidden rounded p-1 hover:bg-accent sm:block"
                    aria-label="Minimalkan"
                    @click="state.minimized = true"
                >
                    <Minus class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 hover:bg-accent"
                    aria-label="Tutup (draf tersimpan)"
                    @click="closePanel"
                >
                    <X class="size-4" />
                </button>
            </header>

            <div
                v-if="(mail?.mailboxes.length ?? 0) > 1"
                class="flex items-center gap-2 border-b px-4 py-1.5"
            >
                <span class="w-12 shrink-0 text-sm text-muted-foreground"
                    >Dari</span
                >
                <select
                    v-model.number="form.mailbox_id"
                    class="h-8 flex-1 rounded-md bg-transparent text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                >
                    <option
                        v-for="m in mail?.mailboxes"
                        :key="m.id"
                        :value="m.id"
                    >
                        {{ m.name }} &lt;{{ m.address }}&gt;
                    </option>
                </select>
            </div>

            <div class="relative">
                <RecipientInput
                    v-model="form.to"
                    label="Kepada"
                    placeholder="Nama, email, atau grup"
                    :autofocus="!state.draft.is_reply"
                />
                <button
                    v-if="!showCc"
                    type="button"
                    class="absolute top-2 right-3 rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
                    @click="showCc = true"
                >
                    Cc/Bcc
                </button>
            </div>
            <template v-if="showCc">
                <RecipientInput v-model="form.cc" label="Cc" />
                <RecipientInput v-model="form.bcc" label="Bcc" />
            </template>

            <div
                v-if="manyRecipients"
                class="flex items-start gap-3 border-b bg-warning-soft px-4 py-2.5 text-sm text-warning"
                role="status"
            >
                <Users class="mt-0.5 size-4 shrink-0" />
                <p class="flex-1 text-pretty">
                    <strong class="font-semibold"
                        >{{ recipientCount }} penerima.</strong
                    >
                    Dengan email biasa, semua alamat saling terlihat. Siaran
                    personal mengirim satu email per penerima dan mengatur kuota
                    otomatis.
                </p>
                <Button
                    size="sm"
                    variant="outline"
                    class="shrink-0 border-warning/40 bg-transparent"
                    @click="convertToBroadcast"
                    >Jadikan siaran</Button
                >
            </div>

            <input
                v-model="form.subject"
                type="text"
                placeholder="Subjek"
                aria-label="Subjek"
                class="h-11 border-b bg-transparent px-4 text-[15px] font-medium outline-none placeholder:font-normal placeholder:text-muted-foreground/70"
            />

            <RichEditor
                v-model="form.body_html"
                class="min-h-0 flex-1"
                min-height="180px"
            />

            <AttachmentPicker
                ref="picker"
                v-model="attachments"
                :message-id="state.draft.id"
                :max-mb="mail?.max_attachments_mb ?? 10"
            >
                <template #default><span /></template>
            </AttachmentPicker>

            <footer class="flex items-center gap-2 border-t px-3 py-2.5">
                <div class="flex">
                    <Button
                        class="rounded-r-none pr-3"
                        :disabled="sending"
                        @click="send()"
                    >
                        <Loader2 v-if="sending" class="animate-spin" />
                        <Send v-else />
                        Kirim
                    </Button>
                    <Popover v-model:open="scheduleOpen">
                        <PopoverTrigger as-child>
                            <Button
                                class="rounded-l-none border-l border-primary-foreground/20 px-2"
                                :disabled="sending"
                                aria-label="Jadwalkan kirim"
                            >
                                <ChevronDown />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent align="start" class="w-72 p-3">
                            <p
                                class="mb-2 flex items-center gap-2 text-sm font-medium"
                            >
                                <CalendarClock class="size-4" /> Jadwalkan kirim
                            </p>
                            <div class="space-y-1">
                                <button
                                    v-for="p in presets"
                                    :key="p.label"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                                    @click="
                                        scheduleOpen = false;
                                        send(p.at.toISOString());
                                    "
                                >
                                    <span>{{ p.label }}</span
                                    ><span
                                        class="text-xs text-muted-foreground"
                                        >{{ p.text }}</span
                                    >
                                </button>
                            </div>
                            <div class="mt-3 border-t pt-3">
                                <input
                                    v-model="scheduleAt"
                                    type="datetime-local"
                                    class="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                                    aria-label="Tanggal dan jam"
                                />
                                <Button
                                    size="sm"
                                    class="mt-2 w-full"
                                    :disabled="!scheduleAt"
                                    @click="schedule"
                                    >Jadwalkan</Button
                                >
                            </div>
                        </PopoverContent>
                    </Popover>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Lampirkan berkas"
                    title="Lampirkan berkas"
                    @click="picker?.open()"
                    ><Paperclip
                /></Button>
                <span class="hidden text-xs text-muted-foreground sm:inline"
                    >Ctrl+Enter untuk kirim</span
                >
                <Button
                    variant="ghost"
                    size="icon"
                    class="ml-auto text-muted-foreground hover:text-destructive"
                    aria-label="Buang draf"
                    title="Buang draf"
                    @click="discard"
                    ><Trash2
                /></Button>
            </footer>
        </section>
    </Teleport>
</template>
