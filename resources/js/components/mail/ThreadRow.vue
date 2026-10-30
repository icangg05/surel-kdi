<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertCircle, Megaphone, Paperclip, Star } from '@lucide/vue';
import StatusBadge from '@/components/mail/StatusBadge.vue';
import { shortDate } from '@/lib/format';
import type { ListRow } from '@/types';

defineProps<{
    row: ListRow;
    href?: string;
    active: boolean;
    selected: boolean;
}>();
const emit = defineEmits<{ toggleSelect: []; toggleStar: []; open: [] }>();
</script>

<template>
    <div
        :class="[
            'group relative flex gap-3 border-b px-4 py-3 transition-colors',
            active
                ? 'bg-accent'
                : selected
                  ? 'bg-primary/5'
                  : 'hover:bg-muted/60',
        ]"
        :data-active="active || undefined"
    >
        <span
            v-if="row.unread"
            class="absolute top-0 bottom-0 left-0 w-[3px] bg-unread"
            aria-hidden="true"
        />
        <div class="relative z-10 flex flex-col items-center gap-2 pt-0.5">
            <input
                v-if="row.kind === 'thread'"
                type="checkbox"
                :checked="selected"
                class="size-4 rounded border-input accent-[var(--primary)]"
                :aria-label="`Pilih ${row.subject}`"
                @click.stop="emit('toggleSelect')"
            />
            <button
                v-if="row.kind === 'thread'"
                type="button"
                :aria-label="row.starred ? 'Hapus bintang' : 'Beri bintang'"
                :aria-pressed="row.starred"
                class="text-muted-foreground/50 hover:text-gold-strong"
                @click.stop="emit('toggleStar')"
            >
                <Star
                    :class="[
                        'size-4',
                        row.starred ? 'fill-gold text-gold-strong' : '',
                    ]"
                />
            </button>
        </div>

        <component
            :is="href ? Link : 'button'"
            :href="href"
            preserve-scroll
            preserve-state
            :only="href ? ['thread', 'mail'] : undefined"
            type="button"
            class="min-w-0 flex-1 text-left outline-none after:absolute after:inset-0 focus-visible:after:ring-2 focus-visible:after:ring-ring/50 focus-visible:after:ring-inset"
            @click="!href && emit('open')"
        >
            <div class="flex items-baseline gap-2">
                <span
                    :class="[
                        'min-w-0 flex-1 truncate text-sm',
                        row.unread
                            ? 'font-semibold text-foreground'
                            : 'text-foreground/85',
                    ]"
                >
                    {{ row.participants }}
                    <span
                        v-if="row.count > 1"
                        class="tabular ml-0.5 text-xs font-normal text-muted-foreground"
                        >{{ row.count }}</span
                    >
                </span>
                <span
                    :class="[
                        'tabular shrink-0 text-xs',
                        row.unread
                            ? 'font-semibold text-foreground'
                            : 'text-muted-foreground',
                    ]"
                    >{{ shortDate(row.date) }}</span
                >
            </div>
            <div class="mt-0.5 flex items-center gap-1.5">
                <Megaphone
                    v-if="row.broadcast"
                    class="size-3.5 shrink-0 text-info"
                    aria-label="Balasan siaran"
                />
                <span
                    :class="[
                        'min-w-0 flex-1 truncate text-[13px]',
                        row.unread
                            ? 'font-semibold'
                            : 'font-medium text-foreground/90',
                    ]"
                    >{{ row.subject }}</span
                >
                <Paperclip
                    v-if="row.has_attachments"
                    class="size-3.5 shrink-0 text-muted-foreground"
                    aria-label="Ada lampiran"
                />
            </div>
            <p
                class="mt-0.5 line-clamp-2 text-[13px] leading-snug text-muted-foreground"
            >
                {{ row.snippet || ' ' }}
            </p>
            <div v-if="row.status" class="mt-1.5 flex items-center gap-2">
                <StatusBadge
                    :status="row.status.value"
                    :label="row.status.label"
                />
                <span
                    v-if="row.error"
                    class="flex min-w-0 items-center gap-1 truncate text-xs text-danger"
                    ><AlertCircle class="size-3 shrink-0" />{{
                        row.error
                    }}</span
                >
            </div>
        </component>
    </div>
</template>
