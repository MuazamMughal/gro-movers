<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { report as seoReport } from '@/routes/admin/seo';
import { rebuild as rebuildStatic } from '@/routes/admin/static';

type Lead = {
    id: number;
    full_name: string;
    from_city: string | null;
    to_city: string | null;
    move_date: string;
    move_type_name: string;
    status: string;
    read_at: string | null;
    created_at: string;
};

const props = defineProps<{
    stats: {
        leadsToday: number;
        leads7: number;
        leads30: number;
        unreadLeads: number;
        unreadMessages: number;
    };
    chart: { date: string; total: number }[];
    latestLeads: Lead[];
    staticSite: {
        enabled: boolean;
        files: number;
        running: { total: number; done: number; progress: number } | null;
        lastBuiltAt: string | null;
        failures: { path: string; error: string | null }[];
    };
    hiddenFromGoogle: { path: string; label: string; edit_url: string; expected: boolean }[];
    schedulerLastRun: string | null;
}>();

// Cron should tick every minute; 10 minutes of silence means emails and rebuilds are waiting.
const cronStale = computed(() => !props.schedulerLastRun || Date.now() - new Date(props.schedulerLastRun).getTime() > 10 * 60 * 1000);

const unexpectedHidden = computed(() => props.hiddenFromGoogle.filter((h) => !h.expected));

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

// ---- Static site: rebuild button and live progress ------------------------
const rebuilding = ref(false);
function rebuild() {
    rebuilding.value = true;
    router.post(rebuildStatic().url, {}, { preserveScroll: true, onFinish: () => (rebuilding.value = false) });
}

let poll: ReturnType<typeof setInterval> | undefined;
watch(
    () => props.staticSite.running,
    (running) => {
        clearInterval(poll);
        if (running) poll = setInterval(() => router.reload({ only: ['staticSite'] }), 3000);
    },
    { immediate: true },
);
onBeforeUnmount(() => clearInterval(poll));

const tiles = computed(() => [
    { label: 'Leads today', value: props.stats.leadsToday },
    { label: 'Leads, last 7 days', value: props.stats.leads7 },
    { label: 'Leads, last 30 days', value: props.stats.leads30 },
    {
        label: 'Unread',
        value: props.stats.unreadLeads + props.stats.unreadMessages,
        hint: `${props.stats.unreadLeads} leads · ${props.stats.unreadMessages} messages`,
    },
]);

const chartMax = computed(() =>
    Math.max(1, ...props.chart.map((d) => d.total)),
);

