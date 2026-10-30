<script setup lang="ts">
import TextAlign from '@tiptap/extension-text-align';
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignLeft,
    Bold,
    Braces,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Redo2,
    RemoveFormatting,
    Underline,
    Undo2,
} from '@lucide/vue';
import { onBeforeUnmount, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

const model = defineModel<string>({ required: true });
const props = withDefaults(
    defineProps<{
        placeholder?: string;
        mergeFields?: Record<string, string>;
        minHeight?: string;
        class?: string;
    }>(),
    { placeholder: 'Tulis pesan…', minHeight: '220px' },
);

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
            },
        }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'mail-prose px-4 py-3',
            style: `min-height:${props.minHeight}`,
        },
    },
    onUpdate: ({ editor: e }) => {
        model.value = e.getHTML();
    },
});

// Isi diganti dari luar (mis. memilih templat) → sinkronkan ke editor.
watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value, { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    const previous = editor.value?.getAttributes('link').href as
        | string
        | undefined;
    const url = window.prompt(
        'Alamat tautan (kosongkan untuk menghapus):',
        previous ?? 'https://',
    );

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url.trim() })
        .run();
}

function insertField(key: string) {
    editor.value?.chain().focus().insertContent(`{{${key}}}`).run();
}

type Tool = {
    icon: unknown;
    label: string;
    run: () => void;
    active?: () => boolean;
};
const tools: (Tool | 'sep')[] = [
    {
        icon: Bold,
        label: 'Tebal (Ctrl+B)',
        run: () => editor.value?.chain().focus().toggleBold().run(),
        active: () => !!editor.value?.isActive('bold'),
    },
    {
        icon: Italic,
        label: 'Miring (Ctrl+I)',
        run: () => editor.value?.chain().focus().toggleItalic().run(),
        active: () => !!editor.value?.isActive('italic'),
    },
    {
        icon: Underline,
        label: 'Garis bawah (Ctrl+U)',
        run: () => editor.value?.chain().focus().toggleUnderline().run(),
        active: () => !!editor.value?.isActive('underline'),
    },
    'sep',
    {
        icon: List,
        label: 'Daftar berpoin',
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
        active: () => !!editor.value?.isActive('bulletList'),
    },
    {
        icon: ListOrdered,
        label: 'Daftar bernomor',
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        active: () => !!editor.value?.isActive('orderedList'),
    },
    {
        icon: Quote,
        label: 'Kutipan',
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
        active: () => !!editor.value?.isActive('blockquote'),
    },
    {
        icon: Link2,
        label: 'Tautan',
        run: setLink,
        active: () => !!editor.value?.isActive('link'),
    },
    'sep',
    {
        icon: AlignLeft,
        label: 'Rata kiri',
        run: () => editor.value?.chain().focus().setTextAlign('left').run(),
    },
    {
        icon: AlignCenter,
        label: 'Rata tengah',
        run: () => editor.value?.chain().focus().setTextAlign('center').run(),
        active: () => !!editor.value?.isActive({ textAlign: 'center' }),
    },
    {
        icon: RemoveFormatting,
        label: 'Hapus format',
        run: () =>
            editor.value?.chain().focus().unsetAllMarks().clearNodes().run(),
    },
    'sep',
    {
        icon: Undo2,
        label: 'Urungkan',
        run: () => editor.value?.chain().focus().undo().run(),
    },
    {
        icon: Redo2,
        label: 'Ulangi',
        run: () => editor.value?.chain().focus().redo().run(),
    },
];
</script>

<template>
    <div :class="cn('flex min-h-0 flex-col', props.class)">
        <div
            class="flex flex-wrap items-center gap-0.5 border-b px-2 py-1"
            role="toolbar"
            aria-label="Format teks"
        >
            <template v-for="(tool, i) in tools" :key="i">
                <span v-if="tool === 'sep'" class="mx-1 h-5 w-px bg-border" />
                <button
                    v-else
                    type="button"
                    :title="tool.label"
                    :aria-label="tool.label"
                    :aria-pressed="tool.active?.() ?? undefined"
                    :class="[
                        'inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground',
                        tool.active?.() ? 'bg-accent text-foreground' : '',
                    ]"
                    @mousedown.prevent
                    @click="tool.run"
                >
                    <component :is="tool.icon" class="size-4" />
                </button>
            </template>

            <DropdownMenu v-if="mergeFields">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="ml-auto inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium text-info hover:bg-info-soft"
                    >
                        <Braces class="size-3.5" /> Sisipkan isian
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel class="text-xs text-muted-foreground"
                        >Diganti otomatis per penerima</DropdownMenuLabel
                    >
                    <DropdownMenuItem
                        v-for="(label, key) in mergeFields"
                        :key="key"
                        @select="insertField(String(key))"
                    >
                        <code
                            class="mr-2 rounded bg-info-soft px-1 font-mono text-xs text-info"
                            v-text="`{{${key}}}`"
                        />
                        {{ label }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <EditorContent
            :editor="editor"
            class="min-h-0 flex-1 overflow-y-auto"
        />
    </div>
</template>
