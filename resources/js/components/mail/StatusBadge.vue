<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ status: string; label: string; class?: string }>();

// Satu peta warna untuk semua status (email, pengiriman, siaran).
const TONES: Record<string, string> = {
    delivered: 'success',
    opened: 'info',
    clicked: 'info',
    sent: 'neutral',
    completed: 'success',
    received: 'neutral',
    queued: 'neutral',
    pending: 'neutral',
    sending: 'info',
    scheduled: 'info',
    waiting_quota: 'warning',
    soft_bounce: 'warning',
    paused: 'warning',
    draft: 'neutral',
    hard_bounce: 'danger',
    blocked: 'danger',
    spam: 'danger',
    failed: 'danger',
    cancelled: 'muted',
    skipped: 'muted',
    active: 'success',
    bounced: 'danger',
    complained: 'danger',
};

const CLASSES: Record<string, string> = {
    success: 'bg-success-soft text-success',
    info: 'bg-info-soft text-info',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    // Netral abu-biru agar tak sama dengan info (biru); dibatalkan/dilewati hanya bergaris.
    neutral: 'bg-muted text-muted-foreground',
    muted: 'text-muted-foreground ring-1 ring-border ring-inset',
};

const DOTS: Record<string, string> = {
    success: 'bg-success',
    info: 'bg-info',
    warning: 'bg-warning',
    danger: 'bg-danger',
    neutral: 'bg-muted-foreground/60',
    muted: 'bg-muted-foreground/40',
};

const tone = computed(() => TONES[props.status] ?? 'neutral');
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                CLASSES[tone],
                props.class,
            )
        "
    >
        <span
            :class="[
                'size-1.5 rounded-full',
                DOTS[tone],
                status === 'sending' ? 'animate-pulse' : '',
            ]"
        />
        {{ label }}
    </span>
</template>
