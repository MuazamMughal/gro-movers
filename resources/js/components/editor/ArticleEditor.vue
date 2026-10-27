<script setup lang="ts">
import {
    Bold,
    Heading2,
    Heading3,
    Heading4,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    SquarePlay,
    Table,
    TextCursorInput,
    Undo2,
    Unlink,
} from '@lucide/vue';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import Youtube from '@tiptap/extension-youtube';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import type { MediaItem } from '@/components/media/types';

/**
 * Blog post editor: H2–H4, lists, quotes, tables, media-library images, links and
 * YouTube embeds. Library images keep their ID (data-media-id) so the website can
 * serve them as responsive WebP <picture> elements.
 */
const props = defineProps<{ id?: string; invalid?: boolean }>();
const model = defineModel<string | null>({ default: null });
const emit = defineEmits<{ words: [count: number] }>();

// Library image node: stores the media ID next to the src.
const LibraryImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            mediaId: {
                default: null,
                parseHTML: (el: HTMLElement) => el.getAttribute('data-media-id'),
                renderHTML: (attrs: Record<string, unknown>) => (attrs.mediaId ? { 'data-media-id': attrs.mediaId } : {}),
            },
        };
    },
});

// Saved HTML is purified (the data-youtube-video wrapper loses its attribute): accept bare embeds too.
const YoutubeEmbed = Youtube.extend({
    parseHTML() {
        return [{ tag: 'div[data-youtube-video] iframe' }, { tag: 'iframe[src*="youtube"]' }];
    },
});

const wordCount = ref(0);

const editor = useEditor({
    content: model.value ?? '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            codeBlock: false,
            link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: null, target: null } },
        }),
        LibraryImage.configure({ inline: false, allowBase64: false }),
        TableKit.configure({ table: { resizable: false } }),
        YoutubeEmbed.configure({ nocookie: true, modestBranding: true, width: 640, height: 360 }),
    ],
    editorProps: {
        attributes: {
            class: 'prose-admin prose-article min-h-96 px-4 py-3 focus:outline-none',
            ...(props.id ? { id: props.id } : {}),
            'aria-multiline': 'true',
            'aria-label': 'Article content',
            role: 'textbox',
        },
    },
    onCreate: ({ editor }) => countWords(editor.getText()),
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        model.value = html === '<p></p>' ? null : html;
        countWords(editor.getText());
    },
});

function countWords(text: string) {
    wordCount.value = text.trim() ? text.trim().split(/\s+/).length : 0;
    emit('words', wordCount.value);
}

watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
        countWords(editor.value.getText());
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const chain = () => editor.value!.chain().focus();
const isActive = (name: string, attrs?: Record<string, unknown>) => !!editor.value?.isActive(name, attrs);

function setLink() {
    if (!editor.value) return;

    const previous = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link URL (e.g. /services/local-moving or a full https link)', previous ?? '');

    if (url === null) return;

    if (url.trim() === '') {
        chain().extendMarkRange('link').unsetLink().run();

        return;
    }

    chain().extendMarkRange('link').setLink({ href: url.trim() }).run();
}

function addVideo() {
    const url = window.prompt('YouTube link (e.g. https://www.youtube.com/watch?v=…)');

    if (!url?.trim()) return;

    if (!chain().setYoutubeVideo({ src: url.trim() }).run()) {
        window.alert('That does not look like a YouTube link.');
    }
}

// Images come from the media library (upload + crop happen in the picker).
const picker = ref<InstanceType<typeof MediaPicker> | null>(null);
const pickedId = ref<number | null>(null);

function insertImage(item: MediaItem) {
    chain().setImage({ src: item.url, alt: item.alt ?? '', mediaId: item.id } as never).run();
    pickedId.value = null;
}

function editAlt() {
    const current = (editor.value?.getAttributes('image').alt as string | undefined) ?? '';
    const alt = window.prompt('Describe the image for Google and screen readers (alt text)', current);

    if (alt !== null) {
        chain().updateAttributes('image', { alt: alt.trim() }).run();
    }
}

type Tool = { label: string; icon: any; action: () => void; active?: () => boolean; divider?: boolean };

