<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { number } from '@/lib/format';

const page = usePage();
const quota = computed(() => page.props.mail?.quota);
const pct = computed(() =>
    quota.value && quota.value.limit > 0
        ? Math.min(100, (quota.value.used / quota.value.limit) * 100)
        : 0,
);
const tone = computed(() =>
    pct.value >= 100
        ? 'bg-danger'
        : pct.value >= 80
          ? 'bg-warning'
          : 'bg-sidebar-primary',
);
const resets = computed(() =>
    quota.value
        ? new Date(quota.value.resets_at).toLocaleTimeString('id-ID', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '',
);
</script>

<template>
    <div
        v-if="quota"
        class="rounded-lg border border-sidebar-border bg-sidebar-accent/40 px-3 py-2.5 group-data-[collapsible=icon]:hidden"
    >
        <div class="flex items-baseline justify-between text-xs">
            <span class="font-medium text-sidebar-foreground"
                >Kuota hari ini</span
            >
            <span class="tabular text-muted-foreground"
                ><span class="font-semibold text-sidebar-foreground">{{
                    number(quota.used)
                }}</span>
                / {{ number(quota.limit) }}</span
            >
        </div>
        <div
            class="mt-2 h-1.5 overflow-hidden rounded-sm bg-sidebar"
            role="progressbar"
            :aria-valuenow="quota.used"
            :aria-valuemax="quota.limit"
            aria-label="Pemakaian kuota Brevo hari ini"
        >
            <div
                :class="['h-full rounded-sm transition-all', tone]"
                :style="{ width: `${pct}%` }"
            />
        </div>
        <p class="mt-1.5 text-[11px] text-muted-foreground">
            <template v-if="quota.remaining > 0"
                >Sisa {{ number(quota.remaining) }} email · reset
                {{ resets }}</template
            >
            <template v-else
                >Habis — antrean lanjut pukul {{ resets }}</template
            >
        </p>
    </div>
</template>
