<script setup lang="ts">
import { computed, ref } from 'vue';
import { dateOnly, number } from '@/lib/format';

type Point = { date: string; sent: number; opened: number; failed: number };
const props = defineProps<{ series: Point[] }>();

const hover = ref<number | null>(null);
const showTable = ref(false);

// Skala "rapi": pilih langkah bulat dulu, garis bantu di kelipatannya.
const step = computed(() => {
    const peak = Math.max(...props.series.map((p) => p.sent), 0);
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(peak, 1)));

    return (
        [1, 2, 5, 10].map((m) => m * magnitude).find((s) => s * 4 >= peak) ??
        magnitude * 10
    );
});
const max = computed(() => step.value * 4);
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => i * step.value));
const day = (d: string) =>
    new Date(d).toLocaleDateString('id-ID', { day: 'numeric' });
const weekday = (d: string) =>
    new Date(d).toLocaleDateString('id-ID', { weekday: 'short' }).slice(0, 3);
const total = computed(() => props.series.reduce((s, p) => s + p.sent, 0));
const active = computed(() =>
    hover.value === null ? null : props.series[hover.value],
);
</script>

<template>
    <div>
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold">Email terkirim per hari</h2>
                <p class="text-xs text-muted-foreground">
                    14 hari terakhir · total
                    <span class="tabular font-medium text-foreground">{{
                        number(total)
                    }}</span>
                </p>
            </div>
            <button
                type="button"
                class="text-xs font-medium text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                @click="showTable = !showTable"
            >
                {{ showTable ? 'Lihat grafik' : 'Lihat tabel' }}
            </button>
        </div>

        <table v-if="showTable" class="w-full text-sm">
            <thead class="bg-muted text-left text-xs text-secondary-foreground">
                <tr>
                    <th class="py-1.5 font-medium">Tanggal</th>
                    <th class="py-1.5 text-right font-medium">Terkirim</th>
                    <th class="py-1.5 text-right font-medium">Dibuka</th>
                    <th class="py-1.5 text-right font-medium">Gagal</th>
                </tr>
            </thead>
            <tbody class="tabular">
                <tr
                    v-for="p in [...series].reverse()"
                    :key="p.date"
                    class="border-t"
                >
                    <td class="py-1.5">{{ dateOnly(p.date) }}</td>
                    <td class="py-1.5 text-right">{{ number(p.sent) }}</td>
                    <td class="py-1.5 text-right">{{ number(p.opened) }}</td>
                    <td class="py-1.5 text-right">{{ number(p.failed) }}</td>
                </tr>
            </tbody>
        </table>

        <div v-else class="relative">
            <div class="relative h-44 pl-8">
                <!-- Garis bantu, sengaja samar -->
                <div
                    v-for="t in ticks"
                    :key="t"
                    class="absolute right-0 left-8 border-t border-dashed border-border/70"
                    :style="{ bottom: `${(t / max) * 100}%` }"
                >
                    <span
                        class="tabular absolute -top-2 -left-8 w-6 text-right text-[10px] text-muted-foreground"
                        >{{ number(t) }}</span
                    >
                </div>
                <div
                    class="absolute inset-0 left-8 flex items-end gap-[2px]"
                    @mouseleave="hover = null"
                >
                    <div
                        v-for="(p, i) in series"
                        :key="p.date"
                        class="relative flex h-full flex-1 cursor-default items-end justify-center"
                        role="img"
                        :aria-label="`${dateOnly(p.date)}: ${p.sent} terkirim, ${p.opened} dibuka, ${p.failed} gagal`"
                        @mouseenter="hover = i"
                    >
                        <div
                            :class="[
                                'w-full max-w-7 rounded-t-[4px] bg-chart-1 transition-opacity',
                                hover !== null && hover !== i
                                    ? 'opacity-45'
                                    : '',
                            ]"
                            :style="{
                                height: p.sent
                                    ? `max(${(p.sent / max) * 100}%, 3px)`
                                    : '0',
                            }"
                        />
                    </div>
                </div>

                <div
                    v-if="active && hover !== null"
                    class="pointer-events-none absolute z-10 w-44 -translate-x-1/2 rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg"
                    :style="{
                        left: `calc(2rem + (100% - 2rem) * ${(hover + 0.5) / series.length})`,
                        top: '-0.5rem',
                    }"
                >
                    <p class="mb-1 font-medium">{{ dateOnly(active.date) }}</p>
                    <p class="flex justify-between">
                        <span class="flex items-center gap-1.5"
                            ><span
                                class="size-2 rounded-sm bg-chart-1"
                            />Terkirim</span
                        ><span class="tabular font-medium">{{
                            number(active.sent)
                        }}</span>
                    </p>
                    <p class="flex justify-between text-muted-foreground">
                        <span>Dibuka</span
                        ><span class="tabular">{{
                            number(active.opened)
                        }}</span>
                    </p>
                    <p class="flex justify-between text-muted-foreground">
                        <span>Gagal</span
                        ><span class="tabular">{{
                            number(active.failed)
                        }}</span>
                    </p>
                </div>
            </div>
            <div class="mt-1.5 flex gap-[2px] pl-8">
                <span
                    v-for="(p, i) in series"
                    :key="p.date"
                    :class="[
                        'tabular flex-1 text-center text-[10px] leading-tight',
                        i === series.length - 1
                            ? 'font-semibold text-foreground'
                            : 'text-muted-foreground',
                    ]"
                >
                    <span class="block">{{ day(p.date) }}</span>
                    <span class="hidden sm:block">{{ weekday(p.date) }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
