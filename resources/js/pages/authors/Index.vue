<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UserRound } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/admin/authors';

type AuthorRow = { id: number; name: string; job_title: string | null; posts_count: number; thumb: string | null };

defineProps<{ authors: AuthorRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Authors', href: index() }] },
});

const toDelete = ref<AuthorRow | null>(null);
const confirmDelete = () =>
    toDelete.value && router.delete(destroy(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
</script>

<template>
    <Head title="Authors" />

    <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Authors" description="Shown in each post's byline and author box. A real name, role and bio help Google trust the content (E-E-A-T)." />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> New author</Link>
            </Button>
        </div>

        <div v-if="!authors.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <UserRound class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No authors yet</p>
        </div>

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="author in authors" :key="author.id" class="flex items-center gap-3 px-3 py-2.5">
                <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted">
                    <img v-if="author.thumb" :src="author.thumb" alt="" class="size-full object-cover" />
                    <UserRound v-else class="size-5 text-muted-foreground" aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1">
                    <Link :href="edit(author.id)" class="font-medium hover:underline">{{ author.name }}</Link>
                    <p class="text-xs text-muted-foreground">{{ author.job_title ?? 'No role set' }} · {{ author.posts_count }} {{ author.posts_count === 1 ? 'post' : 'posts' }}</p>
                </div>
                <Button variant="ghost" size="icon" class="size-8" as-child>
                    <Link :href="edit(author.id)" :aria-label="`Edit ${author.name}`"><Pencil class="size-4" /></Link>
                </Button>
                <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${author.name}`" @click="toDelete = author"><Trash2 class="size-4 text-destructive" /></Button>
            </li>
        </ul>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete “${toDelete?.name}”?`"
        :description="toDelete?.posts_count ? `Their ${toDelete.posts_count} ${toDelete.posts_count === 1 ? 'post stays' : 'posts stay'} on the website without an author.` : 'They have no posts.'"
        confirm-label="Delete"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
