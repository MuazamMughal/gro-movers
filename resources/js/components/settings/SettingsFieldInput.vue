<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SocialLinksInput from './SocialLinksInput.vue';
import type { HoursRow, SettingsField } from './types';

const props = defineProps<{
    field: SettingsField;
    /** Errors keyed by dotted path; includes item errors like "x.0.days". */
    errors: Record<string, string>;
}>();

const model = defineModel<any>();
const emit = defineEmits<{ clearSecret: [] }>();

const id = computed(() => props.field.key.replace(/\./g, '-'));
const error = computed(() => props.errors[props.field.key]);
const itemError = (suffix: string) =>
    props.errors[`${props.field.key}.${suffix}`];

const selectClass =
    'border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

// List helpers (email_list, hours) always work on a fresh array so v-model stays reactive.
const list = computed<any[]>(() => (Array.isArray(model.value) ? model.value : []));
const addItem = (item: unknown) => (model.value = [...list.value, item]);
const removeItem = (i: number) =>
    (model.value = list.value.filter((_, index) => index !== i));
const updateItem = (i: number, value: unknown) =>
    (model.value = list.value.map((item, index) => (index === i ? value : item)));

const hoursRows = computed(() => list.value as HoursRow[]);

const charCount = computed(() => String(model.value ?? '').length);
</script>

<template>
    <div class="grid gap-2">
        <label
            v-if="field.type !== 'toggle'"
            :for="id"
            class="text-sm leading-none font-medium"
        >
            {{ field.label }}
            <span v-if="field.required" class="text-destructive">*</span>
        </label>

        <!-- Plain inputs -->
        <Input
            v-if="['text', 'email', 'tel', 'url', 'number'].includes(field.type)"
            :id="id"
            v-model="model"
            :type="field.type === 'number' ? 'number' : field.type"
            :step="field.type === 'number' ? 'any' : undefined"
            :aria-invalid="!!error"
        />

        <template v-else-if="field.type === 'textarea' || field.type === 'code'">
            <Textarea
                :id="id"
                v-model="model"
                :rows="field.type === 'code' ? 6 : 3"
                :class="{ 'font-mono text-xs': field.type === 'code' }"
                :spellcheck="field.type !== 'code'"
                :aria-invalid="!!error"
            />
            <p
                v-if="field.max"
                class="text-right text-xs"
                :class="charCount > field.max ? 'text-destructive' : 'text-muted-foreground'"
            >
                {{ charCount }} / {{ field.max }}
            </p>
        </template>

        <div v-else-if="field.type === 'color'" class="flex items-center gap-2">
            <input
                :id="id"
                v-model="model"
                type="color"
                class="h-9 w-12 cursor-pointer rounded-md border bg-transparent p-1"
                :aria-label="`${field.label} color picker`"
            />
            <Input
                v-model="model"
                class="w-32 font-mono uppercase"
                maxlength="7"
                :aria-label="`${field.label} hex value`"
                :aria-invalid="!!error"
            />
        </div>

        <label
            v-else-if="field.type === 'toggle'"
            :for="id"
            class="flex items-center gap-3 text-sm font-medium"
        >
            <Checkbox :id="id" v-model="model" />
            {{ field.label }}
        </label>

        <select
            v-else-if="field.type === 'select'"
            :id="id"
            v-model="model"
            :class="selectClass"
        >
            <option :value="null">—</option>
            <option
                v-for="(label, value) in field.options"
                :key="value"
                :value="value"
            >
                {{ label }}
            </option>
        </select>

        <div v-else-if="field.type === 'secret'" class="flex items-center gap-2">
            <Input
                :id="id"
                v-model="model"
                type="password"
                autocomplete="new-password"
                :placeholder="field.is_set ? '•••••••• (saved, leave empty to keep)' : ''"
                :aria-invalid="!!error"
            />
            <Button
                v-if="field.is_set"
                type="button"
                variant="outline"
                size="sm"
                @click="emit('clearSecret')"
            >
                Remove
            </Button>
        </div>

        <div v-else-if="field.type === 'email_list'" class="grid gap-2">
            <div v-for="(email, i) in list" :key="i" class="flex gap-2">
                <Input
                    :model-value="email"
                    type="email"
                    :aria-label="`${field.label} ${i + 1}`"
                    :aria-invalid="!!itemError(String(i))"
                    @update:model-value="(v) => updateItem(i, v)"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Remove ${email || 'address'}`"
                    :disabled="list.length <= 1"
                    @click="removeItem(i)"
                >
                    <X class="size-4" />
                </Button>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                :disabled="list.length >= 10"
                @click="addItem('')"
            >
                <Plus class="size-4" /> Add address
            </Button>
        </div>

        <div v-else-if="field.type === 'hours'" class="grid gap-2">
            <div
                v-for="(row, i) in hoursRows"
                :key="i"
                class="flex gap-2"
            >
                <Input
                    :model-value="row.days"
                    class="w-40"
                    placeholder="Mon–Fri"
                    :aria-label="`Days, row ${i + 1}`"
                    :aria-invalid="!!itemError(`${i}.days`)"
                    @update:model-value="(v) => updateItem(i, { ...row, days: v })"
                />
                <Input
                    :model-value="row.hours"
                    placeholder="8:00 AM – 6:00 PM"
                    :aria-label="`Hours, row ${i + 1}`"
                    :aria-invalid="!!itemError(`${i}.hours`)"
                    @update:model-value="(v) => updateItem(i, { ...row, hours: v })"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Remove row ${i + 1}`"
                    @click="removeItem(i)"
                >
                    <X class="size-4" />
                </Button>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                :disabled="list.length >= 7"
                @click="addItem({ days: '', hours: '' })"
            >
                <Plus class="size-4" /> Add row
            </Button>
        </div>

        <SocialLinksInput
            v-else-if="field.type === 'social'"
            v-model="model"
            :networks="field.options ?? {}"
            :errors="errors"
            :error-prefix="field.key"
        />

        <p
            v-else-if="field.type === 'media'"
            class="rounded-md border border-dashed px-3 py-4 text-sm text-muted-foreground"
        >
            Image upload with cropping becomes available here once the media
            library is built.
        </p>

        <p v-if="field.help" class="text-xs text-muted-foreground">
            {{ field.help }}
        </p>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <p
            v-for="(message, path) in Object.fromEntries(
                Object.entries(errors).filter(
                    ([k]) =>
                        field.type !== 'social' &&
                        k.startsWith(`${field.key}.`),
                ),
            )"
            :key="path"
            class="text-sm text-destructive"
        >
            {{ message }}
        </p>
    </div>
</template>
