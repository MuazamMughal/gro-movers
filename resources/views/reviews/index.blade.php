@extends('layouts.site')

{{-- /reviews: page sections, the overall rating and every active review. --}}
@section('content')
    {!! $rendered->html !!}

    <x-site.section id="all-reviews">
        @if ($reviews->isNotEmpty())
            <div class="mb-12 flex flex-col items-center gap-8 rounded-2xl bg-muted p-8 md:flex-row md:justify-center md:gap-16">
                <div class="text-center">
                    <p class="text-6xl font-extrabold tracking-tight">{{ number_format($average, 1) }}</p>
                    <x-site.stars :rating="$average" class="mt-2 justify-center" />
                    <p class="mt-2 text-secondary-light">Based on {{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }}</p>
                </div>
                <dl class="w-full max-w-xs space-y-1.5 text-sm">
                    @foreach ($breakdown as $stars => $count)
                        <div class="flex items-center gap-3">
                            <dt class="w-12 shrink-0 font-semibold">{{ $stars }} star</dt>
                            <dd class="h-2 flex-1 overflow-hidden rounded-full bg-white" aria-hidden="true">
                                <span class="block h-full rounded-full bg-tertiary" style="width: {{ round($count / $reviews->count() * 100) }}%"></span>
                            </dd>
                            <dd class="w-8 text-right text-secondary-light">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <h2 class="sr-only">Customer reviews</h2>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($reviews as $review)
                    <x-site.review-card :review="$review" badge />
                @endforeach
            </div>
        @else
            <p class="py-12 text-center text-lg text-secondary-light">Reviews are coming soon.</p>
        @endif
    </x-site.section>

    {!! $closing->html !!}
@endsection
