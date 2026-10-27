@props(['field', 'id', 'errors' => null])
{{-- One field's error message (server-rendered after a no-JS submit; the JS form fills it live). --}}
<p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-red-700 empty:hidden" data-error-for="{{ $field }}">{{ $errors?->first($field) }}</p>
