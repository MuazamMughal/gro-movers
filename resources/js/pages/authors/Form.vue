<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { slugify } from '@/lib/slug';
import { index, store, update } from '@/routes/admin/authors';

type AuthorData = { id: number; name: string; slug: string; job_title: string | null; bio: string | null; photo_media_id: number | null; posts_count: number };

const props = defineProps<{ author: AuthorData | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Authors', href: index() }] },
});

const isNew = computed(() => props.author === null);

const form = useForm({
    name: props.author?.name ?? '',
    slug: props.author?.slug ?? '',
    job_title: props.author?.job_title ?? '',
    bio: props.author?.bio ?? '',
    photo_media_id: props.author?.photo_media_id ?? null,
});

useUnsavedGuard(() => form.isDirty && !form.processing);

// The slug is internal (no author pages yet): keep it in sync with the name.
watch(
    () => form.name,
    (name) => (form.slug = slugify(name)),
);

function submit() {
    const options = { preserveScroll: true, onSuccess: () => form.defaults() };

    if (props.author) {
        form.put(update(props.author.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const title = computed(() => (isNew.value ? 'New author' : form.name || 'Unnamed author'));
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> All authors
                </Link>
                <Heading :title="title" class="mb-0!" />
            </div>
            <div class="flex items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button type="button" :disabled="form.processing" @click="submit">{{ isNew ? 'Create author' : 'Save' }}</Button>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="author-name">Name <span class="text-destructive">*</span></Label>
            <Input id="author-name" v-model="form.name" maxlength="100" placeholder="Maria Lopez" :aria-invalid="!!form.errors.name" />
            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="author-role">Role</Label>
            <Input id="author-role" v-model="form.job_title" maxlength="100" placeholder="Moving Coordinator, 10 years experience" />
            <p v-if="form.errors.job_title" class="text-sm text-destructive">{{ form.errors.job_title }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="author-bio">Short bio</Label>
            <Textarea id="author-bio" v-model="form.bio" rows="4" maxlength="1000" />
            <p class="text-xs text-muted-foreground">Shown in the "Written by" box under each post. {{ form.bio?.length ?? 0 }}/1000</p>
            <p v-if="form.errors.bio" class="text-sm text-destructive">{{ form.errors.bio }}</p>
        </div>

        <div class="grid gap-2">
            <span class="text-sm font-medium">Photo</span>
            <MediaPicker v-model="form.photo_media_id" preset="square" label="Author photo" />
        </div>
    </div>
</template>
