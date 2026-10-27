@props(['name' => null])
{{-- Inline Lucide icon (server-rendered SVG). Only names from config('sections.icons') plus a few UI icons. --}}
@php
    $allowed = [...config('sections.icons'), 'check', 'chevron-down', 'chevron-right', 'arrow-right', 'quote', 'star', 'x', 'menu', 'arrow-left', 'newspaper', 'list', 'facebook', 'instagram', 'youtube', 'linkedin'];
@endphp
@if ($name && in_array($name, $allowed, true))
    {!! svg('lucide-'.$name, $attributes->get('class', 'size-6'), ['aria-hidden' => 'true', 'focusable' => 'false'])->toHtml() !!}
@endif
