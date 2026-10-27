@props(['seo', 'breadcrumbs' => [], 'nodes' => [], 'faqs' => [], 'business' => null])
{{--
    The page's JSON-LD as one @graph: MovingCompany + WebSite on every page, plus
    breadcrumbs, FAQs shown on the page and the page's own nodes (App\Seo\Schema).
--}}
@php
    $graph = \App\Seo\Schema::graph([
        \App\Seo\Schema::breadcrumbs($breadcrumbs, $seo->canonical),
        \App\Seo\Schema::faq($faqs),
        ...$nodes,
    ]);

    if ($business) {
        $graph['@graph'][0] = \App\Seo\Schema::business($business);
    }
@endphp
<script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
