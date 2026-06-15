@props(['active' => false])

@php
$classes = ($active ?? false)
            ? 'group flex items-center gap-x-3 rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700'
            : 'group flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
