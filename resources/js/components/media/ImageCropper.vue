<script setup lang="ts">
import { RotateCcw, RotateCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CropBox, Preset } from './types';

const props = defineProps<{
    /** Image to crop: an object URL for a new file or the media original URL. */
    src: string | null;
    preset: Preset | null;
    /** Existing crop to start from (re-crop). */
    initial?: CropBox | null;
    title?: string;
    confirmLabel?: string;
    processing?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [crop: CropBox] }>();

const cropper = ref<InstanceType<typeof Cropper> | null>(null);
const size = ref<{ w: number; h: number } | null>(null);

const aspectRatio = computed(() =>
    props.preset?.width && props.preset?.height ? props.preset.width / props.preset.height : undefined,
);

const tooSmall = computed(
    () => !!(props.preset?.width && size.value && size.value.w < props.preset.width),
);

watch(open, (isOpen) => !isOpen && (size.value = null));

const defaultSize = ({ imageSize }: { imageSize: { width: number; height: number } }) =>
    props.initial && !props.initial.rotate
        ? { width: props.initial.w, height: props.initial.h }
        : { width: imageSize.width, height: imageSize.height };

const defaultPosition = () =>
    props.initial && !props.initial.rotate ? { left: props.initial.x, top: props.initial.y } : undefined;

const onChange = ({ coordinates }: { coordinates: { width: number; height: number } }) =>
    (size.value = { w: Math.round(coordinates.width), h: Math.round(coordinates.height) });

const rotate = (angle: number) => cropper.value?.rotate(angle);

const confirm = () => {
    const result = cropper.value?.getResult();

    if (!result) {
        return;
    }

    const { left, top, width, height } = result.coordinates;
    const turn = (((result.image.transforms.rotate % 360) + 360) % 360) as 0 | 90 | 180 | 270;

    emit('confirm', {
        x: Math.round(left),
        y: Math.round(top),
        w: Math.round(width),
        h: Math.round(height),
        rotate: turn,
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[95vh] overflow-y-auto sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ title ?? 'Crop image' }}</DialogTitle>
                <DialogDescription>
                    <template v-if="preset?.width && preset?.height">
                        {{ preset.label }}: output {{ preset.width }} × {{ preset.height }} px. Drag the frame to choose
                        what is shown.
                    </template>
                    <template v-else>Free crop. Trim the edges to choose what is shown.</template>
                </DialogDescription>
            </DialogHeader>

            <div class="h-[60vh] min-h-72 overflow-hidden rounded-md bg-neutral-900">
                <Cropper
                    v-if="src && open"
                    ref="cropper"
                    class="h-full"
                    :src="src"
                    :stencil-props="{ aspectRatio }"
                    :default-size="defaultSize"
                    :default-position="defaultPosition"
                    :canvas="false"
                    image-restriction="fit-area"
                    @change="onChange"
                />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <div class="flex gap-1">
                    <Button type="button" variant="outline" size="icon" aria-label="Rotate left" @click="rotate(-90)">
                        <RotateCcw class="size-4" />
                    </Button>
                    <Button type="button" variant="outline" size="icon" aria-label="Rotate right" @click="rotate(90)">
                        <RotateCw class="size-4" />
                    </Button>
                </div>
                <p v-if="size" :class="tooSmall ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                    Selection {{ size.w }} × {{ size.h }} px
                    <template v-if="tooSmall"> · smaller than {{ preset?.width }} px wide, it may look blurry</template>
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button type="button" variant="secondary" @click="open = false">Cancel</Button>
                <Button type="button" :disabled="processing" @click="confirm">{{ confirmLabel ?? 'Crop & save' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
