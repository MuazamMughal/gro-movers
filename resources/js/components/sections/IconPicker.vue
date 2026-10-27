<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const props = defineProps<{ icons: Record<string, string>; id?: string }>();
const model = defineModel<string | null>({ default: null });

const open = ref(false);
const search = ref('');

const filtered = computed(() =>
    Object.entries(props.icons).filter(([name]) => name.includes(search.value.trim().toLowerCase())),
);

const pick = (name: string) => {
    model.value = name;
    open.value = false;
};
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            :id="id"
            type="button"
            class="flex h-9 min-w-28 items-center gap-2 rounded-md border px-3 text-sm shadow-xs hover:bg-muted"
            @click="open = true"
        >
            <span v-if="model && icons[model]" class="size-5 [&>svg]:size-5" v-html="icons[model]" />
            <span>{{ model ?? 'Choose icon' }}</span>
        </button>
        <Button v-if="model" type="button" variant="ghost" size="icon" aria-label="Remove icon" @click="model = null">
            <X class="size-4" />
        </Button>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Choose an icon</DialogTitle>
                <DialogDescription>Icons are drawn in the accent color on the website.</DialogDescription>
            </DialogHeader>
            <Input v-model="search" placeholder="Search icons (truck, box, star…)" aria-label="Search icons" />
            <div class="grid max-h-80 grid-cols-6 gap-1 overflow-y-auto sm:grid-cols-8">
                <button
                    v-for="[name, svg] in filtered"
                    :key="name"
                    type="button"
                    class="flex aspect-square items-center justify-center rounded-md border hover:bg-muted [&>span>svg]:size-6"
                    :class="{ 'border-primary bg-muted': model === name }"
                    :title="name"
                    :aria-label="name"
                    @click="pick(name)"
                >
                    <span v-html="svg" />
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
