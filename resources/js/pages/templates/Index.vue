<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Megaphone, Plus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { relative } from '@/lib/format';
import broadcastRoutes from '@/routes/broadcasts';
import templateRoutes from '@/routes/templates';

defineProps<{
    templates: {
        id: number;
        name: string;
        subject: string;
        snippet: string;
        author: string | null;
        updated_at: string;
    }[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Templat', href: templateRoutes.index() }],
    },
});
</script>

<template>
    <Head title="Templat" />
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-6">
        <PageHeader
            title="Templat"
            description="Isi siaran yang sering dipakai — surat edaran, undangan rapat, pemberitahuan. Merge field seperti {{nama}} diisi otomatis per penerima."
        >
            <Button as-child
                ><Link :href="templateRoutes.create()"
                    ><Plus /> Templat baru</Link
                ></Button
            >
        </PageHeader>

        <div v-if="templates.length" class="grid gap-3 sm:grid-cols-2">
            <article
                v-for="t in templates"
                :key="t.id"
                class="flex flex-col rounded-xl border bg-card p-4"
            >
                <Link :href="templateRoutes.edit(t.id)" class="group">
                    <h2 class="font-semibold group-hover:underline">
                        {{ t.name }}
                    </h2>
                    <p class="mt-0.5 truncate text-sm text-foreground/80">
                        {{ t.subject }}
                    </p>
                    <p class="mt-2 line-clamp-3 text-sm text-muted-foreground">
                        {{ t.snippet }}
                    </p>
                </Link>
                <div
                    class="mt-auto flex items-center justify-between gap-2 pt-4"
                >
                    <span class="text-xs text-muted-foreground"
                        >Diubah {{ relative(t.updated_at)
                        }}<template v-if="t.author">
                            · {{ t.author }}</template
                        ></span
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        @click="
                            router.post(broadcastRoutes.store.url(), {
                                template_id: t.id,
                            })
                        "
                        ><Megaphone /> Pakai untuk siaran</Button
                    >
                </div>
            </article>
        </div>
        <div v-else class="rounded-xl border bg-card">
            <EmptyState
                :icon="FileText"
                title="Belum ada templat"
                description="Simpan surat yang sering dikirim sebagai templat agar siaran berikutnya cukup beberapa klik."
            >
                <Button as-child
                    ><Link :href="templateRoutes.create()"
                        ><Plus /> Buat templat</Link
                    ></Button
                >
            </EmptyState>
        </div>
    </div>
</template>
