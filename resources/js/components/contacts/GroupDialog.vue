<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { groupDot } from '@/lib/format';
import groupRoutes from '@/routes/groups';
import type { GroupItem } from '@/types';

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{ group: GroupItem | null; colors: string[] }>();
const form = useForm({ name: '', description: '', color: 'blue' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.defaults({
            name: props.group?.name ?? '',
            description: props.group?.description ?? '',
            color: props.group?.color ?? 'blue',
        });
        form.reset();
    }
});

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.group) {
        form.put(groupRoutes.update.url(props.group.id), options);
    } else {
        form.post(groupRoutes.store.url(), options);
    }
}

function remove() {
    if (
        props.group &&
        confirm(
            `Hapus grup "${props.group.name}"? Kontaknya tidak ikut terhapus.`,
        )
    ) {
        router.delete(groupRoutes.destroy.url(props.group.id), {
            onSuccess: () => (open.value = false),
        });
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader
                ><DialogTitle>{{
                    group ? 'Ubah grup' : 'Grup baru'
                }}</DialogTitle></DialogHeader
            >
            <form id="group-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="g-name">Nama grup</Label>
                    <Input
                        id="g-name"
                        v-model="form.name"
                        v-focus
                        required
                        placeholder="mis. Kepala OPD"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="g-desc">Keterangan</Label>
                    <Input
                        id="g-desc"
                        v-model="form.description"
                        placeholder="Opsional"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Warna penanda</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="c in colors"
                            :key="c"
                            type="button"
                            :aria-label="c"
                            :aria-pressed="form.color === c"
                            :class="[
                                'flex size-8 items-center justify-center rounded border-2',
                                form.color === c
                                    ? 'border-foreground'
                                    : 'border-transparent',
                            ]"
                            @click="form.color = c"
                        >
                            <span :class="['size-5 rounded-sm', groupDot(c)]" />
                        </button>
                    </div>
                </div>
            </form>
            <DialogFooter class="gap-2">
                <Button
                    v-if="group"
                    variant="ghost"
                    class="mr-auto text-destructive hover:text-destructive"
                    @click="remove"
                    >Hapus grup</Button
                >
                <Button variant="outline" @click="open = false">Batal</Button>
                <Button
                    type="submit"
                    form="group-form"
                    :disabled="form.processing"
                    >Simpan</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
