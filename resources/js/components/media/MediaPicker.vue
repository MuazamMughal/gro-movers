<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ImagePlus, Search, Upload, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formData, HttpError, request } from '@/lib/http';
import { browse, derive, show, store } from '@/routes/admin/media';
import ImageCropper from './ImageCropper.vue';
import type { CropBox, MediaItem, Preset } from './types';

const props = defineProps<{
    /** Crop preset key from config/media.php (hero, card, blog, square, logo). */
    preset: string;
    label?: string;
    /** No preview or buttons: the parent opens the dialog with the exposed open(). */
    bare?: boolean;
}>();

const emit = defineEmits<{ select: [item: MediaItem] }>();

const model = defineModel<number | null>({ default: null });

const page = usePage();
const presets = computed(() => page.props.mediaPresets as Record<string, Preset>);
const presetConfig = computed(() => presets.value[props.preset] ?? null);
const accept = computed(() => `image/jpeg,image/png,image/webp${presetConfig.value?.svg ? ',image/svg+xml' : ''}`);

const current = ref<MediaItem | null>(null);
const dialogOpen = ref(false);
const tabs = ['upload', 'library'] as const;
const tab = ref<(typeof tabs)[number]>('upload');

// Library browsing
const items = ref<MediaItem[]>([]);
const nextPage = ref<number | null>(1);
const search = ref('');
const loading = ref(false);

// Cropping
const cropperOpen = ref(false);
const cropSrc = ref<string | null>(null);
const pendingFile = ref<File | null>(null);
const pendingSource = ref<MediaItem | null>(null);
const busy = ref(false);
const progress = ref<number | null>(null);
const dragging = ref(false);

async function loadCurrent() {
    if (!model.value) {
        current.value = null;

        return;
    }

    if (current.value?.id === model.value) {
        return;
    }

    try {
        current.value = (await request<{ media: MediaItem }>(show(model.value).url)).media;
    } catch {
        current.value = null;
    }
}

onMounted(loadCurrent);
watch(model, loadCurrent);

async function loadLibrary(reset = false) {
    if (reset) {
        items.value = [];
        nextPage.value = 1;
    }

    if (!nextPage.value || loading.value) {
        return;
    }

    loading.value = true;

    try {
        const query: Record<string, string | number> = { page: nextPage.value };

        if (search.value) {
            query.q = search.value;
        }

        const res = await request<{ data: MediaItem[]; next_page: number | null }>(browse({ query }).url);
        items.value.push(...res.data);
        nextPage.value = res.next_page;
    } finally {
        loading.value = false;
    }
}

watch(tab, (t) => t === 'library' && items.value.length === 0 && loadLibrary(true));

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadLibrary(true), 300);
});

function select(item: MediaItem) {
    current.value = item;
    model.value = item.id;
    dialogOpen.value = false;
    cropperOpen.value = false;
    emit('select', item);

    if (!item.alt) {
        toast.warning('Add alt text to this image in the media library for SEO and accessibility.');
    }
}

function startCrop(src: string) {
    cropSrc.value = src;
    // Close the picker first: two stacked modal dialogs fight over focus and layering.
    dialogOpen.value = false;
    cropperOpen.value = true;
}

function onFile(file: File | undefined) {
    if (!file) {
        return;
    }

    pendingSource.value = null;
    pendingFile.value = file;

    if (file.type === 'image/svg+xml') {
        if (!presetConfig.value?.svg) {
            toast.error('SVG files can only be used as logos.');

            return;
        }

        void upload(null);

        return;
    }

    startCrop(URL.createObjectURL(file));
}

function onLibraryPick(item: MediaItem) {
    const fits = item.preset === props.preset || (item.is_svg && presetConfig.value?.svg);

    if (fits) {
        select(item);

        return;
    }

    if (item.is_svg) {
        toast.error('SVG files can only be used as logos.');

        return;
    }

    // Different crop needed: cut a copy so other pages using this image stay as they are.
    pendingFile.value = null;
    pendingSource.value = item;
    startCrop(item.original_url);
}

async function upload(crop: CropBox | null) {
    busy.value = true;
    progress.value = 0;

    try {
        let res: { media: MediaItem };

        if (pendingSource.value) {
            res = await request(derive(pendingSource.value.id).url, {
                method: 'POST',
                body: { preset: props.preset, crop },
            });
        } else {
            res = await request(store().url, {
                method: 'POST',
                body: formData({ file: pendingFile.value, preset: props.preset, crop }),
                onProgress: (p) => (progress.value = p),
            });
        }

        select(res.media);
    } catch (e) {
        toast.error(e instanceof HttpError ? e.fieldError('file') : 'Upload failed.');
    } finally {
        busy.value = false;
        progress.value = null;

        if (cropSrc.value?.startsWith('blob:')) {
            URL.revokeObjectURL(cropSrc.value);
        }
    }
}

