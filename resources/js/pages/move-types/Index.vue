<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, GripVertical, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { destroy, index, reorder, store, update } from '@/routes/admin/move-types';

type MoveType = { id: number; name: string; sort: number; is_active: boolean; leads_count: number };

const props = defineProps<{ moveTypes: MoveType[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Move Types', href: index() }] },
});

const list = ref(props.moveTypes.map((m) => ({ ...m, draft: m.name })));
watch(() => props.moveTypes, (m) => (list.value = m.map((x) => ({ ...x, draft: x.name }))));

const addForm = useForm({ name: '' });
const add = () => addForm.post(store().url, { preserveScroll: true, onSuccess: () => addForm.reset() });

const save = (item: MoveType & { draft: string }, patch: Partial<MoveType> = {}) =>
    router.put(update(item.id).url, { name: item.draft, is_active: item.is_active, ...patch }, { preserveScroll: true });

const saveOrder = () => router.post(reorder().url, { ids: list.value.map((m) => m.id) }, { preserveScroll: true });

function move(i: number, delta: number) {
    const next = [...list.value];
    const [item] = next.splice(i, 1);
    next.splice(i + delta, 0, item);
    list.value = next;
    saveOrder();
}

const toDelete = ref<MoveType | null>(null);
const confirmDelete = () =>
    toDelete.value && router.delete(destroy(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
</script>

<template>
    <Head title="Move Types" />

    <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
        <Heading title="Move Types" description="The options in the quote form's “Type of Move” dropdown, in this order." />

        <form class="flex gap-2" @submit.prevent="add">
            <Input v-model="addForm.name" placeholder="e.g. Senior Move" aria-label="New move type" maxlength="60" />
            <Button :disabled="addForm.processing || !addForm.name.trim()"><Plus class="size-4" /> Add</Button>
        </form>
        <p v-if="addForm.errors.name" class="-mt-4 text-sm text-destructive">{{ addForm.errors.name }}</p>

        <draggable v-model="list" item-key="id" handle=".drag-handle" :animation="150" tag="ul" class="divide-y rounded-xl border" @end="saveOrder">
            <template #item="{ element, index }">
                <li class="flex items-center gap-2 px-3 py-2" :class="{ 'opacity-60': !element.is_active }">
                    <span class="drag-handle cursor-grab text-muted-foreground" aria-hidden="true" title="Drag to reorder"><GripVertical class="size-4" /></span>
                    <Input v-model="element.draft" class="h-8 flex-1" :aria-label="`Name of ${element.name}`" maxlength="60" @keydown.enter.prevent="save(element)" />
                    <Button v-if="element.draft !== element.name" type="button" size="icon" class="size-8" :aria-label="`Save ${element.draft}`" @click="save(element)"><Check class="size-4" /></Button>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="element.is_active" @update:model-value="(v) => save(element, { is_active: v === true })" />
                        Active
                    </label>
                    <span class="hidden w-20 text-right text-xs text-muted-foreground sm:block">{{ element.leads_count }} {{ element.leads_count === 1 ? 'lead' : 'leads' }}</span>
                    <Button variant="ghost" size="icon" class="size-8" :disabled="index === 0" :aria-label="`Move ${element.name} up`" @click="move(index, -1)">↑</Button>
                    <Button variant="ghost" size="icon" class="size-8" :disabled="index === list.length - 1" :aria-label="`Move ${element.name} down`" @click="move(index, 1)">↓</Button>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${element.name}`" @click="toDelete = element"><Trash2 class="size-4 text-destructive" /></Button>
                </li>
            </template>
        </draggable>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Remove “${toDelete?.name}”?`"
        description="It disappears from the quote form. Leads that already chose it keep the name."
        confirm-label="Remove"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
