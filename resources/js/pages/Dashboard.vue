<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed } from 'vue';
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
    lastBuild: {
        path: string;
        status: string;
        error: string | null;
        built_at: string;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

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
                    <p v-if="lastBuild">
                        Last build:
                        <Badge
                            :variant="
                                lastBuild.status === 'ok'
                                    ? 'secondary'
                                    : 'destructive'
                            "
                            >{{ lastBuild.status }}</Badge
                        >
                        <span class="block text-muted-foreground">{{
                            formatDate(lastBuild.built_at)
                        }}</span>
                    </p>
                    <p v-else class="text-muted-foreground">
                        No build has run yet.
                    </p>
                    <Button
                        variant="outline"
                        disabled
                        title="Available once the static publisher is built"
                    >
                        <RefreshCw class="size-4" />
                        Rebuild site
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
