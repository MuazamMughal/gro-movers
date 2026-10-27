<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/admin/roles';

const props = defineProps<{
    role: { id: number; name: string; description: string | null; permissions: string[] } | null;
    groups: Record<string, Record<string, string>>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const title = computed(() => (props.role ? `Edit ${props.role.name}` : 'Add role'));

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permissions: [...(props.role?.permissions ?? [])],
});

const toggleGroup = (keys: string[], on: boolean) => {
    form.permissions = on ? [...new Set([...form.permissions, ...keys])] : form.permissions.filter((p) => !keys.includes(p));
};
const groupState = (keys: string[]) => keys.filter((k) => form.permissions.includes(k)).length;

const submit = () => (props.role ? form.put(update(props.role.id).url) : form.post(store().url));
</script>

<template>
    <Head :title="title" />

    <form class="flex max-w-3xl flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading :title="title" description="Tick what this role may see and change. Users and Roles stay with Administrators." />

        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" required maxlength="60" autocomplete="off" />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
            <Textarea id="description" v-model="form.description" rows="2" maxlength="255" />
            <InputError :message="form.errors.description" />
        </div>

        <fieldset v-for="(items, group) in groups" :key="group" class="rounded-xl border p-4">
            <legend class="px-1 text-sm font-semibold">{{ group }}</legend>
            <label class="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                <input
                    type="checkbox"
                    class="size-4 accent-primary"
                    :checked="groupState(Object.keys(items)) === Object.keys(items).length"
                    :indeterminate="groupState(Object.keys(items)) > 0 && groupState(Object.keys(items)) < Object.keys(items).length"
                    @change="toggleGroup(Object.keys(items), ($event.target as HTMLInputElement).checked)"
                />
                Select all
            </label>
            <div class="grid gap-2 sm:grid-cols-2">
                <label v-for="(text, key) in items" :key="key" class="flex items-start gap-2 rounded-md border p-2.5 text-sm hover:bg-muted/50">
                    <input v-model="form.permissions" type="checkbox" :value="key" class="mt-0.5 size-4 accent-primary" />
                    <span>{{ text }}</span>
                </label>
            </div>
        </fieldset>
        <InputError :message="form.errors.permissions" />

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{ role ? 'Save role' : 'Create role' }}</Button>
            <Button variant="outline" as-child><Link :href="index()">Cancel</Link></Button>
        </div>
    </form>
</template>
