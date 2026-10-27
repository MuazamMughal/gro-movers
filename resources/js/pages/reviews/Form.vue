<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { destroy, index, store, update } from '@/routes/admin/reviews';

type ReviewData = {
    id: number;
    name: string;
    city: string | null;
    rating: number;
    body: string;
    source: string;
    reviewed_at: string | null;
    photo_media_id: number | null;
    area_id: number | null;
    service_id: number | null;
    is_featured: boolean;
    is_active: boolean;
};

type Option = { id: number; name: string };

const props = defineProps<{
    review: ReviewData | null;
    sources: Record<string, string>;
    areas: Option[];
    services: Option[];
    today: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reviews', href: index() }] },
});

const isNew = computed(() => props.review === null);

const form = useForm({
    name: props.review?.name ?? '',
    city: props.review?.city ?? '',
    rating: props.review?.rating ?? 5,
    body: props.review?.body ?? '',
    source: props.review?.source ?? 'google',
    reviewed_at: props.review?.reviewed_at ?? props.today,
    photo_media_id: props.review?.photo_media_id ?? null,
    area_id: props.review?.area_id ?? null,
    service_id: props.review?.service_id ?? null,
    is_featured: props.review?.is_featured ?? false,
    is_active: props.review?.is_active ?? true,
});

useUnsavedGuard(() => form.isDirty && !form.processing);

function submit() {
    form.transform((data) => ({ ...data, reviewed_at: data.reviewed_at || null }));

    const options = { preserveScroll: true, onSuccess: () => form.defaults() };

    if (props.review) {
        form.put(update(props.review.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const hover = ref(0);
const stars = [1, 2, 3, 4, 5];
const confirmingDelete = ref(false);
const deleteReview = () => props.review && router.delete(destroy(props.review.id));

const title = computed(() => (isNew.value ? 'New review' : `Review from ${form.name || '…'}`));
const selectClass = 'h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> All reviews
                </Link>
                <Heading :title="title" class="mb-0!" />
            </div>
            <div class="flex items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button type="button" :disabled="form.processing" @click="submit">{{ isNew ? 'Add review' : 'Save' }}</Button>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="review-name">Customer name <span class="text-destructive">*</span></Label>
                <Input id="review-name" v-model="form.name" maxlength="100" placeholder="Jessica M." :aria-invalid="!!form.errors.name" />
                <p class="text-xs text-muted-foreground">First name and last initial is common and respects privacy.</p>
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="grid gap-2">
                <Label for="review-city">Customer's city</Label>
                <Input id="review-city" v-model="form.city" maxlength="100" placeholder="Plano, TX" />
                <p v-if="form.errors.city" class="text-sm text-destructive">{{ form.errors.city }}</p>
            </div>
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Rating <span class="text-destructive">*</span></legend>
            <div class="flex gap-1" role="radiogroup" aria-label="Rating" @mouseleave="hover = 0">
                <button
                    v-for="i in stars"
                    :key="i"
                    type="button"
                    role="radio"
                    :aria-checked="form.rating === i"
                    :aria-label="`${i} ${i === 1 ? 'star' : 'stars'}`"
                    class="rounded p-0.5 text-amber-500 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @mouseenter="hover = i"
                    @click="form.rating = i"
                >
                    <Star class="size-7" :class="i <= (hover || form.rating) ? 'fill-current' : 'opacity-30'" />
                </button>
                <span class="ml-2 self-center text-sm text-muted-foreground">{{ form.rating }} / 5</span>
            </div>
            <p v-if="form.errors.rating" class="text-sm text-destructive">{{ form.errors.rating }}</p>
        </fieldset>

        <div class="grid gap-2">
            <Label for="review-body">Review text <span class="text-destructive">*</span></Label>
            <Textarea id="review-body" v-model="form.body" rows="5" maxlength="3000" :aria-invalid="!!form.errors.body" />
            <p class="text-xs text-muted-foreground">Copy the customer's words as written. {{ form.body.length }}/3000</p>
            <p v-if="form.errors.body" class="text-sm text-destructive">{{ form.errors.body }}</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="review-source">Source <span class="text-destructive">*</span></Label>
                <select id="review-source" v-model="form.source" :class="selectClass">
                    <option v-for="(label, value) in sources" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="form.errors.source" class="text-sm text-destructive">{{ form.errors.source }}</p>
            </div>
            <div class="grid gap-2">
                <Label for="review-date">Review date</Label>
                <Input id="review-date" v-model="form.reviewed_at" type="date" :max="today" :aria-invalid="!!form.errors.reviewed_at" />
                <p v-if="form.errors.reviewed_at" class="text-sm text-destructive">{{ form.errors.reviewed_at }}</p>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="review-area">Show on city page</Label>
                <select id="review-area" v-model="form.area_id" :class="selectClass">
                    <option :value="null">None</option>
                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <p class="text-xs text-muted-foreground">Review sliders on that city's pages show its own reviews first.</p>
            </div>
            <div class="grid gap-2">
                <Label for="review-service">Service</Label>
                <select id="review-service" v-model="form.service_id" :class="selectClass">
                    <option :value="null">None</option>
                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p class="text-xs text-muted-foreground">Shown first on that service's page.</p>
            </div>
        </div>

        <div class="grid gap-2">
            <span class="text-sm font-medium">Customer photo (optional)</span>
            <MediaPicker v-model="form.photo_media_id" preset="square" label="Customer photo" />
            <p class="text-xs text-muted-foreground">Only use a photo the customer agreed to share.</p>
        </div>

        <fieldset class="space-y-3">
            <legend class="mb-1 text-sm font-medium">Visibility</legend>
            <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" /> Show on the website</label>
            <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.is_featured" @update:model-value="(v) => (form.is_featured = v === true)" /> Featured (shown first in review sliders)</label>
        </fieldset>

        <div v-if="review">
            <Button type="button" variant="ghost" class="text-destructive" @click="confirmingDelete = true"><Trash2 class="size-4" /> Delete review</Button>
        </div>
    </div>

    <ConfirmDialog
        :open="confirmingDelete"
        :title="`Delete the review from ${form.name}?`"
        description="It is removed from the website."
        confirm-label="Delete"
        destructive
        @update:open="(o) => (confirmingDelete = o)"
        @confirm="deleteReview"
    />
</template>
