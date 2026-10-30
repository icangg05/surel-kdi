<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { groupDot } from '@/lib/format';
import contactRoutes from '@/routes/contacts';
import type { GroupItem } from '@/types';

export type ContactRow = {
    id: number;
    name: string;
    email: string;
    position: string | null;
    organization: string | null;
    phone: string | null;
    notes: string | null;
    status: string;
    status_label: string;
    suppressed_reason: string | null;
    last_contacted_at: string | null;
    groups: { id: number; name: string; color: string }[];
};

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    contact: ContactRow | null;
    groups: GroupItem[];
    organizations: string[];
    defaultGroup?: number | null;
}>();

const form = useForm({
    name: '',
    email: '',
    position: '',
    organization: '',
    phone: '',
    notes: '',
    group_ids: [] as number[],
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    const c = props.contact;
    form.defaults({
        name: c?.name ?? '',
        email: c?.email ?? '',
        position: c?.position ?? '',
        organization: c?.organization ?? '',
        phone: c?.phone ?? '',
        notes: c?.notes ?? '',
        group_ids: c
            ? c.groups.map((g) => g.id)
            : props.defaultGroup
              ? [props.defaultGroup]
              : [],
    });
    form.reset();
});

function toggleGroup(id: number) {
    form.group_ids = form.group_ids.includes(id)
        ? form.group_ids.filter((g) => g !== id)
        : [...form.group_ids, id];
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.contact) {
        form.put(contactRoutes.update.url(props.contact.id), options);
    } else {
        form.post(contactRoutes.store.url(), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    contact ? 'Ubah kontak' : 'Tambah kontak'
                }}</DialogTitle>
                <DialogDescription
                    >Penerima email dan siaran. Alamat email harus
                    unik.</DialogDescription
                >
            </DialogHeader>
            <form id="contact-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="c-name">Nama</Label>
                        <Input
                            id="c-name"
                            v-model="form.name"
                            v-focus
                            required
                            autocomplete="off"
                            placeholder="mis. Drs. Andi Rahman"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="c-email">Email</Label>
                        <Input
                            id="c-email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="off"
                            class="font-mono text-[13px]"
                            placeholder="nama@instansi.go.id"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-position">Jabatan</Label>
                        <Input
                            id="c-position"
                            v-model="form.position"
                            placeholder="mis. Camat"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-org">Instansi / OPD</Label>
                        <Input
                            id="c-org"
                            v-model="form.organization"
                            list="organizations"
                            placeholder="mis. Kecamatan Poasia"
                        />
                        <datalist id="organizations">
                            <option
                                v-for="o in organizations"
                                :key="o"
                                :value="o"
                            />
                        </datalist>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="c-phone">Telepon / WhatsApp</Label>
                        <Input
                            id="c-phone"
                            v-model="form.phone"
                            inputmode="tel"
                            placeholder="08…"
                        />
                    </div>
                </div>
                <div v-if="groups.length" class="grid gap-2">
                    <Label>Grup</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="g in groups"
                            :key="g.id"
                            type="button"
                            :aria-pressed="form.group_ids.includes(g.id)"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded border px-3 py-1 text-sm transition-colors',
                                form.group_ids.includes(g.id)
                                    ? 'border-primary bg-primary/10 text-foreground'
                                    : 'text-muted-foreground hover:bg-accent',
                            ]"
                            @click="toggleGroup(g.id)"
                        >
                            <span
                                :class="[
                                    'size-2 rounded-full',
                                    groupDot(g.color),
                                ]"
                            />{{ g.name }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-notes">Catatan</Label>
                    <Textarea
                        id="c-notes"
                        v-model="form.notes"
                        rows="2"
                        placeholder="Opsional"
                    />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Batal</Button>
                <Button
                    type="submit"
                    form="contact-form"
                    :disabled="form.processing"
                    >{{
                        contact ? 'Simpan perubahan' : 'Tambah kontak'
                    }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
