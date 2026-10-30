<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    BookUser,
    Download,
    FolderPlus,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    Upload,
    UserCheck,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ContactDialog, {
    type ContactRow,
} from '@/components/contacts/ContactDialog.vue';
import GroupDialog from '@/components/contacts/GroupDialog.vue';
import ImportDialog from '@/components/contacts/ImportDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCompose } from '@/composables/useCompose';
import { groupDot, groupTone, number, relative } from '@/lib/format';
import { usePage } from '@inertiajs/vue3';
import contactRoutes from '@/routes/contacts';
import type { GroupItem, Paginated } from '@/types';

const props = defineProps<{
    contacts: Paginated<ContactRow>;
    filters: { q?: string; group?: number; status?: string };
    groups: (GroupItem & { contacts_count: number })[];
    colors: string[];
    totals: { all: number; suppressed: number };
    organizations: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Kontak & Grup', href: contactRoutes.index() }],
    },
});

const page = usePage();
const compose = useCompose();
const q = ref(props.filters.q ?? '');
const selected = ref<Set<number>>(new Set());
const contactOpen = ref(false);
const editing = ref<ContactRow | null>(null);
const groupOpen = ref(false);
const editingGroup = ref<GroupItem | null>(null);
const importOpen = ref(false);

const activeGroup = computed(
    () =>
        props.groups.find((g) => g.id === Number(props.filters.group)) ?? null,
);

