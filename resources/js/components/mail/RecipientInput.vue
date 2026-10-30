<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Users, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { groupDot } from '@/lib/format';
import { json } from '@/lib/http';
import { search as searchRecipients } from '@/routes/recipients';
import type { Recipient } from '@/types';

type ContactHit = {
    type: 'contact';
    id: number;
    name: string;
    email: string;
    detail: string;
    suppressed: boolean;
};
type GroupHit = {
    type: 'group';
    id: number;
    name: string;
    count: number;
    color: string;
    members: Recipient[];
};

const model = defineModel<Recipient[]>({ required: true });
defineProps<{ label: string; placeholder?: string; autofocus?: boolean }>();

const text = ref('');
const hits = ref<(ContactHit | GroupHit)[]>([]);
const active = ref(0);
const open = ref(false);
const input = ref<HTMLInputElement | null>(null);
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const fetchHits = useDebounceFn(async (q: string) => {
    if (q.length < 2) {
        hits.value = [];

        return;
    }

    const res = await json<{ contacts: ContactHit[]; groups: GroupHit[] }>(
        'GET',
        searchRecipients.url({ query: { q } }),
    );
    hits.value = [...res.groups, ...res.contacts];
    active.value = 0;
    open.value = hits.value.length > 0;
}, 180);

function onInput() {
    // Tempel daftar alamat (dipisah koma/titik koma/baris baru) sekaligus.
    if (/[,;\n]/.test(text.value)) {
        text.value.split(/[,;\n]+/).forEach((part) => addRaw(part));
        text.value = '';

        return;
    }

    fetchHits(text.value.trim());
}

function add(recipient: Recipient) {
    const email = recipient.email.trim().toLowerCase();

    if (email && !model.value.some((r) => r.email === email)) {
        model.value = [...model.value, { email, name: recipient.name ?? null }];
    }
}

function addRaw(raw: string) {
    const value = raw.trim();
    const match = value.match(/^(.*)<([^>]+)>$/);
    const email = (match ? match[2]! : value).trim();

    if (email) {
        add({
            email,
            name: match ? match[1]!.trim().replace(/^"|"$/g, '') || null : null,
        });
    }
}

function pick(hit: ContactHit | GroupHit) {
    if (hit.type === 'group') {
        hit.members.forEach(add);
    } else {
        add({ email: hit.email, name: hit.name });
    }

    text.value = '';
    hits.value = [];
    open.value = false;
    nextTick(() => input.value?.focus());
}

function onKeydown(e: KeyboardEvent) {
    if (open.value && hits.value.length) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active.value = (active.value + 1) % hits.value.length;

            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            active.value =
                (active.value - 1 + hits.value.length) % hits.value.length;

            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            pick(hits.value[active.value]!);

            return;
        }
    }

    if ((e.key === 'Enter' || e.key === 'Tab') && text.value.trim()) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }

        addRaw(text.value);
        text.value = '';
        open.value = false;
    }

    if (e.key === 'Backspace' && !text.value && model.value.length) {
        model.value = model.value.slice(0, -1);
    }

    if (e.key === 'Escape') {
        open.value = false;
    }
}

function onBlur() {
    window.setTimeout(() => {
        if (text.value.trim()) {
            addRaw(text.value);
            text.value = '';
        }

        open.value = false;
    }, 150);
}

function remove(email: string) {
    model.value = model.value.filter((r) => r.email !== email);
}

const invalid = computed(
    () =>
        new Set(
            model.value.filter((r) => !EMAIL.test(r.email)).map((r) => r.email),
        ),
);
</script>

<template>
    <div
        class="relative flex min-h-10 items-start gap-2 border-b px-4 py-1.5"
        @click="input?.focus()"
    >
        <span class="w-12 shrink-0 pt-1.5 text-sm text-muted-foreground">{{
            label
        }}</span>
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1">
            <span
                v-for="r in model"
                :key="r.email"
                :title="r.email"
                :class="[
                    'inline-flex max-w-full items-center gap-1 rounded py-0.5 pr-1 pl-2.5 text-[13px]',
                    invalid.has(r.email)
                        ? 'bg-danger-soft text-danger'
                        : 'bg-secondary text-secondary-foreground',
                ]"
            >
                <span class="truncate">{{ r.name || r.email }}</span>
                <button
                    type="button"
                    class="rounded-sm p-0.5 hover:bg-foreground/10"
                    :aria-label="`Hapus ${r.email}`"
                    @click.stop="remove(r.email)"
                >
                    <X class="size-3" />
                </button>
            </span>
            <input
                ref="input"
                v-model="text"
                v-focus="autofocus"
                type="text"
                autocomplete="off"
                spellcheck="false"
                class="h-7 min-w-[12ch] flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/70"
                :placeholder="model.length ? '' : placeholder"
                :aria-label="label"
                @input="onInput"
                @keydown="onKeydown"
                @blur="onBlur"
            />
        </div>

        <div
            v-if="open"
            class="absolute top-full right-3 left-16 z-50 mt-1 max-h-72 overflow-y-auto rounded-lg border bg-popover p-1 shadow-lg"
            role="listbox"
        >
            <button
                v-for="(hit, i) in hits"
                :key="`${hit.type}-${hit.id}`"
                type="button"
                role="option"
                :aria-selected="i === active"
                :class="[
                    'flex w-full items-center gap-3 rounded-md px-2.5 py-2 text-left text-sm',
                    i === active ? 'bg-accent' : 'hover:bg-accent/60',
                ]"
                @mousedown.prevent="pick(hit)"
                @mouseenter="active = i"
            >
                <template v-if="hit.type === 'group'">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded bg-muted"
                        ><Users class="size-3.5"
                    /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5 font-medium"
                            ><span
                                :class="[
                                    'size-2 rounded-full',
                                    groupDot(hit.color),
                                ]"
                            />Grup {{ hit.name }}</span
                        >
                        <span class="block text-xs text-muted-foreground"
                            >{{ hit.count }} kontak aktif — semua
                            dimasukkan</span
                        >
                    </span>
                </template>
                <template v-else>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{
                            hit.name
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ hit.email
                            }}<template v-if="hit.detail">
                                · {{ hit.detail }}</template
                            ></span
                        >
                    </span>
                    <span v-if="hit.suppressed" class="text-xs text-danger"
                        >ditolak sebelumnya</span
                    >
                </template>
            </button>
        </div>
    </div>
</template>
