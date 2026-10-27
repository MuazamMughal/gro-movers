<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Inbox, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { exportMethod as exportCsv, index, show } from '@/routes/admin/leads';

type LeadRow = {
    id: number;
    full_name: string;
    email: string;
    phone: string;
    route: string;
    move_date: string;
    move_type: string;
    status: string;
    is_read: boolean;
    created_at: string;
};

type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number; prev_page_url: string | null; next_page_url: string | null };
type Filters = { q: string; status: string; move_type: string; from: string; to: string; unread: boolean };

const props = defineProps<{
    leads: Paginated<LeadRow>;
    filters: Filters;
    statuses: { value: string; label: string }[];
    moveTypes: { id: number; name: string }[];
    counts: { unread: number; today: number; all: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Quote Requests', href: index() }] },
});

const f = ref<Filters>({ ...props.filters, unread: !!props.filters.unread, move_type: String(props.filters.move_type ?? '') });

const query = computed(() =>
    Object.fromEntries(
        Object.entries(f.value)
            .filter(([, v]) => v !== '' && v !== false && v !== null)
            .map(([k, v]) => [k, v === true ? '1' : String(v)]),
    ),
);

const apply = () => router.get(index().url, query.value, { preserveState: true, preserveScroll: true, replace: true });

let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => f.value.q,
    () => {
        clearTimeout(timer);
        timer = setTimeout(apply, 300);
    },
);
watch(() => [f.value.status, f.value.move_type, f.value.from, f.value.to, f.value.unread], apply);

const exportUrl = computed(() => exportCsv({ query: query.value }).url);
const hasFilters = computed(() => Object.keys(query.value).length > 0);

const statusVariant = (s: string) => (s === 'new' ? 'default' : s === 'booked' ? 'secondary' : 'outline');
const selectClass = 'h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head title="Quote Requests" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Quote Requests" :description="`${counts.unread} unread · ${counts.today} today · ${counts.all} in total. Every request is also emailed to your lead recipients.`" />
            <Button variant="outline" as-child>
                <a :href="exportUrl"><Download class="size-4" /> Export CSV{{ hasFilters ? ' (filtered)' : '' }}</a>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-60 flex-1">
                <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                <Input v-model="f.q" class="pl-8" placeholder="Search name, email or phone" aria-label="Search quote requests" />
            </div>
            <select v-model="f.status" :class="selectClass" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="f.move_type" :class="selectClass" aria-label="Filter by type of move">
                <option value="">All move types</option>
                <option v-for="m in moveTypes" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
            </select>
            <label class="flex items-center gap-1.5 text-sm">From <Input v-model="f.from" type="date" class="h-9 w-auto" aria-label="Received from" /></label>
            <label class="flex items-center gap-1.5 text-sm">to <Input v-model="f.to" type="date" class="h-9 w-auto" aria-label="Received until" /></label>
            <label class="flex items-center gap-2 text-sm"><Checkbox :model-value="f.unread" @update:model-value="(v) => (f.unread = v === true)" /> Unread only</label>
        </div>

        <div v-if="!leads.data.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <Inbox class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">{{ counts.all ? 'No requests match these filters' : 'No quote requests yet' }}</p>
            <p class="text-sm text-muted-foreground">Requests from the website quote form appear here.</p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">Customer</th>
                        <th class="px-3 py-2 font-medium">Move</th>
                        <th class="px-3 py-2 font-medium">Moving date</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Received</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-muted/30" :class="{ 'font-semibold': !lead.is_read }">
                        <td class="px-3 py-2.5">
                            <Link :href="show(lead.id)" class="flex items-center gap-2 hover:underline">
                                <span v-if="!lead.is_read" class="size-2 shrink-0 rounded-full bg-primary" aria-label="Unread" />
                                {{ lead.full_name }}
                            </Link>
                            <span class="block text-xs font-normal text-muted-foreground">{{ lead.phone }} · {{ lead.email }}</span>
                        </td>
                        <td class="px-3 py-2.5 font-normal">
                            {{ lead.move_type }}
                            <span class="block max-w-72 truncate text-xs text-muted-foreground">{{ lead.route }}</span>
                        </td>
                        <td class="px-3 py-2.5 font-normal whitespace-nowrap">{{ lead.move_date }}</td>
                        <td class="px-3 py-2.5"><Badge :variant="statusVariant(lead.status)" class="capitalize">{{ lead.status }}</Badge></td>
                        <td class="px-3 py-2.5 font-normal whitespace-nowrap text-muted-foreground">{{ lead.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="leads.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Pagination">
            <Button variant="outline" size="sm" :disabled="!leads.prev_page_url" as-child>
                <Link v-if="leads.prev_page_url" :href="leads.prev_page_url" preserve-scroll>← Newer</Link>
                <span v-else>← Newer</span>
            </Button>
            <span class="text-muted-foreground">Page {{ leads.current_page }} of {{ leads.last_page }} · {{ leads.total }} requests</span>
            <Button variant="outline" size="sm" :disabled="!leads.next_page_url" as-child>
                <Link v-if="leads.next_page_url" :href="leads.next_page_url" preserve-scroll>Older →</Link>
                <span v-else>Older →</span>
            </Button>
        </nav>
    </div>
</template>
