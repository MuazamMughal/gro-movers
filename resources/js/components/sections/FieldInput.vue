<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import IconPicker from './IconPicker.vue';
import LinkInput from './LinkInput.vue';
import RecordPicker from './RecordPicker.vue';
import RichTextEditor from './RichTextEditor.vue';
import type { FieldDef, SectionOptions } from './types';
import { clone } from './types';

const props = defineProps<{
    field: FieldDef;
    /** Full error key of this field, e.g. "sections.2.data.cards.0.title". */
    errorKey: string;
    errors: Record<string, string | undefined>;
    icons: Record<string, string>;
    options: SectionOptions;
}>();

const model = defineModel<any>();

const id = computed(() => props.errorKey.replace(/[^\w-]+/g, '-'));
const error = computed(() => props.errors[props.errorKey]);
const length = computed(() => (typeof model.value === 'string' ? model.value.length : 0));

const pickerNoun: Record<string, string> = { services: 'services', areas: 'areas', reviews: 'reviews', faqs: 'FAQs' };

// ---- Repeater helpers --------------------------------------------------------
const rows = computed<Record<string, any>[]>(() => (Array.isArray(model.value) ? model.value : []));

const emptyRow = () =>
    Object.fromEntries((props.field.fields ?? []).map((f) => [f.key, f.default ?? (f.type === 'toggle' ? false : null)]));

const addRow = () => (model.value = [...rows.value, emptyRow()]);
const removeRow = (i: number) => (model.value = rows.value.filter((_, idx) => idx !== i));
const duplicateRow = (i: number) => {
    const next = [...rows.value];
    next.splice(i + 1, 0, clone(rows.value[i]));
    model.value = next;
};
const moveRow = (i: number, delta: number) => {
    const next = [...rows.value];
    const [row] = next.splice(i, 1);
    next.splice(i + delta, 0, row);
    model.value = next;
};
const updateRow = (i: number, key: string, value: unknown) =>
    (model.value = rows.value.map((row, idx) => (idx === i ? { ...row, [key]: value } : row)));

const rowTitle = (row: Record<string, any>, i: number) => {
    const first = (props.field.fields ?? []).find((f) => ['text', 'textarea'].includes(f.type));
    const text = first ? row[first.key] : null;

    return text ? String(text) : `Item ${i + 1}`;
};

const rowErrorCount = (i: number) =>
    Object.keys(props.errors).filter((k) => k.startsWith(`${props.errorKey}.${i}.`) && props.errors[k]).length;

const selectClass = 'h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <div class="grid gap-2">
        <label v-if="field.type !== 'toggle'" :for="id" class="text-sm font-medium">
            {{ field.label }}
            <span v-if="field.required" class="text-destructive">*</span>
        </label>

        <Input
            v-if="field.type === 'text'"
            :id="id"
            v-model="model"
            :placeholder="field.placeholder"
            :maxlength="field.max"
            :aria-invalid="!!error"
        />

        <Input
            v-else-if="field.type === 'number'"
            :id="id"
            type="number"
            :min="field.min"
            :max="field.max"
            :model-value="model ?? ''"
            :aria-invalid="!!error"
            @update:model-value="(v: string | number) => (model = v === '' ? null : Number(v))"
        />

        <Textarea
            v-else-if="field.type === 'textarea' || field.type === 'code'"
            :id="id"
            v-model="model"
            :rows="field.type === 'code' ? 8 : 3"
            :class="{ 'font-mono text-xs': field.type === 'code' }"
            :spellcheck="field.type !== 'code'"
            :aria-invalid="!!error"
        />

        <RichTextEditor v-else-if="field.type === 'richtext'" :id="id" v-model="model" :invalid="!!error" />

        <MediaPicker v-else-if="field.type === 'image'" v-model="model" :preset="field.preset ?? 'card'" :label="field.label" />

        <LinkInput v-else-if="field.type === 'link'" :id="id" v-model="model" :errors="errors" :error-key="errorKey" />

        <label v-else-if="field.type === 'toggle'" class="flex items-center gap-3 text-sm font-medium">
            <Checkbox :id="id" :model-value="!!model" @update:model-value="(v) => (model = v === true)" />
            {{ field.label }}
        </label>

        <select v-else-if="field.type === 'select'" :id="id" v-model="model" :class="selectClass">
            <option v-for="(label, value) in field.options" :key="value" :value="value">{{ label }}</option>
        </select>

        <IconPicker v-else-if="field.type === 'icon'" :id="id" v-model="model" :icons="icons" />

        <RecordPicker
            v-else-if="field.type in pickerNoun"
            :id="id"
            v-model="model"
            :options="options[field.type as keyof SectionOptions] ?? []"
            :noun="pickerNoun[field.type]"
        />

        <div v-else-if="field.type === 'repeater'" class="space-y-2">
            <details
                v-for="(row, i) in rows"
                :key="i"
                class="group rounded-md border"
                :open="rows.length <= 3 || rowErrorCount(i) > 0"
            >
                <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-sm [&::-webkit-details-marker]:hidden">
                    <span class="flex-1 truncate font-medium">{{ rowTitle(row, i) }}</span>
                    <span v-if="rowErrorCount(i)" class="text-xs text-destructive">{{ rowErrorCount(i) }} error(s)</span>
                    <Button type="button" variant="ghost" size="icon" class="size-7" :disabled="i === 0" aria-label="Move up" @click.prevent="moveRow(i, -1)"><ArrowUp class="size-3.5" /></Button>
                    <Button type="button" variant="ghost" size="icon" class="size-7" :disabled="i === rows.length - 1" aria-label="Move down" @click.prevent="moveRow(i, 1)"><ArrowDown class="size-3.5" /></Button>
                    <Button type="button" variant="ghost" size="sm" class="h-7 text-xs" :disabled="!!field.max_items && rows.length >= field.max_items" @click.prevent="duplicateRow(i)">Duplicate</Button>
                    <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="Remove item" @click.prevent="removeRow(i)"><Trash2 class="size-3.5 text-destructive" /></Button>
                </summary>
                <div class="grid gap-4 border-t p-3">
                    <FieldInput
                        v-for="sub in field.fields"
                        :key="sub.key"
                        :field="sub"
                        :model-value="row[sub.key]"
                        :error-key="`${errorKey}.${i}.${sub.key}`"
                        :errors="errors"
                        :icons="icons"
                        :options="options"
                        @update:model-value="(v: unknown) => updateRow(i, sub.key, v)"
                    />
                </div>
            </details>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="!!field.max_items && rows.length >= field.max_items"
                @click="addRow"
            >
                <Plus class="size-4" /> Add {{ field.label.toLowerCase().replace(/s$/, '') }}
            </Button>
            <p v-if="field.max_items" class="text-xs text-muted-foreground">{{ rows.length }} / {{ field.max_items }}</p>
        </div>

        <p v-if="field.help" class="text-xs text-muted-foreground">{{ field.help }}</p>
        <p v-if="field.max && ['text', 'textarea'].includes(field.type) && length > field.max * 0.8" class="text-right text-xs text-muted-foreground">
            {{ length }} / {{ field.max }}
        </p>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
