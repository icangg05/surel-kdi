<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    Inbox,
    Mail,
    MailOpen,
    PenSquare,
    Megaphone,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import MessageCard from '@/components/mail/MessageCard.vue';
import ThreadRow from '@/components/mail/ThreadRow.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCompose } from '@/composables/useCompose';
import { useShortcuts } from '@/composables/useShortcuts';
import broadcastRoutes from '@/routes/broadcasts';
import mailRoutes from '@/routes/mail';
import { unschedule as unscheduleMessage } from '@/routes/messages';
import threadRoutes from '@/routes/threads';
import type { ListRow, Paginated, ThreadDetail } from '@/types';

defineOptions({ layout: { bare: true } });

const props = defineProps<{
    mailbox: { id: number; name: string; address: string };
    folder: string;
    search: string;
    rows: Paginated<ListRow>;
    thread: ThreadDetail | null;
}>();

const page = usePage();
const compose = useCompose();

const TITLES: Record<string, string> = {
    inbox: 'Kotak Masuk',
    starred: 'Berbintang',
    sent: 'Terkirim',
    drafts: 'Draf',
    scheduled: 'Antrean & Terjadwal',
    archive: 'Arsip',
    spam: 'Spam',
    trash: 'Sampah',
};
const EMPTY: Record<string, string> = {
    inbox: 'Balasan dari penerima akan muncul di sini.',
    starred: 'Beri bintang pada utas penting agar mudah ditemukan.',
    sent: 'Email yang sudah Anda kirim akan muncul di sini.',
    drafts: 'Draf tersimpan otomatis saat Anda menulis.',
    scheduled:
        'Email terjadwal, dalam antrean, atau menunggu kuota muncul di sini.',
    archive: 'Utas yang diarsipkan keluar dari Kotak Masuk tanpa dihapus.',
    spam: 'Email dengan skor spam tinggi dari Brevo dipindahkan ke sini.',
    trash: 'Utas di Sampah dihapus permanen setelah 30 hari.',
};

const title = computed(() => TITLES[props.folder] ?? 'Kotak Surat');
const q = ref(props.search);
const searchInput = ref<HTMLInputElement | null>(null);
const selected = ref<Set<number>>(new Set());
const expanded = ref<Set<number>>(new Set());

watch(
    () => props.folder,
    () => selected.value.clear(),
);

watch(
    () => props.thread?.id,
    () => {
        const last = props.thread?.messages.at(-1);
        expanded.value = new Set(last ? [last.id] : []);
    },
    { immediate: true },
);

const listUrl = (extra: Record<string, unknown> = {}) =>
    mailRoutes.index.url(
        { mailbox: props.mailbox.id, folder: props.folder },
        { query: { q: props.search || undefined, ...extra } },
    );

const rowHref = (row: ListRow) => {
    const threadId =
        row.kind === 'thread'
            ? row.id
            : row.kind === 'outgoing'
              ? row.thread_id
              : null;

    return threadId
        ? mailRoutes.index.url(
              {
                  mailbox: props.mailbox.id,
                  folder: props.folder,
                  thread: threadId,
              },
              {
                  query: {
                      q: props.search || undefined,
                      page:
                          props.rows.current_page > 1
                              ? props.rows.current_page
                              : undefined,
                  },
              },
          )
        : undefined;
};

const isActive = (row: ListRow) =>
    !!props.thread &&
    (row.kind === 'thread'
        ? row.id === props.thread.id
        : row.thread_id === props.thread.id);

function submitSearch() {
    router.get(
        mailRoutes.index.url({
            mailbox: props.mailbox.id,
            folder: props.folder,
        }),
        { q: q.value || undefined },
        { preserveState: true, replace: true },
    );
}

function clearSearch() {
    q.value = '';
    submitSearch();
}

const reloadOpts = {
    preserveScroll: true,
    preserveState: true,
    only: ['rows', 'thread', 'mail'],
};

function update(
    threadId: number,
    data: { starred?: boolean; read?: boolean; folder?: string },
    after?: () => void,
) {
    router.patch(threadRoutes.update.url(threadId), data, {
        ...reloadOpts,
        onSuccess: after,
    });
}

function move(threadIds: number[], folder: string, label: string) {
    const previous = props.folder;
    const closeReader = props.thread && threadIds.includes(props.thread.id);

    router.post(
        threadRoutes.bulk.url(),
        { ids: threadIds, folder },
        {
            ...reloadOpts,
            onSuccess: () => {
                selected.value.clear();

                if (closeReader) {
                    router.visit(listUrl(), {
                        preserveScroll: true,
                        preserveState: true,
                    });
                }

                const undoFolder = [
                    'inbox',
                    'archive',
                    'spam',
                    'trash',
                ].includes(previous)
                    ? previous
                    : null;
                toast.success(
                    label,
                    undoFolder
                        ? {
                              action: {
                                  label: 'Urungkan',
                                  onClick: () =>
                                      router.post(
                                          threadRoutes.bulk.url(),
                                          {
                                              ids: threadIds,
                                              folder: undoFolder,
                                          },
                                          reloadOpts,
                                      ),
                              },
                          }
                        : undefined,
                );
            },
        },
    );
}

