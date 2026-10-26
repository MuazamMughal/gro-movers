@props(['seo'])
{{-- SEO <head> tags for one public page. $seo is an App\Support\SeoData (see Seo::for()). --}}
@php([$imageWidth, $imageHeight] = $seo->imageSize())
<title>{{ $seo->title }}</title>
@if ($seo->description)
<meta name="description" content="{{ $seo->description }}">
@endif
<link rel="canonical" href="{{ $seo->canonical }}">
<meta name="robots" content="{{ $seo->robots() }}">
<meta property="og:type" content="{{ $seo->ogType }}">
<meta property="og:site_name" content="{{ $seo->siteName }}">
<meta property="og:locale" content="en_US">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:title" content="{{ $seo->ogTitle }}">
@if ($seo->ogDescription)
<meta property="og:description" content="{{ $seo->ogDescription }}">
@endif
@if ($imageUrl = $seo->imageUrl())
<meta property="og:image" content="{{ $imageUrl }}">
@if ($imageWidth)
<meta property="og:image:width" content="{{ $imageWidth }}">
<meta property="og:image:height" content="{{ $imageHeight }}">
@endif
@if ($seo->image->alt)
<meta property="og:image:alt" content="{{ $seo->image->alt }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ $imageUrl }}">
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $seo->ogTitle }}">
@if ($seo->ogDescription)
<meta name="twitter:description" content="{{ $seo->ogDescription }}">
@endif
