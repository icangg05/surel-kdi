<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { dateOnly, number } from '@/lib/format';
import admin from '@/routes/admin';

const props = defineProps<{
    settings: {
        daily_limit: number;
        per_minute: number;
        spam_threshold: number;
        quota_timezone: string;
        reply_tracking: boolean;
    };
    quota: {
        used: number;
        remaining: number;
        resets_at: string;
        history: { date: string; used: number }[];
    };
    timezones: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            { title: 'Kuota & Pengiriman', href: admin.settings() },
        ],
    },
});

const form = useForm({ ...props.settings });
const hasUsage = computed(() => props.quota.history.some((h) => h.used > 0));
const peak = computed(() =>
    Math.max(
        1,
        ...props.quota.history.map((h) => h.used),
        props.settings.daily_limit,
    ),
);
const exhausted = computed(
    () =>
        props.quota.remaining === 0 &&
        props.quota.used < props.settings.daily_limit,
);
const reset = computed(() =>
    new Date(props.quota.resets_at).toLocaleString('id-ID', {
        weekday: 'long',
        hour: '2-digit',
        minute: '2-digit',
    }),
);
</script>

<template>
    <Head title="Kuota & Pengiriman" />
    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
        <PageHeader
            title="Kuota & Pengiriman"
            description="Batas ini menjaga agar Surel Kendari tidak melebihi limit akun Brevo. Setiap alamat penerima dihitung satu kredit."
        />

        <form
            class="space-y-5 rounded-xl border bg-card p-5"
            @submit.prevent="
                form.put(admin.settings.update.url(), { preserveScroll: true })
            "
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="s-limit">Limit harian (email/hari)</Label>
                    <Input
                        id="s-limit"
                        v-model.number="form.daily_limit"
                        type="number"
                        min="1"
                        class="tabular"
                    />
                    <p class="text-xs text-muted-foreground">
                        Paket gratis Brevo: 300/hari. Samakan dengan paket akun.
                    </p>
                    <InputError :message="form.errors.daily_limit" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="s-rate">Laju siaran (email/menit)</Label>
                    <Input
                        id="s-rate"
                        v-model.number="form.per_minute"
                        type="number"
                        min="1"
                        class="tabular"
                    />
                    <p class="text-xs text-muted-foreground">
                        Siaran dikirim bertahap supaya reputasi pengirim tetap
                        baik.
                    </p>
                    <InputError :message="form.errors.per_minute" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="s-tz">Zona waktu reset kuota</Label>
                    <select
                        id="s-tz"
                        v-model="form.quota_timezone"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option v-for="tz in timezones" :key="tz" :value="tz">
                            {{ tz }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Ikuti zona waktu reset kuota akun Brevo Anda.
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="s-spam">Ambang skor spam email masuk</Label>
                    <Input
                        id="s-spam"
                        v-model.number="form.spam_threshold"
                        type="number"
                        step="0.5"
                        min="0"
                        class="tabular"
                    />
                    <p class="text-xs text-muted-foreground">
                        Skor dari Brevo di atas ini masuk folder Spam.
                    </p>
                </div>
            </div>
            <label class="flex items-start gap-3 rounded-lg border p-3">
                <Switch v-model="form.reply_tracking" class="mt-0.5" />
                <span>
                    <span class="block text-sm font-medium"
                        >Token pelacak di alamat balasan</span
                    >
                    <span class="block text-xs text-muted-foreground"
                        >Reply-To berbentuk
                        <span class="font-mono">kominfo+t8x2k…@in.domain</span>
                        sehingga setiap balasan pasti kembali ke utas & penerima
                        siaran yang benar.</span
                    >
                </span>
            </label>
            <div class="flex justify-end">
                <Button :disabled="form.processing || !form.isDirty"
                    >Simpan pengaturan</Button
                >
            </div>
        </form>

        <section class="rounded-xl border bg-card p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Pemakaian 30 hari</h2>
                <p class="tabular text-sm text-muted-foreground">
                    Hari ini {{ number(quota.used) }} terpakai · reset
                    {{ reset }}
                </p>
            </div>
            <div
                v-if="exhausted"
                class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-warning-soft p-3 text-sm text-warning"
            >
                <span class="flex-1"
                    >Brevo menolak pengiriman hari ini karena limit akun, walau
                    hitungan aplikasi belum penuh.</span
                >
                <Button
                    size="sm"
                    variant="outline"
                    @click="
                        router.post(
                            admin.settings.resetQuota.url(),
                            {},
                            { preserveScroll: true },
                        )
                    "
                    >Cabut status habis</Button
                >
            </div>
            <p
                v-if="!hasUsage"
                class="mt-4 rounded-lg bg-muted/50 py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada email terkirim dalam 30 hari terakhir.
            </p>
            <div
                v-else
                class="mt-4 flex h-32 items-end gap-[2px]"
                aria-label="Grafik pemakaian kuota harian"
            >
                <div
                    v-for="h in quota.history"
                    :key="h.date"
                    class="group relative flex h-full flex-1 items-end"
                    role="img"
                    :aria-label="`${dateOnly(h.date)}: ${h.used}`"
                >
                    <div
                        class="w-full rounded-t-[4px] bg-chart-1"
                        :style="{
                            height: h.used
                                ? `max(${(h.used / peak) * 100}%, 3px)`
                                : '0',
                        }"
                    />
                    <span
                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap shadow group-hover:block"
                        >{{ dateOnly(h.date) }}:
                        <strong class="tabular">{{
                            number(h.used)
                        }}</strong></span
                    >
                </div>
            </div>
            <div
                v-if="hasUsage"
                class="mt-1 border-t border-dashed pt-1 text-right text-[11px] text-muted-foreground"
            >
                Garis dasar 0 · puncak skala {{ number(peak) }}
            </div>
        </section>
    </div>
</template>
