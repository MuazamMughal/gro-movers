<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Crop, ImageOff, RefreshCw, Search, Trash2, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import ImageCropper from '@/components/media/ImageCropper.vue';
import { formatBytes, type CropBox, type MediaItem, type MediaUsageItem, type Preset } from '@/components/media/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formData, HttpError, request } from '@/lib/http';
import { crop as cropRoute, destroy, index, replace, show, store, update } from '@/routes/admin/media';

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
};

const props = defineProps<{
    media: Paginated<MediaItem>;
    filters: { q: string | null; month: string | null; type: string | null };
    months: { value: string; label: string }[];
    presets: Record<string, Preset>;
    limits: { max_kb: number; max_megapixels: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Media Library', href: index() }],
    },
});

const presetsShared = computed(() => (usePage().props.mediaPresets as Record<string, Preset>) ?? props.presets);

// ---- Filters -------------------------------------------------------------
const q = ref(props.filters.q ?? '');
const month = ref(props.filters.month ?? '');
const type = ref(props.filters.type ?? '');

function applyFilters() {
    const query: Record<string, string> = {};

    if (q.value) query.q = q.value;
    if (month.value) query.month = month.value;
    if (type.value) query.type = type.value;

    router.get(index({ query }).url, {}, { preserveState: true, preserveScroll: true, replace: true });
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});
watch([month, type], applyFilters);

const selectClass =
    'border-input dark:bg-input/30 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';

// ---- Uploads (straight to the library, no crop; crop later per use) -----
const uploads = ref<{ name: string; progress: number; error?: string }[]>([]);
const dragging = ref(false);

async function uploadFiles(files: FileList | File[] | null | undefined) {
    const list = Array.from(files ?? []);

    for (const file of list) {
        const entry = { name: file.name, progress: 0 } as { name: string; progress: number; error?: string };
        uploads.value.push(entry);

        try {
            await request(store().url, {
                method: 'POST',
                body: formData({ file, preset: file.type === 'image/svg+xml' ? 'logo' : null }),
                onProgress: (p) => (entry.progress = p),
            });
            entry.progress = 100;
        } catch (e) {
            entry.error = e instanceof HttpError ? e.fieldError('file') : 'Upload failed.';
        }
    }

    const failed = uploads.value.filter((u) => u.error).length;
    toast[failed ? 'warning' : 'success'](
        failed ? `${list.length - failed} uploaded, ${failed} failed.` : `${list.length} image${list.length > 1 ? 's' : ''} uploaded.`,
    );
    uploads.value = uploads.value.filter((u) => u.error);
    router.reload({ only: ['media', 'months'] });
}

const onDrop = (e: DragEvent) => {
    dragging.value = false;
    void uploadFiles(e.dataTransfer?.files);
};

// ---- Details panel -------------------------------------------------------
const selected = ref<MediaItem | null>(null);
const usages = ref<MediaUsageItem[] | null>(null);
const details = ref({ alt: '', title: '', caption: '' });
const saving = ref(false);
const sheetOpen = computed({
    get: () => selected.value !== null,
    set: (v) => !v && (selected.value = null),
});

async function openDetails(item: MediaItem) {
    selected.value = item;
    usages.value = null;
    details.value = { alt: item.alt ?? '', title: item.title ?? '', caption: item.caption ?? '' };

    const res = await request<{ media: MediaItem; usages: MediaUsageItem[] }>(show(item.id).url);
    if (selected.value?.id === item.id) {
        selected.value = res.media;
        usages.value = res.usages;
    }
}

function refreshSelected(item: MediaItem) {
    selected.value = item;
    router.reload({ only: ['media'] });
}

async function saveDetails() {
    if (!selected.value) return;

    saving.value = true;

    try {
        const res = await request<{ media: MediaItem }>(update(selected.value.id).url, { method: 'PATCH', body: details.value });
        refreshSelected(res.media);
        toast.success('Image details saved.');
    } catch (e) {
        toast.error(e instanceof HttpError ? e.message : 'Could not save.');
    } finally {
        saving.value = false;
    }
}

const copyUrl = async () => {
    if (!selected.value) return;

    await navigator.clipboard.writeText(new URL(selected.value.url, window.location.origin).toString());
    toast.success('Image URL copied.');
};

// Re-crop in place (updates every page using the image)
const cropperOpen = ref(false);
const cropPreset = ref<string>('card');
const cropping = ref(false);

watch(selected, (item, previous) => {
    if (item && item.id !== previous?.id) {
        cropPreset.value = item.preset ?? 'card';
    }
});

async function applyCrop(box: CropBox) {
    if (!selected.value) return;

    cropping.value = true;

    try {
        const res = await request<{ media: MediaItem }>(cropRoute(selected.value.id).url, {
            method: 'POST',
            body: { preset: cropPreset.value, crop: box },
        });
        cropperOpen.value = false;
        refreshSelected(res.media);
        toast.success('Image re-cropped.');
    } catch (e) {
        toast.error(e instanceof HttpError ? e.message : 'Could not crop.');
    } finally {
        cropping.value = false;
    }
}

