<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CircleHelp, ExternalLink, GripVertical, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import RichTextEditor from '@/components/sections/RichTextEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { request } from '@/lib/http';
import { destroy, index, reorder, show, store, update } from '@/routes/admin/faqs';

type FaqRow = { id: number; question: string; answer: string; is_active: boolean; attached_count: number };
type Usage = { label: string; path: string | null };

const props = defineProps<{ faqs: FaqRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'FAQs', href: index() }] },
});

const list = ref([...props.faqs]);
watch(() => props.faqs, (f) => (list.value = [...f]));

const search = ref('');
const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return term ? list.value.filter((f) => f.question.toLowerCase().includes(term) || f.answer.toLowerCase().includes(term)) : list.value;
});

const saveOrder = () => router.post(reorder().url, { ids: list.value.map((f) => f.id) }, { preserveScroll: true, preserveState: true });

function move(i: number, delta: number) {
    const next = [...list.value];
    const [item] = next.splice(i, 1);
    next.splice(i + delta, 0, item);
    list.value = next;
    saveOrder();
}

// ---- Editor dialog -------------------------------------------------------------
const editing = ref<FaqRow | null>(null);
const dialogOpen = ref(false);
const usages = ref<Usage[] | null>(null);
const form = useForm({ question: '', answer: '' as string | null, is_active: true });

function openEditor(faq: FaqRow | null) {
    editing.value = faq;
    form.clearErrors();
    form.question = faq?.question ?? '';
    form.answer = faq?.answer ?? null;
    form.is_active = faq?.is_active ?? true;
    form.defaults();
    usages.value = null;
    dialogOpen.value = true;

    if (faq) {
        request<{ usages: Usage[] }>(show(faq.id).url).then((res) => (usages.value = res.usages)).catch(() => (usages.value = []));
    }
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) };

    if (editing.value) {
        form.put(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

function toggleActive(faq: FaqRow) {
    router.put(update(faq.id).url, { question: faq.question, answer: faq.answer, is_active: !faq.is_active }, { preserveScroll: true });
}

const toDelete = ref<FaqRow | null>(null);
const confirmDelete = () =>
    toDelete.value && router.delete(destroy(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });

const plain = (html: string) => html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
</script>

<template>
    <Head title="FAQs" />

    <div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="FAQ bank"
                description="Write each question once. Attach it to pages, services and city pages from their FAQs tab; editing it here updates it everywhere."
            />
            <Button @click="openEditor(null)"><Plus class="size-4" /> New FAQ</Button>
        </div>

        <div v-if="list.length > 5" class="relative">
            <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
            <Input v-model="search" class="pl-8" placeholder="Search questions and answers" aria-label="Search FAQs" />
        </div>

        <div v-if="!list.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <CircleHelp class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No FAQs yet</p>
            <p class="text-sm text-muted-foreground">Answer the questions customers ask on the phone: cost, insurance, timing…</p>
        </div>

        <draggable
            v-else
            v-model="list"
            item-key="id"
            handle=".drag-handle"
            :animation="150"
            :disabled="!!search"
            tag="ul"
            class="divide-y rounded-xl border"
            @end="saveOrder"
        >
            <template #item="{ element, index: i }">
                <li v-show="filtered.includes(element)" class="flex items-start gap-2 px-3 py-3" :class="{ 'opacity-60': !element.is_active }">
                    <span v-if="!search" class="drag-handle mt-0.5 cursor-grab text-muted-foreground" aria-hidden="true" title="Drag to reorder"><GripVertical class="size-4" /></span>
                    <button type="button" class="min-w-0 flex-1 text-left" @click="openEditor(element)">
                        <span class="flex flex-wrap items-center gap-2 font-medium">
                            {{ element.question }}
                            <Badge v-if="!element.is_active" variant="outline">Hidden</Badge>
                        </span>
                        <span class="line-clamp-1 text-sm text-muted-foreground">{{ plain(element.answer) }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ element.attached_count ? `Attached to ${element.attached_count} ${element.attached_count === 1 ? 'page' : 'pages'}` : 'Not attached yet' }}
                        </span>
                    </button>
                    <label class="flex shrink-0 items-center gap-2 text-sm">
                        <Checkbox :model-value="element.is_active" @update:model-value="toggleActive(element)" /> Show
                    </label>
                    <template v-if="!search">
                        <Button variant="ghost" size="icon" class="size-8" :disabled="i === 0" :aria-label="`Move “${element.question}” up`" @click="move(i, -1)">↑</Button>
                        <Button variant="ghost" size="icon" class="size-8" :disabled="i === list.length - 1" :aria-label="`Move “${element.question}” down`" @click="move(i, 1)">↓</Button>
                    </template>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="`Edit “${element.question}”`" @click="openEditor(element)"><Pencil class="size-4" /></Button>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete “${element.question}”`" @click="toDelete = element"><Trash2 class="size-4 text-destructive" /></Button>
                </li>
            </template>
        </draggable>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ editing ? 'Edit FAQ' : 'New FAQ' }}</DialogTitle>
                <DialogDescription>Short, direct answers work best for Google's FAQ results.</DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="save">
                <div class="grid gap-2">
                    <Label for="faq-question">Question <span class="text-destructive">*</span></Label>
                    <Input id="faq-question" v-model="form.question" maxlength="255" placeholder="How much does a local move cost?" :aria-invalid="!!form.errors.question" />
                    <p v-if="form.errors.question" class="text-sm text-destructive">{{ form.errors.question }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="faq-answer">Answer <span class="text-destructive">*</span></Label>
                    <RichTextEditor id="faq-answer" v-model="form.answer" :invalid="!!form.errors.answer" />
                    <p v-if="form.errors.answer" class="text-sm text-destructive">{{ form.errors.answer }}</p>
                </div>
                <label class="flex items-center gap-3 text-sm">
                    <Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" /> Show on the website
                </label>

                <div v-if="editing" class="rounded-lg border bg-muted/30 p-3 text-sm">
                    <p class="font-medium">Shown on</p>
                    <p v-if="usages === null" class="text-muted-foreground">Loading…</p>
                    <p v-else-if="!usages.length" class="text-muted-foreground">Not attached to any page yet.</p>
                    <ul v-else class="mt-1 space-y-1">
                        <li v-for="(u, i) in usages" :key="i" class="flex items-center gap-2">
                            {{ u.label }}
                            <a v-if="u.path" :href="u.path" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground" :aria-label="`View ${u.label}`"><ExternalLink class="size-3.5" /></a>
                        </li>
                    </ul>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="dialogOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">{{ editing ? 'Save' : 'Add FAQ' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete “${toDelete?.question}”?`"
        :description="toDelete?.attached_count ? `It is removed from ${toDelete.attached_count} ${toDelete.attached_count === 1 ? 'page' : 'pages'}.` : 'It is not attached to any page.'"
        confirm-label="Delete"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
