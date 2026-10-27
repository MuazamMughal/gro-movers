<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, FileText, Pencil } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { edit, index, template, toggle } from '@/routes/admin/area-services';
import { edit as editArea, index as areasIndex } from '@/routes/admin/areas';

type Cell = { service_id: number; offered: boolean; page_id: number | null; is_active: boolean; custom: boolean; path: string };
type Row = { id: number; name: string; is_active: boolean; cells: Cell[] };

defineProps<{
    services: { id: number; title: string; is_active: boolean }[];
    areas: Row[];
    stats: { live: number; custom: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Service Areas', href: areasIndex() },
            { title: 'City × service pages', href: index() },
        ],
    },
});

const flip = (cell: Cell) => cell.page_id && router.patch(toggle(cell.page_id).url, {}, { preserveScroll: true });
</script>

<template>
    <Head title="City × service pages" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="City × service pages"
                :description="`One page per service in each city, like “Local Moving in Plano, TX”. ${stats.live} live · ${stats.custom} with custom content.`"
            />
            <Button as-child><Link :href="template()"><FileText class="size-4" /> Edit shared template</Link></Button>
        </div>

        <div class="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
            Pages use the shared template (with {city} and {service} filled in) unless you give them custom content.
            Tick a box to put a page live. A dash means the service is not offered in that city — change that on the area.
        </div>

        <p v-if="!areas.length || !services.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
            Add services and service areas first. Their combinations appear here automatically.
        </p>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50">
                    <tr>
                        <th scope="col" class="sticky left-0 bg-muted px-4 py-3 text-left font-medium">City</th>
                        <th v-for="service in services" :key="service.id" scope="col" class="min-w-36 px-3 py-3 text-center font-medium" :class="{ 'opacity-50': !service.is_active }">
                            {{ service.title }}
                            <span v-if="!service.is_active" class="block text-[10px] font-normal">inactive</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="area in areas" :key="area.id" :class="{ 'opacity-50': !area.is_active }">
                        <th scope="row" class="sticky left-0 bg-background px-4 py-3 text-left font-medium">
                            <Link :href="editArea(area.id)" class="hover:underline">{{ area.name }}</Link>
                            <span v-if="!area.is_active" class="block text-[10px] font-normal text-muted-foreground">inactive</span>
                        </th>
                        <td v-for="cell in area.cells" :key="cell.service_id" class="px-3 py-2 text-center">
                            <span v-if="!cell.offered || !cell.page_id" class="text-muted-foreground" title="Not offered in this city">—</span>
                            <div v-else class="flex items-center justify-center gap-1">
                                <Checkbox
                                    :model-value="cell.is_active"
                                    :aria-label="`${cell.is_active ? 'Unpublish' : 'Publish'} ${services.find((s) => s.id === cell.service_id)?.title} in ${area.name}`"
                                    @update:model-value="flip(cell)"
                                />
                                <Badge v-if="cell.custom" variant="secondary" class="px-1.5 text-[10px]">custom</Badge>
                                <Button variant="ghost" size="icon" class="size-7" as-child>
                                    <Link :href="edit(cell.page_id)" aria-label="Edit page"><Pencil class="size-3.5" /></Link>
                                </Button>
                                <Button v-if="cell.is_active && area.is_active" variant="ghost" size="icon" class="size-7" as-child>
                                    <a :href="cell.path" target="_blank" rel="noopener" aria-label="View page"><ExternalLink class="size-3.5" /></a>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