function visit(params: Record<string, unknown>) {
    router.get(
        contactRoutes.index.url(),
        { ...props.filters, ...params },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const search = useDebounceFn(
    () => visit({ q: q.value || undefined, page: undefined }),
    300,
);
watch(q, search);
watch(
    () => props.contacts.current_page,
    () => selected.value.clear(),
);

function openContact(c: ContactRow | null) {
    editing.value = c;
    contactOpen.value = true;
}

function openGroup(g: GroupItem | null) {
    editingGroup.value = g;
    groupOpen.value = true;
}

const allSelected = computed(
    () =>
        props.contacts.data.length > 0 &&
        props.contacts.data.every((c) => selected.value.has(c.id)),
);

function toggleAll() {
    selected.value = allSelected.value
        ? new Set()
        : new Set(props.contacts.data.map((c) => c.id));
}

function toggle(id: number) {
    const next = new Set(selected.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selected.value = next;
}

function bulk(action: string, groupId?: number) {
    router.post(
        contactRoutes.bulk.url(),
        { ids: [...selected.value], action, group_id: groupId },
        { preserveScroll: true, onSuccess: () => selected.value.clear() },
    );
}

function destroy(c: ContactRow) {
    router.delete(contactRoutes.destroy.url(c.id), { preserveScroll: true });
}

function emailSelected() {
    const mailboxId = page.props.mail?.current;
    const to = props.contacts.data
        .filter((c) => selected.value.has(c.id))
        .map((c) => ({ email: c.email, name: c.name }));

    if (mailboxId) {
        compose.start({ mailboxId, to });
    }
}

const exportUrl = computed(() =>
    contactRoutes.export.url({
        query: { group: props.filters.group || undefined },
    }),
);
</script>

<template>
    <Head title="Kontak & Grup" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6 lg:flex-row"
    >
        <!-- Grup -->
        <aside class="lg:w-60 lg:shrink-0">
            <div class="flex items-center justify-between lg:mb-2">
                <h2
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Grup
                </h2>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Grup baru"
                    title="Grup baru"
                    @click="openGroup(null)"
                    ><FolderPlus
                /></Button>
            </div>
            <nav
                class="-mx-1 flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible"
                aria-label="Filter grup"
            >
                <button
                    type="button"
                    :class="[
                        'flex shrink-0 items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm',
                        !filters.group && !filters.status
                            ? 'bg-accent font-medium'
                            : 'hover:bg-accent/60',
                    ]"
                    @click="
                        visit({
                            group: undefined,
                            status: undefined,
                            page: undefined,
                        })
                    "
                >
                    <Users class="size-4 text-muted-foreground" />
                    <span class="flex-1 text-left">Semua kontak</span>
                    <span class="tabular text-xs text-muted-foreground">{{
                        number(totals.all)
                    }}</span>
                </button>
                <div
                    v-for="g in groups"
                    :key="g.id"
                    class="group/item flex shrink-0 items-center"
                >
                    <button
                        type="button"
                        :class="[
                            'flex min-w-0 flex-1 items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm',
                            Number(filters.group) === g.id
                                ? 'bg-accent font-medium'
                                : 'hover:bg-accent/60',
                        ]"
                        @click="
                            visit({
                                group: g.id,
                                status: undefined,
                                page: undefined,
                            })
                        "
                    >
                        <span
                            :class="[
                                'ml-1 size-2 shrink-0 rounded-full',
                                groupDot(g.color),
                            ]"
                        />
                        <span class="flex-1 truncate text-left">{{
                            g.name
                        }}</span>
                        <span class="tabular text-xs text-muted-foreground">{{
                            number(g.contacts_count)
                        }}</span>
                    </button>
                    <button
                        type="button"
                        class="hidden rounded p-1 text-muted-foreground opacity-0 group-hover/item:opacity-100 hover:bg-accent lg:block"
                        :aria-label="`Ubah grup ${g.name}`"
                        @click="openGroup(g)"
                    >
                        <Pencil class="size-3.5" />
                    </button>
                </div>
                <button
                    type="button"
                    :class="[
                        'mt-0 flex shrink-0 items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm lg:mt-3',
                        filters.status === 'suppressed'
                            ? 'bg-accent font-medium'
                            : 'hover:bg-accent/60',
                    ]"
                    @click="
                        visit({
                            status: 'suppressed',
                            group: undefined,
                            page: undefined,
                        })
                    "
                >
                    <X class="size-4 text-danger" />
                    <span class="flex-1 text-left">Tidak bisa dikirimi</span>
                    <span class="tabular text-xs text-muted-foreground">{{
                        number(totals.suppressed)
                    }}</span>
                </button>
            </nav>
        </aside>

        <!-- Daftar kontak -->
        <section class="min-w-0 flex-1">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{
                            activeGroup?.name ??
                            (filters.status === 'suppressed'
                                ? 'Tidak bisa dikirimi'
                                : 'Semua kontak')
                        }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="filters.status === 'suppressed'"
                            >Alamat yang pernah ditolak (bounce), menandai spam,
                            atau diblokir. Otomatis tidak ikut siaran.</template
                        >
                        <template v-else-if="activeGroup?.description">{{
                            activeGroup.description
                        }}</template>
                        <template v-else
                            >{{ number(contacts.total) }} kontak</template
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="importOpen = true"
                        ><Upload /> Impor</Button
                    >
                    <Button variant="outline" as-child
                        ><a :href="exportUrl"><Download /> Ekspor</a></Button
                    >
                    <Button @click="openContact(null)"
                        ><Plus /> Tambah kontak</Button
                    >
                </div>
            </div>

            <div class="mt-4 rounded-xl border bg-card">
                <div
                    class="flex flex-wrap items-center gap-2 border-b px-3 py-2"
                >
                    <label class="relative min-w-48 flex-1">
                        <span class="sr-only">Cari kontak</span>
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="q"
                            type="search"
                            placeholder="Cari nama, email, jabatan, instansi"
                            class="h-9 w-full rounded-md bg-transparent pr-3 pl-9 text-sm outline-none placeholder:text-muted-foreground/80"
                        />
                    </label>

                    <template v-if="selected.size">
                        <span class="tabular text-xs font-medium"
                            >{{ selected.size }} dipilih</span
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="emailSelected"
                            >Tulis email</Button
                        >
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child
                                ><Button size="sm" variant="outline"
                                    ><FolderPlus /> Masukkan ke grup</Button
                                ></DropdownMenuTrigger
                            >
                            <DropdownMenuContent>
                                <DropdownMenuLabel
                                    class="text-xs text-muted-foreground"
                                    >Pilih grup</DropdownMenuLabel
                                >
                                <DropdownMenuItem
                                    v-for="g in groups"
                                    :key="g.id"
                                    @select="bulk('add_group', g.id)"
                                    ><span
                                        :class="[
                                            'mr-2 size-2 rounded-full',
                                            groupDot(g.color),
                                        ]"
                                    />{{ g.name }}</DropdownMenuItem
                                >
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button
                            v-if="activeGroup"
                            size="sm"
                            variant="outline"
                            @click="bulk('remove_group', activeGroup.id)"
                            >Keluarkan dari grup</Button
                        >
                        <Button
                            v-if="filters.status === 'suppressed'"
                            size="sm"
                            variant="outline"
                            @click="bulk('reactivate')"
                            ><UserCheck /> Aktifkan lagi</Button
                        >
                        <ConfirmDialog
                            :title="`Hapus ${selected.size} kontak?`"
                            description="Riwayat email tetap ada, tetapi kontak hilang dari grup dan buku alamat."
                            confirm-label="Hapus"
                            destructive
                            @confirm="bulk('delete')"
                        >
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:text-destructive"
                                ><Trash2
                            /></Button>
                        </ConfirmDialog>
                    </template>
                </div>

                <div v-if="contacts.data.length" class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="bg-muted text-left text-xs text-secondary-foreground"
                        >
                            <tr class="border-b">
                                <th class="w-10 py-2.5 pl-4">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        class="size-4 accent-[var(--primary)]"
                                        aria-label="Pilih semua di halaman ini"
                                        @change="toggleAll"
                                    />
                                </th>
                                <th class="py-2.5 pr-3 font-medium">Nama</th>
                                <th class="py-2.5 pr-3 font-medium">
                                    Jabatan & instansi
                                </th>
                                <th class="py-2.5 pr-3 font-medium">Grup</th>
                                <th class="py-2.5 pr-3 font-medium">
                                    Terakhir dikirimi
                                </th>
                                <th class="w-10 py-2.5 pr-3">
                                    <span class="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in contacts.data"
                                :key="c.id"
                                :class="[
                                    'border-b last:border-0',
                                    selected.has(c.id)
                                        ? 'bg-primary/5'
                                        : 'hover:bg-muted/40',
                                ]"
                            >
                                <td class="py-2.5 pl-4">
                                    <input
                                        type="checkbox"
                                        :checked="selected.has(c.id)"
                                        class="size-4 accent-[var(--primary)]"
                                        :aria-label="`Pilih ${c.name}`"
                                        @change="toggle(c.id)"
                                    />
                                </td>
                                <td class="max-w-64 py-2.5 pr-3">
                                    <button
                                        type="button"
                                        class="block max-w-full text-left"
                                        @click="openContact(c)"
                                    >
                                        <span
                                            class="block truncate font-medium hover:underline"
                                            >{{ c.name }}</span
                                        >
                                        <span
                                            class="block truncate font-mono text-xs text-muted-foreground"
                                            >{{ c.email }}</span
                                        >
                                    </button>
                                </td>
                                <td class="max-w-56 py-2.5 pr-3">
                                    <span class="block truncate">{{
                                        c.position || '—'
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ c.organization }}</span
                                    >
                                </td>
                                <td class="py-2.5 pr-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="g in c.groups"
                                            :key="g.id"
                                            :class="[
                                                'rounded-sm px-2 py-0.5 text-xs font-medium',
                                                groupTone(g.color),
                                            ]"
                                            >{{ g.name }}</span
                                        >
                                        <StatusBadge
                                            v-if="c.status !== 'active'"
                                            :status="c.status"
                                            :label="c.status_label"
                                            :title="c.suppressed_reason ?? ''"
                                        />
                                    </div>
                                </td>
                                <td
                                    class="py-2.5 pr-3 text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{
                                        c.last_contacted_at
                                            ? relative(c.last_contacted_at)
                                            : 'Belum pernah'
                                    }}
                                </td>
                                <td class="py-2.5 pr-3">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child
                                            ><Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`Aksi untuk ${c.name}`"
                                                ><MoreHorizontal /></Button
                                        ></DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                @select="openContact(c)"
                                                ><Pencil />
                                                Ubah</DropdownMenuItem
                                            >
                                            <DropdownMenuItem
                                                v-if="page.props.mail?.current"
                                                @select="
                                                    compose.start({
                                                        mailboxId:
                                                            page.props.mail!
                                                                .current!,
                                                        to: [
                                                            {
                                                                email: c.email,
                                                                name: c.name,
                                                            },
                                                        ],
                                                    })
                                                "
                                                >Tulis email</DropdownMenuItem
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="text-destructive"
                                                @select="destroy(c)"
                                                ><Trash2 />
                                                Hapus</DropdownMenuItem
                                            >
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EmptyState
                    v-else
                    :icon="BookUser"
                    :title="
                        filters.q
                            ? 'Tidak ada kontak yang cocok'
                            : 'Belum ada kontak di sini'
                    "
                    :description="
                        filters.q
                            ? 'Coba kata kunci lain.'
                            : 'Tambahkan satu per satu, atau impor dari Excel/CSV sekaligus.'
                    "
                >
                    <div v-if="!filters.q" class="flex gap-2">
                        <Button variant="outline" @click="importOpen = true"
                            ><Upload /> Impor</Button
                        >
                        <Button @click="openContact(null)"
                            ><Plus /> Tambah kontak</Button
                        >
                    </div>
                </EmptyState>

                <div v-if="contacts.last_page > 1" class="border-t px-4 py-2.5">
                    <Pagination :meta="contacts" noun="kontak" />
                </div>
            </div>
        </section>
    </div>

    <ContactDialog
        v-model:open="contactOpen"
        :contact="editing"
        :groups="groups"
        :organizations="organizations"
        :default-group="activeGroup?.id"
    />
    <GroupDialog
        v-model:open="groupOpen"
        :group="editingGroup"
        :colors="colors"
    />
    <ImportDialog v-model:open="importOpen" :groups="groups" />
</template>