function markRead(threadIds: number[], read: boolean) {
    router.post(
        threadRoutes.bulk.url(),
        { ids: threadIds, read },
        { ...reloadOpts, onSuccess: () => selected.value.clear() },
    );
}

function toggleStar(row: ListRow) {
    update(row.id, { starred: !row.starred });
}

function destroyThread() {
    if (props.thread) {
        router.delete(threadRoutes.destroy.url(props.thread.id), {
            preserveScroll: true,
        });
    }
}

function openRow(row: ListRow) {
    if (row.kind === 'draft') {
        compose.resume(row.id);
    }
}

function reply(mode: 'reply' | 'reply_all' | 'forward') {
    const source = props.thread?.messages.at(-1);

    if (source) {
        compose.start({
            mailboxId: props.mailbox.id,
            mode,
            sourceId: source.id,
        });
    }
}

function unschedule(messageId: number) {
    router.post(unscheduleMessage.url(messageId), {}, reloadOpts);
}

function toggleExpanded(id: number) {
    const next = new Set(expanded.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expanded.value = next;
}

function toggleSelect(id: number) {
    const next = new Set(selected.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selected.value = next;
}

const threadRows = computed(() =>
    props.rows.data.filter((r) => r.kind === 'thread'),
);
const allSelected = computed(
    () =>
        threadRows.value.length > 0 &&
        threadRows.value.every((r) => selected.value.has(r.id)),
);

function toggleAll() {
    selected.value = allSelected.value
        ? new Set()
        : new Set(threadRows.value.map((r) => r.id));
}

const selectedIds = computed(() => [...selected.value]);
const currentFolder = computed(() => props.thread?.folder ?? null);

function step(delta: number) {
    const rows = props.rows.data;
    const index = rows.findIndex(isActive);
    const next = rows[index === -1 ? 0 : index + delta];
    const href = next ? rowHref(next) : undefined;

    if (href) {
        router.visit(href, {
            preserveScroll: true,
            preserveState: true,
            only: ['thread', 'mail'],
        });
    }
}

useShortcuts({
    j: () => step(1),
    k: () => step(-1),
    '/': () => searchInput.value?.focus(),
    r: () => props.thread && reply('reply'),
    a: () => props.thread && reply('reply_all'),
    f: () => props.thread && reply('forward'),
    e: () =>
        props.thread &&
        currentFolder.value !== 'archive' &&
        move([props.thread.id], 'archive', 'Utas diarsipkan.'),
    '#': () =>
        props.thread &&
        currentFolder.value !== 'trash' &&
        move([props.thread.id], 'trash', 'Utas dipindah ke Sampah.'),
    s: () =>
        props.thread &&
        update(props.thread.id, { starred: !props.thread.is_starred }),
    u: () =>
        props.thread &&
        router.visit(listUrl(), { preserveScroll: true, preserveState: true }),
});

const unreadLabel = computed(() => {
    const n = page.props.mail?.counts?.inbox ?? 0;

    return props.folder === 'inbox' && n > 0
        ? `${n} belum dibaca`
        : `${props.rows.total} utas`;
});
</script>

<template>
    <Head :title="title" />

    <div class="flex h-svh min-h-0 overflow-hidden">
        <!-- Daftar -->
        <section
            :class="[
                'flex min-h-0 w-full flex-col border-r bg-background md:w-[400px] md:shrink-0 lg:w-[430px]',
                thread ? 'hidden md:flex' : 'flex',
            ]"
            aria-label="Daftar email"
        >
            <div class="flex items-center gap-2 px-4 pt-3 pb-2">
                <SidebarTrigger class="-ml-1" />
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="tabular truncate text-xs text-muted-foreground">
                        {{ unreadLabel }}
                    </p>
                </div>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Muat ulang"
                            @click="router.reload({ only: ['rows', 'mail'] })"
                            ><RefreshCw
                        /></Button>
                    </TooltipTrigger>
                    <TooltipContent>Muat ulang</TooltipContent>
                </Tooltip>
            </div>

            <form
                class="px-4 pb-3"
                role="search"
                @submit.prevent="submitSearch"
            >
                <label class="relative block">
                    <span class="sr-only">Cari email</span>
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        ref="searchInput"
                        v-model="q"
                        type="search"
                        placeholder="Cari subjek, isi, atau pengirim"
                        class="h-9 w-full rounded-lg border bg-muted/40 pr-14 pl-9 text-sm outline-none placeholder:text-muted-foreground/80 focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/20"
                    />
                    <button
                        v-if="search"
                        type="button"
                        class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-accent"
                        aria-label="Hapus pencarian"
                        @click="clearSearch"
                    >
                        <X class="size-3.5" />
                    </button>
                    <kbd
                        v-else
                        class="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 rounded border px-1.5 font-mono text-[10px] text-muted-foreground"
                        >/</kbd
                    >
                </label>
            </form>

            <div
                v-if="threadRows.length"
                class="flex h-10 items-center gap-1 border-y bg-muted/30 px-4"
            >
                <input
                    type="checkbox"
                    :checked="allSelected"
                    :indeterminate="selected.size > 0 && !allSelected"
                    class="size-4 rounded accent-[var(--primary)]"
                    aria-label="Pilih semua"
                    @change="toggleAll"
                />
                <template v-if="selected.size">
                    <span class="tabular mr-auto ml-2 text-xs font-medium"
                        >{{ selected.size }} dipilih</span
                    >
                    <Button
                        v-if="folder !== 'archive'"
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Arsipkan"
                        title="Arsipkan"
                        @click="
                            move(
                                selectedIds,
                                'archive',
                                `${selected.size} utas diarsipkan.`,
                            )
                        "
                        ><Archive
                    /></Button>
                    <Button
                        v-if="folder !== 'inbox'"
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Pindah ke Kotak Masuk"
                        title="Pindah ke Kotak Masuk"
                        @click="
                            move(
                                selectedIds,
                                'inbox',
                                'Dipindah ke Kotak Masuk.',
                            )
                        "
                        ><Inbox
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Tandai dibaca"
                        title="Tandai dibaca"
                        @click="markRead(selectedIds, true)"
                        ><MailOpen
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Tandai belum dibaca"
                        title="Tandai belum dibaca"
                        @click="markRead(selectedIds, false)"
                        ><Mail
                    /></Button>
                    <Button
                        v-if="folder !== 'trash'"
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Pindah ke Sampah"
                        title="Pindah ke Sampah"
                        @click="
                            move(
                                selectedIds,
                                'trash',
                                `${selected.size} utas dipindah ke Sampah.`,
                            )
                        "
                        ><Trash2
                    /></Button>
                </template>
                <span v-else class="ml-2 text-xs text-muted-foreground"
                    >Pilih untuk aksi massal</span
                >
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <template v-if="rows.data.length">
                    <ThreadRow
                        v-for="row in rows.data"
                        :key="row.key"
                        :row="row"
                        :href="rowHref(row)"
                        :active="isActive(row)"
                        :selected="
                            selected.has(row.id) && row.kind === 'thread'
                        "
                        @toggle-select="toggleSelect(row.id)"
                        @toggle-star="toggleStar(row)"
                        @open="openRow(row)"
                    />
                </template>
                <EmptyState
                    v-else
                    :icon="search ? Search : Inbox"
                    :title="
                        search
                            ? `Tidak ada hasil untuk “${search}”`
                            : `${title} kosong`
                    "
                    :description="
                        search
                            ? 'Coba kata kunci lain atau periksa folder lain.'
                            : EMPTY[folder]
                    "
                />
            </div>

            <button
                type="button"
                class="fixed right-4 bottom-5 z-30 flex items-center gap-2 rounded bg-gold px-5 py-3.5 text-sm font-semibold text-gold-foreground shadow-lg md:hidden"
                aria-label="Tulis email"
                @click="compose.start({ mailboxId: mailbox.id })"
            >
                <PenSquare class="size-4" /> Tulis
            </button>

            <div v-if="rows.last_page > 1" class="border-t px-4 py-2">
                <Pagination :meta="rows" noun="utas" />
            </div>
        </section>

        <!-- Panel baca -->
        <section
            :class="[
                'min-h-0 flex-1 flex-col',
                thread ? 'flex' : 'hidden md:flex',
            ]"
            aria-label="Isi email"
        >
            <template v-if="thread">
                <div
                    class="flex items-center gap-1 border-b bg-background px-3 py-2 sm:px-4"
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="md:hidden"
                        aria-label="Kembali ke daftar"
                        @click="
                            router.visit(listUrl(), {
                                preserveScroll: true,
                                preserveState: true,
                            })
                        "
                        ><ArrowLeft
                    /></Button>

                    <Button
                        v-if="thread.folder !== 'archive'"
                        variant="ghost"
                        size="sm"
                        @click="
                            move([thread.id], 'archive', 'Utas diarsipkan.')
                        "
                        ><Archive />
                        <span class="hidden lg:inline">Arsipkan</span></Button
                    >
                    <Button
                        v-if="thread.folder && thread.folder !== 'inbox'"
                        variant="ghost"
                        size="sm"
                        @click="
                            move(
                                [thread.id],
                                'inbox',
                                'Dipindah ke Kotak Masuk.',
                            )
                        "
                        ><ArchiveRestore />
                        <span class="hidden lg:inline"
                            >Ke Kotak Masuk</span
                        ></Button
                    >
                    <Button
                        v-if="thread.folder === 'spam'"
                        variant="ghost"
                        size="sm"
                        @click="
                            move([thread.id], 'inbox', 'Ditandai bukan spam.')
                        "
                        ><ShieldCheck />
                        <span class="hidden lg:inline">Bukan spam</span></Button
                    >
                    <Button
                        v-else-if="thread.folder"
                        variant="ghost"
                        size="icon-sm"
                        title="Laporkan spam"
                        aria-label="Laporkan spam"
                        @click="move([thread.id], 'spam', 'Dipindah ke Spam.')"
                        ><ShieldAlert
                    /></Button>
                    <Button
                        v-if="thread.folder !== 'trash'"
                        variant="ghost"
                        size="icon-sm"
                        title="Pindah ke Sampah (#)"
                        aria-label="Pindah ke Sampah"
                        @click="
                            move(
                                [thread.id],
                                'trash',
                                'Utas dipindah ke Sampah.',
                            )
                        "
                        ><Trash2
                    /></Button>
                    <ConfirmDialog
                        v-if="
                            thread.folder === 'trash' ||
                            thread.folder === 'spam'
                        "
                        title="Hapus permanen utas ini?"
                        description="Semua email dan lampiran di utas ini dihapus dan tidak bisa dipulihkan."
                        confirm-label="Hapus permanen"
                        destructive
                        @confirm="destroyThread"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            ><Trash2 /> Hapus permanen</Button
                        >
                    </ConfirmDialog>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Tandai belum dibaca"
                        aria-label="Tandai belum dibaca"
                        @click="markRead([thread.id], false)"
                        ><Mail
                    /></Button>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="ml-auto"
                        :aria-pressed="thread.is_starred"
                        :title="
                            thread.is_starred
                                ? 'Hapus bintang (s)'
                                : 'Beri bintang (s)'
                        "
                        :aria-label="
                            thread.is_starred ? 'Hapus bintang' : 'Beri bintang'
                        "
                        @click="
                            update(thread.id, { starred: !thread.is_starred })
                        "
                    >
                        <Star
                            :class="
                                thread.is_starred
                                    ? 'fill-gold text-gold-strong'
                                    : ''
                            "
                        />
                    </Button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div class="mx-auto max-w-4xl px-3 py-5 sm:px-6">
                        <div class="mb-4 px-1">
                            <h2
                                class="text-xl leading-snug font-semibold tracking-tight text-balance"
                            >
                                {{ thread.subject }}
                            </h2>
                            <a
                                v-if="thread.broadcast"
                                :href="
                                    broadcastRoutes.show.url(
                                        thread.broadcast.id,
                                    )
                                "
                                class="mt-2 inline-flex items-center gap-1.5 rounded-sm bg-info-soft px-2.5 py-0.5 text-xs font-medium text-info hover:underline"
                            >
                                <Megaphone class="size-3.5" /> Balasan atas
                                siaran “{{ thread.broadcast.subject }}”
                            </a>
                        </div>
                        <div class="space-y-3">
                            <MessageCard
                                v-for="(m, i) in thread.messages"
                                :key="m.id"
                                :message="m"
                                :expanded="expanded.has(m.id)"
                                :last="i === thread.messages.length - 1"
                                @toggle="toggleExpanded(m.id)"
                                @reply="reply"
                                @unschedule="unschedule(m.id)"
                            />
                        </div>
                        <p
                            class="mt-6 text-center text-xs text-muted-foreground"
                        >
                            Pintasan:
                            <kbd class="rounded border px-1 font-mono">r</kbd>
                            balas ·
                            <kbd class="rounded border px-1 font-mono">a</kbd>
                            balas semua ·
                            <kbd class="rounded border px-1 font-mono">f</kbd>
                            teruskan ·
                            <kbd class="rounded border px-1 font-mono">e</kbd>
                            arsip ·
                            <kbd class="rounded border px-1 font-mono">j</kbd
                            >/<kbd class="rounded border px-1 font-mono">k</kbd>
                            utas berikut/sebelum
                        </p>
                    </div>
                </div>
            </template>

            <EmptyState
                v-else
                :icon="MailOpen"
                title="Pilih email untuk dibaca"
                description="Klik utas di sebelah kiri, atau tekan j untuk membuka yang teratas."
                class="m-auto"
            />
        </section>
    </div>
</template>
