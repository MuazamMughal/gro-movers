<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import SettingsFieldInput from '@/components/settings/SettingsFieldInput.vue';
import ThemePreview from '@/components/settings/ThemePreview.vue';
import type { SettingsField } from '@/components/settings/types';
import { Button } from '@/components/ui/button';
import {
    clearSecret,
    edit,
    index,
    testEmail,
    update,
} from '@/routes/admin/settings';

const props = defineProps<{
    tab: string;
    tabs: Record<string, string>;
    description: string;
    fields: SettingsField[];
    values: Record<string, any>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: index() }],
    },
});

/*
 * useForm treats dots in keys as nested paths, so form keys use "__" instead
 * ("general__company_name") and are nested on submit for Laravel's validation.
 */
const formKey = (key: string) => key.replaceAll('.', '__');

function nest(flat: Record<string, unknown>): Record<string, any> {
    const out: Record<string, any> = {};

    for (const [key, value] of Object.entries(flat)) {
        const parts = key.split('__');
        let node = out;

        parts.slice(0, -1).forEach((p) => (node = node[p] ??= {}));
        node[parts[parts.length - 1]] = value;
    }

    return out;
}

const form = useForm<Record<string, any>>(
    Object.fromEntries(
        Object.entries(props.values).map(([key, value]) => [formKey(key), value]),
    ),
).transform(nest);

const errors = computed(() => form.errors as Record<string, string>);

const submit = () =>
    form.put(update(props.tab).url, {
        preserveScroll: true,
        onSuccess: () => {
            // Never keep a typed secret in the page after saving.
            props.fields
                .filter((f) => f.type === 'secret')
                .forEach((f) => (form[formKey(f.key)] = null));
        },
    });

const removeSecret = (key: string) =>
    router.delete(clearSecret().url, { data: { key }, preserveScroll: true });

const sendTest = () => router.post(testEmail().url, {}, { preserveScroll: true });

const themeColors = computed(() => ({
    primary: form.theme__primary,
    secondary: form.theme__secondary,
    tertiary: form.theme__tertiary,
    muted: form.theme__muted,
    border: form.theme__border,
}));

const widthClasses: Record<NonNullable<SettingsField['width']>, string> = {
    half: 'sm:col-span-3',
    third: 'sm:col-span-2',
};

const widthClass = (field: SettingsField) =>
    field.width ? widthClasses[field.width] : 'sm:col-span-6';
</script>

<template>
    <Head :title="`${tabs[tab]} settings`" />

    <div class="px-4 py-6 md:px-6">
        <Heading title="Settings" description="Site-wide content and configuration." />

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-12">
            <nav class="flex gap-1 overflow-x-auto lg:w-48 lg:flex-col" aria-label="Settings sections">
                <Button
                    v-for="(label, key) in tabs"
                    :key="key"
                    variant="ghost"
                    :class="['justify-start', { 'bg-muted': key === tab }]"
                    :aria-current="key === tab ? 'page' : undefined"
                    as-child
                >
                    <Link :href="edit(String(key))" preserve-scroll>{{ label }}</Link>
                </Button>
            </nav>

            <div class="flex min-w-0 flex-1 flex-col gap-8 xl:flex-row">
                <form class="max-w-2xl flex-1 space-y-6" novalidate @submit.prevent="submit">
                    <Heading variant="small" :title="tabs[tab]" :description="description" />

                    <div class="grid gap-x-4 gap-y-6 sm:grid-cols-6">
                        <template v-for="field in fields" :key="field.key">
                            <h3
                                v-if="field.group"
                                class="border-t pt-6 text-sm font-semibold sm:col-span-6"
                            >
                                {{ field.group }}
                            </h3>
                            <SettingsFieldInput
                                v-model="form[formKey(field.key)]"
                                :class="widthClass(field)"
                                :field="field"
                                :errors="errors"
                                @clear-secret="removeSecret(field.key)"
                            />
                        </template>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t pt-6">
                        <Button :disabled="form.processing">Save {{ tabs[tab] }} settings</Button>
                        <Button
                            v-if="tab === 'email'"
                            type="button"
                            variant="outline"
                            :disabled="form.processing || form.isDirty"
                            :title="form.isDirty ? 'Save your changes first' : undefined"
                            @click="sendTest"
                        >
                            <Send class="size-4" /> Send test email
                        </Button>
                        <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                    </div>
                </form>

                <aside v-if="tab === 'theme'" class="xl:w-80">
                    <h3 class="mb-3 text-sm font-semibold">Preview</h3>
                    <ThemePreview :colors="themeColors" />
                </aside>
            </div>
        </div>
    </div>
</template>
