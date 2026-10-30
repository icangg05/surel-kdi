<script setup lang="ts">
import {
    ChevronDown,
    Download,
    FileWarning,
    Forward,
    Loader2,
    Reply,
    ReplyAll,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MessageFrame from '@/components/mail/MessageFrame.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    avatarTone,
    fileSize,
    initials,
    longDate,
    shortDate,
} from '@/lib/format';
import type { MessageItem, Recipient } from '@/types';

const props = defineProps<{
    message: MessageItem;
    expanded: boolean;
    last: boolean;
}>();
const emit = defineEmits<{
    toggle: [];
    reply: [mode: 'reply' | 'reply_all' | 'forward'];
    unschedule: [];
}>();
const pendingStatuses = ['scheduled', 'queued', 'waiting_quota', 'failed'];

const outgoing = computed(() => props.message.direction === 'out');
const sender = computed(() =>
    outgoing.value
        ? props.message.author
            ? `${props.message.author} (Saya)`
            : 'Saya'
        : props.message.from_name || props.message.from_email,
);
const showDetails = ref(false);
const list = (rs: Recipient[]) =>
    rs.map((r) => (r.name ? `${r.name} <${r.email}>` : r.email)).join(', ');
const toLine = computed(() =>
    props.message.to.map((r) => r.name || r.email).join(', '),
);
const canReplyAll = computed(
    () => props.message.to.length + props.message.cc.length > 1,
);

const deliverySummary = computed(() => {
    const d = props.message.deliveries;

    if (!d.length) {
        return null;
    }

    const opened = d.filter((x) =>
        ['opened', 'clicked'].includes(x.status),
    ).length;
    const failed = d.filter((x) =>
        ['hard_bounce', 'blocked', 'failed', 'spam'].includes(x.status),
    ).length;

    return { total: d.length, opened, failed };
});
</script>

