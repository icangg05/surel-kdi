<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FileSpreadsheet, Loader2, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { number } from '@/lib/format';
import { json } from '@/lib/http';
import contactRoutes from '@/routes/contacts';
import { preview as importPreview } from '@/routes/contacts/import';
import type { GroupItem } from '@/types';

type Preview = {
    total: number;
    new: number;
    update: number;
    invalid: number;
    duplicate: number;
    rows: {
        line: number;
        name?: string;
        email: string;
        position?: string;
        organization?: string;
        groups?: string;
        status: string;
    }[];
};

const open = defineModel<boolean>('open', { required: true });
defineProps<{ groups: GroupItem[] }>();

const file = ref<File | null>(null);
const preview = ref<Preview | null>(null);
const loading = ref(false);
const error = ref('');
const updateExisting = ref(true);
const groupId = ref<number | ''>('');

watch(open, (v) => {
    if (v) {
        file.value = null;
        preview.value = null;
        error.value = '';
    }
});

const LABELS: Record<string, [string, string]> = {
    new: ['active', 'Baru'],
    update: ['scheduled', 'Diperbarui'],
    invalid: ['failed', 'Email tidak valid'],
    duplicate: ['skipped', 'Duplikat di berkas'],
};

async function choose(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0];

    if (!f) {
        return;
    }

    file.value = f;
    loading.value = true;
    error.value = '';
    const form = new FormData();
    form.append('file', f);

    try {
        preview.value = await json<Preview>('POST', importPreview.url(), form);
    } catch (err) {
        error.value = (err as Error).message;
    } finally {
        loading.value = false;
    }
}

const importable = computed(() =>
    preview.value
        ? preview.value.new + (updateExisting.value ? preview.value.update : 0)
        : 0,
);

function submit() {
    if (!file.value) {
        return;
    }

    router.post(
        contactRoutes.import.url(),
        {
            file: file.value,
            update_existing: updateExisting.value,
            group_id: groupId.value || null,
        },
        {
            forceFormData: true,
            onSuccess: () => (open.value = false),
            onError: (e) =>
                (error.value = Object.values(e)[0] ?? 'Impor gagal.'),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Impor kontak</DialogTitle>
                <DialogDescription>
                    Berkas CSV atau Excel (.xlsx). Baris pertama berisi judul
                    kolom:
                    <span class="font-mono text-xs"
                        >Nama, Email, Jabatan, Instansi/OPD, Telepon, Grup</span
                    >. Kolom Grup boleh berisi beberapa nama dipisah koma; grup
                    baru dibuat otomatis.
                </DialogDescription>
            </DialogHeader>

            <label
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors hover:border-primary/50 hover:bg-primary/5"
            >
                <input
                    type="file"
                    accept=".csv,.xlsx,text/csv"
                    class="sr-only"
                    @change="choose"
                />
                <Loader2
                    v-if="loading"
                    class="size-6 animate-spin text-muted-foreground"
                />
                <FileSpreadsheet v-else-if="file" class="size-6 text-primary" />
                <Upload v-else class="size-6 text-muted-foreground" />
                <span class="text-sm font-medium">{{
                    file ? file.name : 'Pilih berkas CSV/XLSX'
                }}</span>
                <span class="text-xs text-muted-foreground">{{
                    file
                        ? 'Klik untuk mengganti'
                        : 'Maksimal 5 MB, hingga 5.000 baris'
                }}</span>
            </label>

            <p v-if="error" class="text-sm text-danger">{{ error }}</p>

            <template v-if="preview">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div
                        v-for="k in [
                            'new',
                            'update',
                            'invalid',
                            'duplicate',
                        ] as const"
                        :key="k"
                        class="rounded-lg border px-3 py-2"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ LABELS[k]![1] }}
                        </p>
                        <p class="tabular text-lg font-semibold">
                            {{ number(preview[k]) }}
                        </p>
                    </div>
                </div>
                <div class="max-h-64 overflow-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="sticky top-0 bg-muted text-left text-xs text-secondary-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">Baris</th>
                                <th class="px-3 py-2 font-medium">Nama</th>
                                <th class="px-3 py-2 font-medium">Email</th>
                                <th class="px-3 py-2 font-medium">Instansi</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in preview.rows"
                                :key="r.line"
                                class="border-t"
                            >
                                <td
                                    class="tabular px-3 py-1.5 text-muted-foreground"
                                >
                                    {{ r.line }}
                                </td>
                                <td class="px-3 py-1.5">{{ r.name }}</td>
                                <td class="px-3 py-1.5 font-mono text-xs">
                                    {{ r.email || '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-muted-foreground">
                                    {{ r.organization }}
                                </td>
                                <td class="px-3 py-1.5">
                                    <StatusBadge
                                        :status="LABELS[r.status]![0]"
                                        :label="LABELS[r.status]![1]"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <label class="flex items-center gap-2 text-sm"
                        ><Switch v-model="updateExisting" /> Perbarui data
                        kontak yang sudah ada</label
                    >
                    <label class="flex items-center gap-2 text-sm">
                        Masukkan semua ke grup
                        <select
                            v-model="groupId"
                            class="h-9 rounded-md border bg-background px-2 text-sm"
                        >
                            <option value="">— tidak —</option>
                            <option
                                v-for="g in groups"
                                :key="g.id"
                                :value="g.id"
                            >
                                {{ g.name }}
                            </option>
                        </select>
                    </label>
                </div>
            </template>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Batal</Button>
                <Button :disabled="!preview || importable === 0" @click="submit"
                    >Impor
                    {{ preview ? number(importable) : '' }} kontak</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