const tools: Tool[] = [
    { label: 'Bold', icon: Bold, action: () => chain().toggleBold().run(), active: () => isActive('bold') },
    { label: 'Italic', icon: Italic, action: () => chain().toggleItalic().run(), active: () => isActive('italic') },
    { label: 'Heading 2 (main section, shown in the table of contents)', icon: Heading2, action: () => chain().toggleHeading({ level: 2 }).run(), active: () => isActive('heading', { level: 2 }), divider: true },
    { label: 'Heading 3', icon: Heading3, action: () => chain().toggleHeading({ level: 3 }).run(), active: () => isActive('heading', { level: 3 }) },
    { label: 'Heading 4', icon: Heading4, action: () => chain().toggleHeading({ level: 4 }).run(), active: () => isActive('heading', { level: 4 }) },
    { label: 'Bullet list', icon: List, action: () => chain().toggleBulletList().run(), active: () => isActive('bulletList'), divider: true },
    { label: 'Numbered list', icon: ListOrdered, action: () => chain().toggleOrderedList().run(), active: () => isActive('orderedList') },
    { label: 'Quote', icon: Quote, action: () => chain().toggleBlockquote().run(), active: () => isActive('blockquote') },
    { label: 'Divider line', icon: Minus, action: () => chain().setHorizontalRule().run() },
    { label: 'Link', icon: Link2, action: setLink, active: () => isActive('link'), divider: true },
    { label: 'Remove link', icon: Unlink, action: () => chain().unsetLink().run() },
    { label: 'Image from media library', icon: ImagePlus, action: () => picker.value?.open(), divider: true },
    { label: 'Table', icon: Table, action: () => chain().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(), active: () => isActive('table') },
    { label: 'YouTube video', icon: SquarePlay, action: addVideo },
    { label: 'Undo', icon: Undo2, action: () => chain().undo().run(), divider: true },
    { label: 'Redo', icon: Redo2, action: () => chain().redo().run() },
];

const tableTools = [
    { label: '+ Row', action: () => chain().addRowAfter().run() },
    { label: '+ Column', action: () => chain().addColumnAfter().run() },
    { label: '− Row', action: () => chain().deleteRow().run() },
    { label: '− Column', action: () => chain().deleteColumn().run() },
    { label: 'Header row on/off', action: () => chain().toggleHeaderRow().run() },
    { label: 'Delete table', action: () => chain().deleteTable().run() },
];

const inTable = computed(() => isActive('table'));
const onImage = computed(() => isActive('image'));
</script>

<template>
    <div class="rounded-md border shadow-xs" :class="{ 'border-destructive': invalid }">
        <div class="sticky top-0 z-10 rounded-t-md border-b bg-background">
            <div class="flex flex-wrap items-center gap-0.5 bg-muted/40 p-1" role="toolbar" aria-label="Formatting">
                <template v-for="tool in tools" :key="tool.label">
                    <span v-if="tool.divider" class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
                    <button
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
                </template>
            </div>
            <div v-if="inTable || onImage" class="flex flex-wrap items-center gap-1 border-t px-2 py-1 text-xs">
                <template v-if="inTable">
                    <span class="mr-1 font-medium text-muted-foreground">Table:</span>
                    <button v-for="t in tableTools" :key="t.label" type="button" class="rounded px-2 py-1 hover:bg-muted" @click="t.action">{{ t.label }}</button>
                </template>
                <button v-if="onImage" type="button" class="inline-flex items-center gap-1 rounded px-2 py-1 hover:bg-muted" @click="editAlt">
                    <TextCursorInput class="size-3.5" /> Edit alt text
                </button>
            </div>
        </div>
        <EditorContent :editor="editor" />
        <div class="flex justify-between border-t px-3 py-1.5 text-xs text-muted-foreground">
            <span>Use Heading 2 for each main section: they build the table of contents.</span>
            <span>{{ wordCount }} words · {{ Math.max(1, Math.ceil(wordCount / 200)) }} min read</span>
        </div>
    </div>

    <MediaPicker ref="picker" v-model="pickedId" bare preset="content" label="Insert image" @select="insertImage" />
</template>

<style>
.prose-article img { max-width: 100%; height: auto; border-radius: 0.5rem; }
.prose-article img.ProseMirror-selectednode { outline: 3px solid var(--primary); }
.prose-article table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.prose-article th, .prose-article td { border: 1px solid var(--border); padding: 0.4em 0.6em; vertical-align: top; }
.prose-article th { background: var(--muted); font-weight: 700; text-align: left; }
.prose-article .selectedCell { background: color-mix(in oklab, var(--primary) 12%, transparent); }
.prose-article div[data-youtube-video] iframe { width: 100%; aspect-ratio: 16 / 9; height: auto; border-radius: 0.5rem; }
.prose-article hr { border-top: 2px solid var(--border); }
</style>
