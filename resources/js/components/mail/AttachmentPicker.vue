<script setup lang="ts">
import { File as FileIcon, Loader2, Paperclip, X } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { fileSize } from '@/lib/format';
import { json } from '@/lib/http';
import {
    destroy as destroyAttachment,
    store as storeAttachment,
} from '@/routes/attachments';
import type { AttachmentItem } from '@/types';

const model = defineModel<AttachmentItem[]>({ required: true });
const props = defineProps<{
    messageId?: number;
    broadcastId?: number;
    maxMb: number;
}>();

const uploading = ref<string[]>([]);
const picker = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

async function upload(files: FileList | File[]) {
    for (const file of Array.from(files)) {
        const total = model.value.reduce((s, a) => s + a.size, 0) + file.size;

        if (total > props.maxMb * 1024 * 1024) {
            toast.error(
                `${file.name}: total lampiran melebihi ${props.maxMb} MB.`,
            );
            continue;
        }

        uploading.value.push(file.name);
        const form = new FormData();
        form.append('file', file);

        if (props.messageId) {
            form.append('message_id', String(props.messageId));
        }

        if (props.broadcastId) {
            form.append('broadcast_id', String(props.broadcastId));
        }

        try {
            model.value = [
                ...model.value,
                await json<AttachmentItem>('POST', storeAttachment.url(), form),
            ];
        } catch (e) {
            toast.error(`${file.name}: ${(e as Error).message}`);
        } finally {
            uploading.value = uploading.value.filter((n) => n !== file.name);
        }
    }
}

async function remove(a: AttachmentItem) {
    await json('DELETE', destroyAttachment.url(a.id));
    model.value = model.value.filter((x) => x.id !== a.id);
}

function onDrop(e: DragEvent) {
    dragging.value = false;

    if (e.dataTransfer?.files.length) {
        upload(e.dataTransfer.files);
    }
}

defineExpose({ open: () => picker.value?.click(), upload });
</script>

<template>
    <div
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
    >
        <input
            ref="picker"
            type="file"
            multiple
            class="hidden"
            @change="
                (e) => {
                    upload((e.target as HTMLInputElement).files ?? []);
                    (e.target as HTMLInputElement).value = '';
                }
            "
        />
        <div
            v-if="dragging"
            class="m-3 flex items-center justify-center rounded-lg border-2 border-dashed border-primary/50 bg-primary/5 py-6 text-sm text-primary"
        >
            Lepaskan berkas untuk melampirkan
        </div>
        <div
            v-if="model.length || uploading.length"
            class="flex flex-wrap gap-2 px-4 py-2"
        >
            <span
                v-for="a in model"
                :key="a.id"
                class="inline-flex max-w-64 items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-2 text-xs"
            >
                <FileIcon class="size-3.5 shrink-0 text-muted-foreground" />
                <span class="truncate font-medium">{{ a.filename }}</span>
                <span class="tabular shrink-0 text-muted-foreground">{{
                    fileSize(a.size)
                }}</span>
                <button
                    type="button"
                    class="rounded p-0.5 hover:bg-foreground/10"
                    :aria-label="`Hapus lampiran ${a.filename}`"
                    @click="remove(a)"
                >
                    <X class="size-3" />
                </button>
            </span>
            <span
                v-for="name in uploading"
                :key="name"
                class="inline-flex items-center gap-2 rounded-md border border-dashed px-2 py-1 text-xs text-muted-foreground"
            >
                <Loader2 class="size-3.5 animate-spin" /> {{ name }}
            </span>
        </div>
        <slot :open="() => picker?.click()">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                @click="picker?.click()"
            >
                <Paperclip class="size-4" /> Lampirkan berkas
            </button>
        </slot>
    </div>
</template>
