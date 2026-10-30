<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
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
import { avatarTone, initials, relative } from '@/lib/format';
import admin from '@/routes/admin';
import type { MailboxSummary } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role: string;
    last_login_at: string | null;
    two_factor: boolean;
    mailbox_ids: number[];
};
const props = defineProps<{ users: UserRow[]; mailboxes: MailboxSummary[] }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            { title: 'Pengguna', href: admin.users.index() },
        ],
    },
});

const me = usePage().props.auth.user;
const open = ref(false);
const editing = ref<UserRow | null>(null);
const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'operator',
    is_active: true,
    mailbox_ids: [] as number[],
});

function edit(u: UserRow | null) {
    editing.value = u;
    form.clearErrors();
    form.defaults({
        name: u?.name ?? '',
        email: u?.email ?? '',
        password: '',
        role: u?.role ?? 'operator',
        is_active: u?.is_active ?? true,
        mailbox_ids: u?.mailbox_ids ?? props.mailboxes.map((m) => m.id),
    });
    form.reset();
    open.value = true;
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    if (editing.value) {
        form.put(admin.users.update.url(editing.value.id), opts);
    } else {
        form.post(admin.users.store.url(), opts);
    }
}

function toggleMailbox(id: number) {
    form.mailbox_ids = form.mailbox_ids.includes(id)
        ? form.mailbox_ids.filter((x) => x !== id)
        : [...form.mailbox_ids, id];
}

const mailboxName = (id: number) =>
    props.mailboxes.find((m) => m.id === id)?.address ?? '';
</script>

<template>
    <Head title="Pengguna" />
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-6">
        <PageHeader
            title="Pengguna"
            description="Akun yang bisa masuk ke Surel Kendari. Operator hanya melihat mailbox yang ditugaskan; admin melihat semua dan mengelola pengaturan."
        >
            <Button @click="edit(null)"><Plus /> Tambah pengguna</Button>
        </PageHeader>

        <div class="divide-y rounded-xl border bg-card">
            <div
                v-for="u in users"
                :key="u.id"
                class="flex flex-wrap items-center gap-3 px-4 py-3"
            >
                <div
                    :class="[
                        'flex size-9 shrink-0 items-center justify-center rounded text-[13px] font-semibold',
                        avatarTone(u.email),
                    ]"
                >
                    {{ initials(u.name) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ u.name }}
                        <span
                            v-if="u.id === me?.id"
                            class="text-xs font-normal text-muted-foreground"
                            >(Anda)</span
                        >
                        <span
                            v-if="u.role === 'admin'"
                            class="rounded-sm bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                            >Admin</span
                        >
                        <ShieldCheck
                            v-if="u.two_factor"
                            class="size-4 text-success"
                            aria-label="2FA aktif"
                        />
                        <StatusBadge
                            v-if="!u.is_active"
                            status="cancelled"
                            label="Nonaktif"
                        />
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        <span class="font-mono">{{ u.email }}</span> ·
                        {{
                            u.role === 'admin'
                                ? 'semua mailbox'
                                : u.mailbox_ids.map(mailboxName).join(', ') ||
                                  'belum ada mailbox'
                        }}
                    </p>
                </div>
                <span class="text-xs text-muted-foreground">{{
                    u.last_login_at
                        ? `Masuk ${relative(u.last_login_at)}`
                        : 'Belum pernah masuk'
                }}</span>
                <div class="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Ubah ${u.name}`"
                        @click="edit(u)"
                        ><Pencil
                    /></Button>
                    <ConfirmDialog
                        v-if="u.id !== me?.id"
                        :title="`Hapus ${u.name}?`"
                        description="Akun dihapus permanen. Email yang ditulisnya tetap ada."
                        confirm-label="Hapus"
                        destructive
                        @confirm="
                            router.delete(admin.users.destroy.url(u.id), {
                                preserveScroll: true,
                            })
                        "
                    >
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground hover:text-destructive"
                            :aria-label="`Hapus ${u.name}`"
                            ><Trash2
                        /></Button>
                    </ConfirmDialog>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Ubah pengguna' : 'Tambah pengguna'
                }}</DialogTitle>
                <DialogDescription
                    >Sampaikan kata sandi awal ke pengguna secara langsung;
                    minta ia menggantinya setelah masuk.</DialogDescription
                >
            </DialogHeader>
            <form id="user-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="u-name">Nama</Label
                    ><Input
                        id="u-name"
                        v-model="form.name"
                        required
                    /><InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="u-email">Email login</Label
                    ><Input
                        id="u-email"
                        v-model="form.email"
                        type="email"
                        required
                        class="font-mono text-[13px]"
                    /><InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="u-pass">{{
                        editing
                            ? 'Kata sandi baru (kosongkan bila tidak diganti)'
                            : 'Kata sandi awal'
                    }}</Label>
                    <Input
                        id="u-pass"
                        v-model="form.password"
                        type="text"
                        autocomplete="new-password"
                        :required="!editing"
                        class="font-mono text-[13px]"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label>Peran</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="r in [
                                {
                                    v: 'operator',
                                    l: 'Operator',
                                    d: 'Memakai mailbox yang ditugaskan',
                                },
                                {
                                    v: 'admin',
                                    l: 'Admin',
                                    d: 'Semua mailbox + pengaturan',
                                },
                            ]"
                            :key="r.v"
                            type="button"
                            :aria-pressed="form.role === r.v"
                            :class="[
                                'rounded-lg border p-3 text-left',
                                form.role === r.v
                                    ? 'border-primary ring-1 ring-primary'
                                    : 'hover:bg-muted/40',
                            ]"
                            @click="form.role = r.v"
                        >
                            <p class="text-sm font-medium">{{ r.l }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ r.d }}
                            </p>
                        </button>
                    </div>
                    <InputError :message="form.errors.role" />
                </div>
                <div v-if="form.role === 'operator'" class="grid gap-2">
                    <Label>Mailbox yang boleh dipakai</Label>
                    <label
                        v-for="m in mailboxes"
                        :key="m.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :checked="form.mailbox_ids.includes(m.id)"
                            class="size-4 accent-[var(--primary)]"
                            @change="toggleMailbox(m.id)"
                        />
                        {{ m.name }}
                        <span class="font-mono text-xs text-muted-foreground">{{
                            m.address
                        }}</span>
                    </label>
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><Switch v-model="form.is_active" /> Akun aktif</label
                >
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Batal</Button>
                <Button
                    type="submit"
                    form="user-form"
                    :disabled="form.processing"
                    >Simpan</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