function onDrop(e: DragEvent) {
    dragging.value = false;
    onFile(e.dataTransfer?.files?.[0]);
}

const clear = () => {
    model.value = null;
    current.value = null;
};

defineExpose({ open: () => (dialogOpen.value = true) });
</script>

<template>
    <div v-if="!bare" class="flex flex-wrap items-start gap-4">
        <div
            class="flex h-24 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:16px_16px]"
        >
            <img
                v-if="current"
                :src="current.thumb_url"
                :alt="current.alt ?? ''"
                class="max-h-full max-w-full object-contain"
            />
            <ImagePlus v-else class="size-6 text-muted-foreground" aria-hidden="true" />
        </div>

        <div class="space-y-2 text-sm">
            <p v-if="current" class="text-muted-foreground">
                {{ current.filename }}
                <span v-if="current.display_width"> · {{ current.display_width }} × {{ current.display_height }}</span>
                <span v-if="!current.alt" class="block text-amber-600 dark:text-amber-400">No alt text yet</span>
            </p>
            <p v-else class="text-muted-foreground">No image selected.</p>
            <div class="flex gap-2">
                <Button type="button" variant="outline" size="sm" @click="dialogOpen = true">
                    {{ current ? 'Change' : 'Choose image' }}
                </Button>
                <Button v-if="current" type="button" variant="ghost" size="sm" @click="clear">
                    <X class="size-4" /> Remove
                </Button>
            </div>
            <p v-if="presetConfig" class="text-xs text-muted-foreground">
                {{ presetConfig.label }}<template v-if="presetConfig.width">
                    · {{ presetConfig.width }} × {{ presetConfig.height }} px</template
                ><template v-if="presetConfig.svg"> · PNG, WebP or SVG</template>
            </p>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ label ?? 'Choose image' }}</DialogTitle>
                <DialogDescription>Upload a new image or reuse one from the media library.</DialogDescription>
            </DialogHeader>

            <div class="flex gap-1 border-b" role="tablist">
                <button
                    v-for="t in tabs"
                    :key="t"
                    type="button"
                    role="tab"
                    :aria-selected="tab === t"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                    :class="tab === t ? 'border-primary' : 'border-transparent text-muted-foreground'"
                    @click="tab = t"
                >
                    {{ t === 'upload' ? 'Upload new' : 'Media library' }}
                </button>
            </div>

            <label
                v-if="tab === 'upload'"
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-12 text-center text-sm transition-colors"
                :class="dragging ? 'border-primary bg-muted' : 'hover:bg-muted/50'"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <Upload class="size-6 text-muted-foreground" aria-hidden="true" />
                <span class="font-medium">Drop an image here or click to browse</span>
                <span class="text-muted-foreground">JPG, PNG or WebP up to 8 MB<template v-if="presetConfig?.svg">, or SVG</template></span>
                <span v-if="progress !== null" class="text-muted-foreground">Uploading… {{ progress }}%</span>
                <input
                    type="file"
                    class="sr-only"
                    :accept="accept"
                    :disabled="busy"
                    @change="(e) => onFile((e.target as HTMLInputElement).files?.[0])"
                />
            </label>

            <div v-else class="space-y-3">
                <div class="relative">
                    <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                    <Input v-model="search" class="pl-8" placeholder="Search by file name or alt text" aria-label="Search media" />
                </div>
                <p v-if="!items.length && !loading" class="py-8 text-center text-sm text-muted-foreground">No images found.</p>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <button
                        v-for="item in items"
                        :key="item.id"
                        type="button"
                        class="group relative aspect-square overflow-hidden rounded-md border bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="{ 'ring-2 ring-primary': item.id === model }"
                        :title="item.filename"
                        @click="onLibraryPick(item)"
                    >
                        <img :src="item.thumb_url" :alt="item.alt ?? ''" class="size-full object-cover" loading="lazy" />
                        <span
                            v-if="item.preset && item.preset !== preset && !item.is_svg"
                            class="absolute right-1 bottom-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white"
                            >{{ presets[item.preset]?.label ?? item.preset }}</span
                        >
                    </button>
                </div>
                <Button v-if="nextPage" type="button" variant="outline" class="w-full" :disabled="loading" @click="loadLibrary()">
                    {{ loading ? 'Loading…' : 'Load more' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <ImageCropper
        v-model:open="cropperOpen"
        :src="cropSrc"
        :preset="presetConfig"
        :processing="busy"
        :confirm-label="pendingSource ? 'Crop & use' : 'Upload & use'"
        @confirm="upload"
    />
</template>
