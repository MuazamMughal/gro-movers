<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail, MapPin, Phone, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, update } from '@/routes/admin/leads';
import { destroy as destroyNote, store as storeNote } from '@/routes/admin/leads/notes';

/** One side of the move: the full address the visitor chose, its parts (city, county, ZIP…) and map links. */
type Address = { address: string; parts: Record<string, string>; map: string; osm: string | null };

type Lead = {
    id: number;
    full_name: string;
    email: string;
    phone: string;
    phone_href: string;
    from: Address;
    to: Address;
    move_date: string;
    days_until: number;
    move_type_name: string;
    status: string;
    source_url: string | null;
    referrer: string | null;
    utm: Record<string, string> | null;
    ip: string | null;
    user_agent: string | null;
    created_at: string;
    notes: { id: number; body: string; author: string; created_at: string }[];
};

const props = defineProps<{ lead: Lead; statuses: { value: string; label: string }[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Quote Requests', href: index() }] },
});

const setStatus = (status: string) => router.patch(update(props.lead.id).url, { status }, { preserveScroll: true });
const markUnread = () => router.patch(update(props.lead.id).url, { read: false }, { onSuccess: () => router.visit(index().url) });

const note = useForm({ body: '' });
const addNote = () => note.post(storeNote(props.lead.id).url, { preserveScroll: true, onSuccess: () => note.reset() });
const removeNote = (id: number) => router.delete(destroyNote({ lead: props.lead.id, note: id }).url, { preserveScroll: true });

const confirming = ref(false);

const when = (days: number) => (days === 0 ? 'today' : days === 1 ? 'tomorrow' : days > 1 ? `in ${days} days` : `${-days} days ago`);
</script>

<template>
    <Head :title="`Quote: ${lead.full_name}`" />

    <div class="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="size-4" /> All quote requests</Link>
                <Heading :title="lead.full_name" :description="`Received ${lead.created_at}`" class="mb-0!" />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" @click="markUnread">Mark as unread</Button>
                <Button variant="ghost" class="text-destructive" @click="confirming = true"><Trash2 class="size-4" /> Delete</Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="space-y-6">
                <section class="grid gap-4 rounded-xl border p-5 sm:grid-cols-2" aria-label="Contact">
                    <a :href="lead.phone_href" class="flex items-center gap-3 rounded-lg bg-muted/40 p-3 hover:bg-muted"><Phone class="size-5 text-primary" /><span><span class="block text-xs text-muted-foreground">Phone</span><span class="font-semibold">{{ lead.phone }}</span></span></a>
                    <a :href="`mailto:${lead.email}`" class="flex items-center gap-3 rounded-lg bg-muted/40 p-3 hover:bg-muted"><Mail class="size-5 text-primary" /><span class="min-w-0"><span class="block text-xs text-muted-foreground">Email</span><span class="block truncate font-semibold">{{ lead.email }}</span></span></a>
                </section>

                <section class="rounded-xl border p-5" aria-label="Move">
                    <dl class="grid gap-5 sm:grid-cols-2">
                        <div v-for="side in (['from', 'to'] as const)" :key="side">
                            <dt class="text-xs text-muted-foreground">{{ side === 'from' ? 'Moving from' : 'Moving to' }}</dt>
                            <dd class="font-semibold">{{ lead[side].address }}</dd>
                            <dd v-if="Object.keys(lead[side].parts).length">
                                <dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-0.5 text-sm">
                                    <template v-for="(value, label) in lead[side].parts" :key="label">
                                        <dt class="text-muted-foreground">{{ label }}</dt>
                                        <dd class="break-words">{{ value }}</dd>
                                    </template>
                                </dl>
                            </dd>
                            <dd v-else class="text-sm text-muted-foreground">Typed by hand (no suggestion chosen), so no address parts were saved.</dd>
                            <dd class="mt-1 flex flex-wrap gap-x-4">
                                <a :href="lead[side].map" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-primary hover:underline"><MapPin class="size-3.5" /> Open in Google Maps</a>
                                <a v-if="lead[side].osm" :href="lead[side].osm" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-primary hover:underline"><MapPin class="size-3.5" /> OpenStreetMap</a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Moving date</dt>
                            <dd class="font-semibold">{{ lead.move_date }}</dd>
                            <dd class="text-sm text-muted-foreground">{{ when(lead.days_until) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Type of move</dt>
                            <dd class="font-semibold">{{ lead.move_type_name }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-3 rounded-xl border p-5" aria-labelledby="notes-heading">
                    <h2 id="notes-heading" class="font-semibold">Notes</h2>
                    <form class="space-y-2" @submit.prevent="addNote">
                        <Label for="note-body" class="sr-only">New note</Label>
                        <Textarea id="note-body" v-model="note.body" rows="3" maxlength="5000" placeholder="Called, left a voicemail…" />
                        <p v-if="note.errors.body" class="text-sm text-destructive">{{ note.errors.body }}</p>
                        <Button type="submit" size="sm" :disabled="note.processing || !note.body.trim()">Add note</Button>
                    </form>
                    <ul v-if="lead.notes.length" class="divide-y">
                        <li v-for="n in lead.notes" :key="n.id" class="flex items-start gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm whitespace-pre-wrap">{{ n.body }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ n.author }} · {{ n.created_at }}</p>
                            </div>
                            <Button variant="ghost" size="icon" class="size-7" aria-label="Delete note" @click="removeNote(n.id)"><Trash2 class="size-3.5" /></Button>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="space-y-6">
                <fieldset class="space-y-1 rounded-xl border p-4">
                    <legend class="px-1 text-sm font-semibold">Status</legend>
                    <button
                        v-for="s in statuses"
                        :key="s.value"
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm hover:bg-muted"
                        :class="lead.status === s.value ? 'bg-muted font-semibold' : ''"
                        :aria-pressed="lead.status === s.value"
                        @click="setStatus(s.value)"
                    >
                        {{ s.label }}
                        <span v-if="lead.status === s.value" aria-hidden="true">✓</span>
                    </button>
                </fieldset>

                <section class="space-y-2 rounded-xl border p-4 text-sm" aria-label="Source">
                    <h2 class="font-semibold">Where it came from</h2>
                    <p v-if="lead.source_url"><span class="text-muted-foreground">Page: </span><a :href="lead.source_url" target="_blank" rel="noopener" class="break-all hover:underline">{{ lead.source_url }}</a></p>
                    <p v-if="lead.referrer" class="break-all"><span class="text-muted-foreground">Referrer: </span>{{ lead.referrer }}</p>
                    <p v-for="(value, key) in lead.utm ?? {}" :key="key" class="break-all"><span class="text-muted-foreground">{{ key }}: </span>{{ value }}</p>
                    <p v-if="!lead.source_url && !lead.referrer && !lead.utm" class="text-muted-foreground">No tracking details.</p>
                    <p v-if="lead.ip" class="pt-2 text-xs text-muted-foreground">IP {{ lead.ip }}</p>
                </section>
            </aside>
        </div>
    </div>

    <ConfirmDialog
        :open="confirming"
        :title="`Delete the request from ${lead.full_name}?`"
        description="It moves to the trash and leaves this list and the CSV export."
        confirm-label="Delete"
        destructive
        @update:open="(o) => (confirming = o)"
        @confirm="router.delete(destroy(lead.id))"
    />
</template>