<template>
    <article
        :class="[
            'rounded-xl border bg-card transition-shadow',
            expanded ? 'shadow-sm' : '',
        ]"
    >
        <header
            class="flex cursor-pointer items-start gap-3 px-4 py-3 sm:px-5"
            @click="!last && emit('toggle')"
        >
            <div
                :class="[
                    'flex size-9 shrink-0 items-center justify-center rounded text-[13px] font-semibold',
                    outgoing
                        ? 'bg-primary text-primary-foreground'
                        : avatarTone(message.from_email),
                ]"
                aria-hidden="true"
            >
                {{
                    outgoing
                        ? 'KI'
                        : initials(message.from_name || message.from_email)
                }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-semibold">{{ sender }}</span>
                    <span
                        v-if="!outgoing"
                        class="truncate font-mono text-xs text-muted-foreground"
                        >{{ message.from_email }}</span
                    >
                    <span
                        class="tabular ml-auto shrink-0 text-xs text-muted-foreground"
                        :title="longDate(message.date)"
                        >{{
                            expanded
                                ? longDate(message.date)
                                : shortDate(message.date)
                        }}</span
                    >
                </div>
                <p
                    v-if="!expanded"
                    class="mt-0.5 truncate text-sm text-muted-foreground"
                >
                    {{ message.snippet }}
                </p>
                <div v-else class="mt-0.5 text-[13px] text-muted-foreground">
                    <button
                        type="button"
                        class="inline-flex max-w-full items-center gap-1 hover:text-foreground"
                        @click.stop="showDetails = !showDetails"
                    >
                        <span class="truncate">kepada {{ toLine || '—' }}</span>
                        <ChevronDown
                            :class="[
                                'size-3.5 shrink-0 transition-transform',
                                showDetails ? 'rotate-180' : '',
                            ]"
                        />
                    </button>
                    <dl
                        v-if="showDetails"
                        class="mt-2 grid grid-cols-[5.5rem_1fr] gap-x-3 gap-y-1 rounded-md bg-muted/50 p-3 text-xs"
                        @click.stop
                    >
                        <dt>Dari</dt>
                        <dd class="font-mono break-all text-foreground/80">
                            {{
                                message.from_name
                                    ? `${message.from_name} <${message.from_email}>`
                                    : message.from_email
                            }}
                        </dd>
                        <dt>Kepada</dt>
                        <dd class="font-mono break-all text-foreground/80">
                            {{ list(message.to) }}
                        </dd>
                        <template v-if="message.cc.length"
                            ><dt>Cc</dt>
                            <dd class="font-mono break-all text-foreground/80">
                                {{ list(message.cc) }}
                            </dd></template
                        >
                        <template v-if="message.bcc.length"
                            ><dt>Bcc</dt>
                            <dd class="font-mono break-all text-foreground/80">
                                {{ list(message.bcc) }}
                            </dd></template
                        >
                        <template v-if="message.reply_to"
                            ><dt>Balas ke</dt>
                            <dd class="font-mono break-all text-foreground/80">
                                {{ message.reply_to }}
                            </dd></template
                        >
                        <dt>Tanggal</dt>
                        <dd class="text-foreground/80">
                            {{ longDate(message.date) }}
                        </dd>
                        <template v-if="message.spam_score !== null"
                            ><dt>Skor spam</dt>
                            <dd class="tabular text-foreground/80">
                                {{ message.spam_score }}
                            </dd></template
                        >
                    </dl>
                </div>
            </div>
        </header>

        <div v-if="expanded" class="px-4 pb-4 sm:px-5 sm:pl-[4.25rem]">
            <div
                v-if="message.contact"
                class="mb-3 inline-flex items-center gap-2 rounded-md bg-muted/60 px-2.5 py-1 text-xs text-muted-foreground"
            >
                Kontak:
                <span class="font-medium text-foreground">{{
                    message.contact.name
                }}</span>
                <template
                    v-if="
                        message.contact.position || message.contact.organization
                    "
                    >·
                    {{
                        [message.contact.position, message.contact.organization]
                            .filter(Boolean)
                            .join(', ')
                    }}</template
                >
            </div>

            <div
                v-if="outgoing && deliverySummary"
                class="mb-3 rounded-lg border bg-muted/30 p-3"
            >
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <StatusBadge
                        :status="message.status.value"
                        :label="message.status.label"
                    />
                    <span class="text-muted-foreground"
                        >{{ deliverySummary.total }} penerima ·
                        {{ deliverySummary.opened }} membuka<template
                            v-if="deliverySummary.failed"
                        >
                            ·
                            <span class="text-danger"
                                >{{ deliverySummary.failed }} gagal</span
                            ></template
                        ></span
                    >
                </div>
                <ul
                    v-if="
                        message.deliveries.length > 1 ||
                        message.deliveries[0]?.error
                    "
                    class="mt-2 space-y-1"
                >
                    <li
                        v-for="d in message.deliveries"
                        :key="d.email"
                        class="flex items-center gap-2 text-xs"
                    >
                        <span class="min-w-0 flex-1 truncate font-mono">{{
                            d.email
                        }}</span>
                        <span
                            v-if="d.error"
                            class="max-w-[40%] truncate text-danger"
                            :title="d.error"
                            >{{ d.error }}</span
                        >
                        <StatusBadge :status="d.status" :label="d.label" />
                    </li>
                </ul>
                <p v-if="message.error" class="mt-2 text-xs text-danger">
                    {{ message.error }}
                </p>
                <Button
                    v-if="pendingStatuses.includes(message.status.value)"
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    @click="emit('unschedule')"
                >
                    {{
                        message.status.value === 'failed'
                            ? 'Kembalikan ke Draf untuk diperbaiki'
                            : 'Batalkan pengiriman'
                    }}
                </Button>
            </div>

            <div
                v-if="message.spam_score !== null && message.spam_score >= 5"
                class="mb-3 flex items-center gap-2 rounded-md bg-danger-soft px-3 py-2 text-xs text-danger"
            >
                <ShieldAlert class="size-4" /> Brevo menilai email ini
                kemungkinan spam (skor {{ message.spam_score }}). Hati-hati
                dengan tautan & lampirannya.
            </div>

            <MessageFrame
                :src="message.body_url"
                :has-remote-images="message.has_remote_images"
            />

            <div
                v-if="message.attachments.length"
                class="mt-4 flex flex-wrap gap-2"
            >
                <template v-for="a in message.attachments" :key="a.id">
                    <a
                        v-if="a.available"
                        :href="a.url"
                        class="group inline-flex max-w-72 items-center gap-2.5 rounded-lg border bg-background px-3 py-2 text-sm hover:border-primary/40 hover:bg-accent"
                    >
                        <Download
                            class="size-4 shrink-0 text-muted-foreground group-hover:text-primary"
                        />
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                a.filename
                            }}</span>
                            <span
                                class="tabular block text-xs text-muted-foreground"
                                >{{ fileSize(a.size) }}</span
                            >
                        </span>
                    </a>
                    <span
                        v-else
                        class="inline-flex max-w-72 items-center gap-2.5 rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground"
                        :title="a.error ?? 'Sedang diunduh dari Brevo'"
                    >
                        <FileWarning
                            v-if="a.error"
                            class="size-4 shrink-0 text-warning"
                        />
                        <Loader2 v-else class="size-4 shrink-0 animate-spin" />
                        <span class="min-w-0">
                            <span class="block truncate">{{ a.filename }}</span>
                            <span class="block text-xs">{{
                                a.error ? 'Belum bisa diunduh' : 'Mengunduh…'
                            }}</span>
                        </span>
                    </span>
                </template>
            </div>

            <div v-if="last" class="mt-5 flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="emit('reply', 'reply')"
                    ><Reply /> Balas</Button
                >
                <Button
                    v-if="canReplyAll"
                    variant="outline"
                    size="sm"
                    @click="emit('reply', 'reply_all')"
                    ><ReplyAll /> Balas semua</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    @click="emit('reply', 'forward')"
                    ><Forward /> Teruskan</Button
                >
            </div>
        </div>
    </article>
</template>