const dateFmt = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
});
const formatDate = (iso: string) => dateFmt.format(new Date(iso));
const formatDay = (day: string) => dateFmt.format(new Date(`${day}T00:00:00`));
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Dashboard"
            description="Quote requests and site status at a glance."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="tile in tiles" :key="tile.label" class="gap-2 py-5">
                <CardHeader class="px-5">
                    <CardDescription>{{ tile.label }}</CardDescription>
                    <CardTitle class="text-3xl tabular-nums">
                        {{ tile.value }}
                    </CardTitle>
                </CardHeader>
                <CardContent v-if="tile.hint" class="px-5">
                    <p class="text-xs text-muted-foreground">{{ tile.hint }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Leads per day</CardTitle>
                <CardDescription>Last 30 days</CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-40 items-end gap-1"
                    role="img"
                    :aria-label="`Leads per day for the last 30 days, ${stats.leads30} in total`"
                >
                    <div
                        v-for="day in chart"
                        :key="day.date"
                        class="group relative flex h-full flex-1 items-end"
                    >
                        <div
                            class="w-full rounded-t-sm bg-primary/80 transition-colors group-hover:bg-primary"
                            :style="{
                                height: `${Math.max(day.total ? 4 : 1, (day.total / chartMax) * 100)}%`,
                            }"
                            :class="{ 'bg-muted-foreground/20': !day.total }"
                        />
                        <span
                            class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 rounded bg-popover px-1.5 py-0.5 text-xs whitespace-nowrap shadow group-hover:block"
                        >
                            {{ formatDay(day.date) }}: {{ day.total }}
                        </span>
                    </div>
                </div>
                <div
                    class="mt-2 flex justify-between text-xs text-muted-foreground"
                >
                    <span>{{ formatDay(chart[0].date) }}</span>
                    <span>Today</span>
                </div>
            </CardContent>
        </Card>

        <div v-if="cronStale" class="rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm" role="alert">
            <p class="font-semibold text-destructive">The scheduled task (cron) is not running.</p>
            <p class="text-muted-foreground">
                Lead emails, site rebuilds, scheduled posts and backups wait until it runs.
                {{ schedulerLastRun ? `Last run: ${formatDate(schedulerLastRun)}.` : 'It has never run on this server.' }}
                See “Cron” in docs/deploy-cpanel.md.
            </p>
        </div>

        <Card :class="unexpectedHidden.length ? 'border-destructive' : ''">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Pages hidden from Google ({{ hiddenFromGoogle.length }})
                    <Badge v-if="unexpectedHidden.length" variant="destructive">{{ unexpectedHidden.length }} to check</Badge>
                </CardTitle>
                <CardDescription>
                    Normally only the thank-you page is hidden. Anything else here will not show up in Google search.
                </CardDescription>
            </CardHeader>
            <CardContent class="text-sm">
                <ul class="flex flex-wrap gap-2">
                    <li v-for="h in hiddenFromGoogle" :key="h.path">
                        <Link :href="h.edit_url" class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 hover:bg-muted" :class="{ 'border-destructive text-destructive': !h.expected }">
                            {{ h.label }} <span class="font-mono text-xs text-muted-foreground">{{ h.path }}</span>
                        </Link>
                    </li>
                </ul>
                <Link :href="seoReport()" class="mt-3 inline-block text-muted-foreground underline">Open the SEO report</Link>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Latest quote requests</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!latestLeads.length"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No quote requests yet. They will appear here as soon as
                        the quote form goes live.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="lead in latestLeads"
                            :key="lead.id"
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 text-sm"
                        >
                            <span class="font-medium">
                                {{ lead.full_name }}
                                <Badge
                                    v-if="!lead.read_at"
                                    class="ml-1 align-middle"
                                    >New</Badge
                                >
                            </span>
                            <span class="text-muted-foreground">
                                {{ lead.from_city ?? '—' }} →
                                {{ lead.to_city ?? '—' }}
                            </span>
                            <span class="text-muted-foreground">
                                {{ lead.move_type_name }} ·
                                {{ formatDate(lead.move_date) }}
                            </span>
                            <span
                                class="ml-auto text-xs text-muted-foreground"
                                >{{ formatDate(lead.created_at) }}</span
                            >
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Static site</CardTitle>
                    <CardDescription
                        >Pre-built HTML pages served to
                        visitors.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <p v-if="!staticSite.enabled" class="text-amber-700 dark:text-amber-400">
                        Switched off in Settings → Static Cache: every page is rendered live.
                    </p>
                    <template v-else>
                        <div v-if="staticSite.running" class="space-y-1.5" role="status">
                            <p class="font-medium">Rebuilding… {{ staticSite.running.progress }}%</p>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full bg-primary transition-all" :style="{ width: `${staticSite.running.progress}%` }" />
                            </div>
                        </div>
                        <p>
                            <span class="text-2xl font-semibold tabular-nums">{{ staticSite.files }}</span>
                            pages cached
                        </p>
                        <p v-if="staticSite.lastBuiltAt" class="text-muted-foreground">Last update {{ formatDate(staticSite.lastBuiltAt) }}</p>
                        <p v-else class="text-muted-foreground">No build has run yet.</p>
                        <div v-if="staticSite.failures.length" class="space-y-1 rounded-md border border-destructive/40 p-2">
                            <p class="font-medium text-destructive">{{ staticSite.failures.length }} {{ staticSite.failures.length === 1 ? 'page' : 'pages' }} failed to build (shown live meanwhile):</p>
                            <p v-for="f in staticSite.failures" :key="f.path" class="truncate font-mono text-xs" :title="f.error ?? ''">{{ f.path }}</p>
                        </div>
                    </template>
                    <Button variant="outline" :disabled="!staticSite.enabled || !!staticSite.running || rebuilding" @click="rebuild">
                        <RefreshCw class="size-4" :class="{ 'animate-spin': staticSite.running || rebuilding }" />
                        Rebuild site
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
