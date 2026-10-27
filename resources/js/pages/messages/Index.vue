<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink, Mail, MailOpen, Phone, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { destroy, index, show, update } from '@/routes/admin/messages';

type Message = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    phone_href: string | null;
    message: string;
    source_url: string | null;
    is_read: boolean;
    created_at: string;
};

type Paginated<T> = { data: T[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };

const props = defineProps<{ messages: Paginated<Message>; filters: { q: string; unread: boolean }; unreadCount: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Contact Messages', href: index() }] },
});

const q = ref(props.filters.q);
const unread = ref(props.filters.unread);
const apply = () =>
    router.get(index().url, { ...(q.value ? { q: q.value } : {}), ...(unread.value ? { unread: '1' } : {}) }, { preserveState: true, preserveScroll: true, replace: true });

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch(unread, apply);

// ?open=ID (after opening a message) selects it.
const openId = ref<number | null>(Number(new URLSearchParams(location.search).get('open')) || null);
const open = computed(() => props.messages.data.find((m) => m.id === openId.value) ?? null);

function select(m: Message) {
    openId.value = m.id;
    if (!m.is_read) router.visit(show(m.id).url, { preserveScroll: true, preserveState: true });
}

const setRead = (m: Message, read: boolean) => router.patch(update(m.id).url, { read }, { preserveScroll: true });
const toDelete = ref<Message | null>(null);
</script>

<template>
    <Head title="Contact Messages" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading title="Contact Messages" :description="`${unreadCount} unread. Messages from the website contact form; each one is also emailed to your lead recipients.`" />

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-60 flex-1">
                <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                <Input v-model="q" class="pl-8" placeholder="Search name, email or message" aria-label="Search messages" />
            </div>
            <label class="flex items-center gap-2 text-sm"><Checkbox :model-value="unread" @update:model-value="(v) => (unread = v === true)" /> Unread only</label>
        </div>

        <div v-if="!messages.data.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <Mail class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No messages</p>
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <ul class="divide-y overflow-hidden rounded-xl border">
                <li v-for="m in messages.data" :key="m.id">
                    <button type="button" class="w-full px-4 py-3 text-left hover:bg-muted/40" :class="{ 'bg-muted': openId === m.id }" :aria-current="openId === m.id ? 'true' : undefined" @click="select(m)">
                        <span class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 truncate" :class="{ 'font-semibold': !m.is_read }">
                                <span v-if="!m.is_read" class="size-2 shrink-0 rounded-full bg-primary" aria-label="Unread" />{{ m.name }}
                            </span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ m.created_at }}</span>
                        </span>
                        <span class="mt-0.5 line-clamp-2 block text-sm text-muted-foreground">{{ m.message }}</span>
                    </button>
                </li>
            </ul>

            <article v-if="open" class="space-y-4 rounded-xl border p-5" aria-live="polite">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ open.name }}</h2>
                        <p class="text-sm text-muted-foreground">{{ open.created_at }}</p>
                    </div>
                    <div class="flex gap-1">
                        <Button variant="outline" size="sm" @click="setRead(open, !open.is_read)">
                            <component :is="open.is_read ? Mail : MailOpen" class="size-4" /> {{ open.is_read ? 'Mark unread' : 'Mark read' }}
                        </Button>
                        <Button variant="ghost" size="sm" class="text-destructive" @click="toDelete = open"><Trash2 class="size-4" /> Delete</Button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 text-sm">
                    <a :href="`mailto:${open.email}`" class="inline-flex items-center gap-1.5 rounded-md bg-muted/50 px-3 py-1.5 hover:bg-muted"><Mail class="size-4" /> {{ open.email }}</a>
                    <a v-if="open.phone" :href="open.phone_href ?? undefined" class="inline-flex items-center gap-1.5 rounded-md bg-muted/50 px-3 py-1.5 hover:bg-muted"><Phone class="size-4" /> {{ open.phone }}</a>
                    <a v-if="open.source_url" :href="open.source_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted"><ExternalLink class="size-4" /> Sent from this page</a>
                </div>
                <p class="whitespace-pre-wrap">{{ open.message }}</p>
            </article>
            <p v-else class="hidden items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground lg:flex">Select a message to read it.</p>
        </div>

        <nav v-if="messages.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Pagination">
            <Button variant="outline" size="sm" :disabled="!messages.prev_page_url" @click="messages.prev_page_url && router.visit(messages.prev_page_url)">← Newer</Button>
            <span class="text-muted-foreground">Page {{ messages.current_page }} of {{ messages.last_page }}</span>
            <Button variant="outline" size="sm" :disabled="!messages.next_page_url" @click="messages.next_page_url && router.visit(messages.next_page_url)">Older →</Button>
        </nav>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete the message from ${toDelete?.name}?`"
        description="It moves to the trash."
        confirm-label="Delete"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="toDelete && router.delete(destroy(toDelete.id), { onFinish: () => (toDelete = null) })"
    />
</template>
