<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import RichEditor from '@/components/mail/RichEditor.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import templateRoutes from '@/routes/templates';

const props = defineProps<{
    template: {
        id: number;
        name: string;
        subject: string;
        body_html: string;
    } | null;
    mergeFields: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Templat', href: templateRoutes.index() },
            { title: 'Ubah', href: '#' },
        ],
    },
});

const form = useForm({
    name: props.template?.name ?? '',
    subject: props.template?.subject ?? '',
    body_html: props.template?.body_html ?? '<p>Yth. {{nama}},</p><p></p>',
});

function submit() {
    if (props.template) {
        form.put(templateRoutes.update.url(props.template.id));
    } else {
        form.post(templateRoutes.store.url());
    }
}

function remove() {
    if (props.template && confirm('Hapus templat ini?')) {
        router.delete(templateRoutes.destroy.url(props.template.id));
    }
}
</script>

<template>
    <Head :title="template ? `Ubah ${template.name}` : 'Templat baru'" />
    <form
        class="mx-auto w-full max-w-4xl space-y-5 p-4 sm:p-6"
        @submit.prevent="submit"
    >
        <PageHeader :title="template ? 'Ubah templat' : 'Templat baru'">
            <Button
                v-if="template"
                type="button"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                @click="remove"
                >Hapus</Button
            >
            <Button :disabled="form.processing">Simpan templat</Button>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="t-name">Nama templat</Label>
                <Input
                    id="t-name"
                    v-model="form.name"
                    required
                    placeholder="mis. Undangan rapat koordinasi"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label for="t-subject">Subjek email</Label>
                <Input
                    id="t-subject"
                    v-model="form.subject"
                    required
                    placeholder="mis. Undangan Rapat Koordinasi {{instansi}}"
                />
                <InputError :message="form.errors.subject" />
            </div>
        </div>

        <div class="grid gap-1.5">
            <Label>Isi</Label>
            <div class="overflow-hidden rounded-xl border bg-card">
                <RichEditor
                    v-model="form.body_html"
                    :merge-fields="mergeFields"
                    min-height="360px"
                />
            </div>
            <InputError :message="form.errors.body_html" />
        </div>
    </form>
</template>
