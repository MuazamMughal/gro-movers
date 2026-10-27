<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, index, store, update } from '@/routes/admin/tags';

type TagRow = { id: number; name: string; slug: string; posts_count: number };

const props = defineProps<{ tags: TagRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tags', href: index() }] },
});

const list = ref(props.tags.map((t) => ({ ...t, draft: t.name })));
watch(() => props.tags, (t) => (list.value = t.map((x) => ({ ...x, draft: x.name }))));

const addForm = useForm({ name: '' });
const add = () => addForm.post(store().url, { preserveScroll: true, onSuccess: () => addForm.reset() });

const renameError = ref<{ id: number; message: string } | null>(null);
const save = (tag: TagRow & { draft: string }) =>
    router.put(update(tag.id).url, { name: tag.draft }, {
        preserveScroll: true,
        onSuccess: () => (renameError.value = null),
        onError: (e) => (renameError.value = { id: tag.id, message: e.name ?? 'Could not rename the tag.' }),
    });

const toDelete = ref<TagRow | null>(null);
const confirmDelete = () =>
    toDelete.value && router.delete(destroy(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
</script>

<template>
    <Head title="Tags" />

    <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
        <Heading title="Tags" description="Topic labels shown at the end of posts. Add new tags while writing a post; rename or remove them here." />

        <form class="flex gap-2" @submit.prevent="add">
            <Input v-model="addForm.name" placeholder="e.g. Packing Tips" aria-label="New tag" maxlength="40" />
            <Button :disabled="addForm.processing || !addForm.name.trim()"><Plus class="size-4" /> Add</Button>
        </form>
        <p v-if="addForm.errors.name" class="-mt-4 text-sm text-destructive">{{ addForm.errors.name }}</p>

        <p v-if="!list.length" class="rounded-xl border border-dashed py-12 text-center text-sm text-muted-foreground">No tags yet.</p>

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="tag in list" :key="tag.id" class="px-3 py-2">
                <div class="flex items-center gap-2">
                    <Input v-model="tag.draft" class="h-8 flex-1" :aria-label="`Name of ${tag.name}`" maxlength="40" @keydown.enter.prevent="save(tag)" />
                    <Button v-if="tag.draft !== tag.name" type="button" size="icon" class="size-8" :aria-label="`Save ${tag.draft}`" @click="save(tag)"><Check class="size-4" /></Button>
                    <span class="w-20 text-right text-xs text-muted-foreground">{{ tag.posts_count }} {{ tag.posts_count === 1 ? 'post' : 'posts' }}</span>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${tag.name}`" @click="toDelete = tag"><Trash2 class="size-4 text-destructive" /></Button>
                </div>
                <p v-if="renameError?.id === tag.id" class="mt-1 text-sm text-destructive">{{ renameError.message }}</p>
            </li>
        </ul>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete the tag “${toDelete?.name}”?`"
        :description="toDelete?.posts_count ? `It is removed from ${toDelete.posts_count} ${toDelete.posts_count === 1 ? 'post' : 'posts'}. The posts stay.` : 'No posts use it.'"
        confirm-label="Delete"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