// Replace file (keeps every place it is used)
async function replaceFile(file: File | undefined) {
    if (!selected.value || !file) return;

    try {
        const res = await request<{ media: MediaItem }>(replace(selected.value.id).url, {
            method: 'POST',
            body: formData({ file }),
        });
        refreshSelected(res.media);
        toast.success('File replaced.');
    } catch (e) {
        toast.error(e instanceof HttpError ? e.fieldError('file') : 'Could not replace the file.');
    }
}

// Delete (blocked while in use)
const confirmDelete = ref(false);
const deleting = ref(false);

async function deleteSelected() {
    if (!selected.value) return;

    deleting.value = true;

    try {
        await request(destroy(selected.value.id).url, { method: 'DELETE' });
        toast.success('Image deleted.');
        selected.value = null;
        router.reload({ only: ['media', 'months'] });
    } catch (e) {
        if (e instanceof HttpError && e.status === 409) {
            usages.value = e.data.usages;
        }
        toast.error(e instanceof HttpError ? e.message : 'Could not delete.');
    } finally {
        deleting.value = false;
        confirmDelete.value = false;
    }
}

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(new Date(iso)) : '';
</script>

<template>
    <Head title="Media Library" />

    <div
        class="flex flex-col gap-6 p-4 md:p-6"
        @dragover.prevent="dragging = true"
        @dragleave.self.prevent="dragging = false"
        @drop.prevent="onDrop"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Media Library"
                description="Every image on the website. Images are cropped per use and served as WebP."
            />
            <Button as-child>
                <label class="cursor-pointer">
                    <Upload class="size-4" /> Upload images
                    <input
                        type="file"
                        class="sr-only"
                        multiple
                        accept="image/jpeg,image/png,image/webp,image/svg+xml"
                        @change="(e) => uploadFiles((e.target as HTMLInputElement).files)"
                    />
                </label>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="relative min-w-60 flex-1">
                <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                <Input v-model="q" class="pl-8" placeholder="Search file name, alt text or title" aria-label="Search media" />
            </div>
            <select v-model="month" :class="selectClass" aria-label="Filter by month">
                <option value="">All months</option>
                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select v-model="type" :class="selectClass" aria-label="Filter by type">
                <option value="">All types</option>
                <option value="image">Photos</option>
                <option value="svg">SVG</option>
            </select>
        </div>

        <div
            v-if="dragging"
            class="rounded-lg border-2 border-dashed border-primary bg-muted/50 py-10 text-center text-sm font-medium"
        >
            Drop images to upload
        </div>

        <ul v-if="uploads.length" class="space-y-1 text-sm">
            <li v-for="u in uploads" :key="u.name" class="flex justify-between gap-4">
                <span>{{ u.name }}</span>
                <span :class="u.error ? 'text-destructive' : 'text-muted-foreground'">{{ u.error ?? `${u.progress}%` }}</span>
            </li>
        </ul>

        <div v-if="!media.data.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <ImageOff class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No images{{ filters.q || filters.month || filters.type ? ' match these filters' : ' yet' }}</p>
            <p class="text-sm text-muted-foreground">
                Upload JPG, PNG or WebP (max {{ limits.max_kb / 1024 }} MB). Drag files anywhere on this page.
            </p>
        </div>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
            <button
                v-for="item in media.data"
                :key="item.id"
                type="button"
                class="group overflow-hidden rounded-lg border text-left transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="openDetails(item)"
            >
                <div class="aspect-[4/3] bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:16px_16px]">
                    <img :src="item.thumb_url" :alt="item.alt ?? ''" class="size-full object-contain" loading="lazy" />
                </div>
                <div class="space-y-1 p-2">
                    <p class="truncate text-xs font-medium">{{ item.filename }}</p>
                    <div class="flex flex-wrap gap-1">
                        <Badge v-if="item.preset" variant="secondary" class="text-[10px]">{{ presetsShared[item.preset]?.label ?? item.preset }}</Badge>
                        <Badge v-if="!item.alt" variant="outline" class="text-[10px] text-amber-600">No alt</Badge>
                    </div>
                </div>
            </button>
        </div>

        <nav v-if="media.links.length > 3" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
            <template v-for="link in media.links" :key="link.label">
                <Button v-if="link.url" :variant="link.active ? 'default' : 'ghost'" size="sm" as-child>
                    <Link :href="link.url" preserve-scroll><span v-html="link.label" /></Link>
                </Button>
                <span v-else class="px-2 text-sm text-muted-foreground" v-html="link.label" />
            </template>
            <span class="ml-auto text-sm text-muted-foreground">{{ media.from }}–{{ media.to }} of {{ media.total }}</span>
        </nav>
    </div>

    <Sheet v-model:open="sheetOpen">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <template v-if="selected">
                <SheetHeader>
                    <SheetTitle class="truncate">{{ selected.filename }}</SheetTitle>
                    <SheetDescription>
                        Uploaded {{ formatDate(selected.created_at) }} · original {{ selected.width }} × {{ selected.height }} ·
                        {{ formatBytes(selected.size) }}
                    </SheetDescription>
                </SheetHeader>

                <div class="space-y-6 px-4 pb-6">
                    <div class="overflow-hidden rounded-md border bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:16px_16px]">
                        <img :src="selected.url" :alt="selected.alt ?? ''" class="mx-auto max-h-64 object-contain" />
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-muted-foreground">Crop</dt>
                        <dd>{{ selected.preset ? presetsShared[selected.preset]?.label : 'Original ratio' }}</dd>
                        <dt class="text-muted-foreground">Served size</dt>
                        <dd>{{ selected.display_width }} × {{ selected.display_height }}</dd>
                        <dt class="text-muted-foreground">Versions</dt>
                        <dd>{{ selected.is_svg ? 'SVG (vector)' : `${selected.variants_count} files · ${formatBytes(selected.variants_size)}` }}</dd>
                    </dl>

                    <form class="space-y-4" @submit.prevent="saveDetails">
                        <div class="grid gap-2">
                            <Label for="media-alt">Alt text</Label>
                            <Input id="media-alt" v-model="details.alt" placeholder="Describe what the image shows" />
                            <p class="text-xs text-muted-foreground">Read by screen readers and search engines. Required for SEO.</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="media-title">Title</Label>
                            <Input id="media-title" v-model="details.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="media-caption">Caption</Label>
                            <Textarea id="media-caption" v-model="details.caption" rows="2" />
                        </div>
                        <Button :disabled="saving">Save details</Button>
                    </form>

                    <div class="flex flex-wrap gap-2 border-t pt-4">
                        <Button type="button" variant="outline" size="sm" @click="copyUrl"><Copy class="size-4" /> Copy URL</Button>
                        <div v-if="!selected.is_svg" class="flex w-full items-center gap-2">
                            <label for="crop-preset" class="text-sm text-muted-foreground">Crop as</label>
                            <select id="crop-preset" v-model="cropPreset" :class="[selectClass, 'h-8 flex-1']">
                                <option v-for="(p, key) in presetsShared" :key="key" :value="key">
                                    {{ p.label }}{{ p.width ? ` (${p.width}×${p.height})` : '' }}
                                </option>
                            </select>
                            <Button type="button" variant="outline" size="sm" @click="cropperOpen = true">
                                <Crop class="size-4" /> Re-crop
                            </Button>
                        </div>
                        <Button type="button" variant="outline" size="sm" as-child>
                            <label class="cursor-pointer">
                                <RefreshCw class="size-4" /> Replace file
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept="image/jpeg,image/png,image/webp,image/svg+xml"
                                    @change="(e) => replaceFile((e.target as HTMLInputElement).files?.[0])"
                                />
                            </label>
                        </Button>
                    </div>

                    <section class="space-y-2 border-t pt-4">
                        <h3 class="text-sm font-semibold">Used on</h3>
                        <p v-if="usages === null" class="text-sm text-muted-foreground">Checking…</p>
                        <p v-else-if="!usages.length" class="text-sm text-muted-foreground">Not used anywhere yet.</p>
                        <ul v-else class="space-y-1 text-sm">
                            <li v-for="(u, i) in usages" :key="i">
                                {{ u.label }} <span class="text-muted-foreground">· {{ u.field }}</span>
                            </li>
                        </ul>
                    </section>

                    <div class="border-t pt-4">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            :disabled="usages === null || usages.length > 0"
                            :title="usages?.length ? 'Remove the image from the pages above first' : undefined"
                            @click="confirmDelete = true"
                        >
                            <Trash2 class="size-4" /> Delete image
                        </Button>
                    </div>
                </div>
            </template>
        </SheetContent>
    </Sheet>

    <ImageCropper
        v-model:open="cropperOpen"
        :src="selected?.original_url ?? null"
        :preset="presetsShared[cropPreset] ?? null"
        :initial="selected?.preset === cropPreset ? selected?.crop : null"
        title="Re-crop image"
        confirm-label="Save crop"
        :processing="cropping"
        @confirm="applyCrop"
    />

    <ConfirmDialog
        v-model:open="confirmDelete"
        title="Delete this image?"
        description="The file and all its sizes are removed from the server. This cannot be undone."
        confirm-label="Delete image"
        destructive
        :processing="deleting"
        @confirm="deleteSelected"
    />
</template>
