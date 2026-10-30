<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{ src: string; hasRemoteImages: boolean }>();

const frame = ref<HTMLIFrameElement | null>(null);
const height = ref(120);
const showImages = ref(false);
const url = computed(() =>
    showImages.value ? `${props.src}?images=1` : props.src,
);

// Tinggi iframe mengikuti isi. Iframe satu origin (sandbox allow-same-origin,
// tanpa allow-scripts) — skrip di isi email tetap tidak bisa jalan.
function measure() {
    const doc = frame.value?.contentDocument;

    if (doc?.body) {
        height.value = Math.max(60, doc.documentElement.scrollHeight);
    }
}

let observer: ResizeObserver | null = null;

function onLoad() {
    measure();
    const body = frame.value?.contentDocument?.body;
    observer?.disconnect();

    if (body) {
        observer = new ResizeObserver(measure);
        observer.observe(body);
        body.querySelectorAll('img').forEach((img) =>
            img.addEventListener('load', measure),
        );
    }
}

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div>
        <div
            v-if="hasRemoteImages && !showImages"
            class="mb-3 flex items-center gap-3 rounded-md border bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
        >
            <ImageOff class="size-4 shrink-0" />
            <span class="flex-1"
                >Gambar dari luar diblokir untuk melindungi privasi (bisa berisi
                pelacak).</span
            >
            <button
                type="button"
                class="font-medium text-foreground underline-offset-2 hover:underline"
                @click="showImages = true"
            >
                Tampilkan gambar
            </button>
        </div>
        <div class="overflow-hidden rounded-lg bg-white dark:p-4">
            <iframe
                ref="frame"
                :src="url"
                title="Isi email"
                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                referrerpolicy="no-referrer"
                loading="lazy"
                class="block w-full border-0"
                :style="{ height: `${height}px` }"
                @load="onLoad"
            />
        </div>
    </div>
</template>
