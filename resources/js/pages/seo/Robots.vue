<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, RefreshCw } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import SeoToolsNav from '@/components/seo/SeoToolsNav.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { report, robots as robotsRoute } from '@/routes/admin/seo';
import { regenerate } from '@/routes/admin/seo/sitemap';
import { update } from '@/routes/admin/seo/robots';

const props = defineProps<{ robots: string; preview: string; sitemapUrl: string; sitemapCount: number; production: boolean }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'SEO Tools', href: report() }, { title: 'robots.txt & sitemap', href: robotsRoute() }] },
});

const form = useForm({ robots: props.robots });
useUnsavedGuard(() => form.isDirty && !form.processing);

const save = () => form.put(update().url, { preserveScroll: true, onSuccess: () => form.defaults() });
const regenerateSitemap = () => router.post(regenerate().url, {}, { preserveScroll: true });
</script>

<template>
    <Head title="robots.txt & sitemap" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading title="SEO Tools" description="Tell search engines what to crawl, and give them the list of every page." />

        <SeoToolsNav current="robots" />

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-3">
                <Label for="robots-txt" class="text-base font-semibold">robots.txt</Label>
                <Textarea id="robots-txt" v-model="form.robots" rows="10" class="font-mono text-sm" spellcheck="false" :aria-invalid="!!form.errors.robots" />
                <p v-if="form.errors.robots" class="text-sm text-destructive">{{ form.errors.robots }}</p>
                <p class="text-xs text-muted-foreground">
                    The Sitemap line is added automatically.
                    <template v-if="production">“Disallow: /” cannot be saved on the live site because it would remove every page from Google.</template>
                </p>
                <div class="flex items-center gap-2">
                    <Button :disabled="form.processing || !form.isDirty" @click="save">Save robots.txt</Button>
                    <Button variant="ghost" as-child><a href="/robots.txt" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a></Button>
                </div>
                <details class="rounded-lg border p-3 text-sm">
                    <summary class="cursor-pointer font-medium">What visitors see now</summary>
                    <pre class="mt-2 overflow-x-auto text-xs whitespace-pre-wrap">{{ preview }}</pre>
                </details>
            </section>

            <section class="space-y-3 rounded-xl border p-5">
                <h2 class="text-base font-semibold">XML sitemap</h2>
                <p class="text-sm text-muted-foreground">
                    Lists the {{ sitemapCount }} pages that can appear in Google, with their last change date. It updates by itself whenever
                    you publish or change a page; hidden (noindex) pages and drafts are left out.
                </p>
                <p class="font-mono text-sm break-all">{{ sitemapUrl }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="regenerateSitemap"><RefreshCw class="size-4" /> Regenerate</Button>
                    <Button variant="ghost" as-child><a :href="sitemapUrl" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a></Button>
                </div>
                <p class="text-xs text-muted-foreground">Submit this URL once in Google Search Console → Sitemaps.</p>
            </section>
        </div>
    </div>
</template>
