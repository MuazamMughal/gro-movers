<script setup lang="ts">
import { Bold, Heading2, Heading3, Italic, Link2, List, ListOrdered, Quote, Redo2, Undo2, Unlink } from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps<{ id?: string; invalid?: boolean }>();
const model = defineModel<string | null>({ default: null });

const editor = useEditor({
    content: model.value ?? '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            codeBlock: false,
            link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: null, target: null } },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose-admin min-h-40 px-3 py-2 focus:outline-none',
            ...(props.id ? { id: props.id } : {}),
            'aria-multiline': 'true',
            role: 'textbox',
        },
    },
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        model.value = html === '<p></p>' ? null : html;
    },
});

// Keep the editor in sync when the value changes from outside (e.g. duplicate, reset).
watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    if (!editor.value) return;

    const previous = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link URL (e.g. /services/local-moving or a full https link)', previous ?? '');

    if (url === null) return;

    if (url.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
}

type Tool = { label: string; icon: any; action: () => void; active?: () => boolean };

const tools: Tool[] = [
    { label: 'Bold', icon: Bold, action: () => editor.value?.chain().focus().toggleBold().run(), active: () => !!editor.value?.isActive('bold') },
    { label: 'Italic', icon: Italic, action: () => editor.value?.chain().focus().toggleItalic().run(), active: () => !!editor.value?.isActive('italic') },
    { label: 'Heading 2', icon: Heading2, action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), active: () => !!editor.value?.isActive('heading', { level: 2 }) },
    { label: 'Heading 3', icon: Heading3, action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(), active: () => !!editor.value?.isActive('heading', { level: 3 }) },
    { label: 'Bullet list', icon: List, action: () => editor.value?.chain().focus().toggleBulletList().run(), active: () => !!editor.value?.isActive('bulletList') },
    { label: 'Numbered list', icon: ListOrdered, action: () => editor.value?.chain().focus().toggleOrderedList().run(), active: () => !!editor.value?.isActive('orderedList') },
    { label: 'Quote', icon: Quote, action: () => editor.value?.chain().focus().toggleBlockquote().run(), active: () => !!editor.value?.isActive('blockquote') },
    { label: 'Link', icon: Link2, action: setLink, active: () => !!editor.value?.isActive('link') },
    { label: 'Remove link', icon: Unlink, action: () => editor.value?.chain().focus().unsetLink().run() },
    { label: 'Undo', icon: Undo2, action: () => editor.value?.chain().focus().undo().run() },
    { label: 'Redo', icon: Redo2, action: () => editor.value?.chain().focus().redo().run() },
];
</script>

<template>
    <div class="overflow-hidden rounded-md border shadow-xs" :class="{ 'border-destructive': invalid }">
        <div class="flex flex-wrap gap-0.5 border-b bg-muted/40 p-1" role="toolbar" aria-label="Formatting">
            <button
                v-for="tool in tools"
                :key="tool.label"
                type="button"
                class="rounded p-1.5 hover:bg-muted"
                :class="{ 'bg-muted text-foreground': tool.active?.() }"
                :aria-label="tool.label"
                :title="tool.label"
                :aria-pressed="tool.active ? tool.active() : undefined"
                @click="tool.action"
            >
                <component :is="tool.icon" class="size-4" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.prose-admin > * + * { margin-top: 0.75em; }
.prose-admin h2 { font-size: 1.35em; font-weight: 700; }
.prose-admin h3 { font-size: 1.15em; font-weight: 700; }
.prose-admin h4 { font-weight: 700; }
.prose-admin ul { list-style: disc; padding-left: 1.5em; }
.prose-admin ol { list-style: decimal; padding-left: 1.5em; }
.prose-admin blockquote { border-left: 3px solid currentColor; padding-left: 1em; opacity: 0.85; }
.prose-admin a { text-decoration: underline; color: var(--primary); }
.prose-admin p.is-editor-empty:first-child::before { content: attr(data-placeholder); opacity: 0.5; pointer-events: none; float: left; height: 0; }
</style>
