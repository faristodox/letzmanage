@props(['title', 'type', 'labels', 'data'])

@php
    $key = md5($type.json_encode($labels).json_encode($data));
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>

    @if (empty($data) || array_sum($data) === 0)
        <p class="mt-6 text-center text-sm text-slate-400">{{ __('No responses yet.') }}</p>
    @else
        <div wire:ignore wire:key="chart-{{ $key }}" x-data="chartCard(@js($type), @js($labels), @js($data))" class="mt-4">
            <canvas x-ref="canvas"></canvas>
        </div>
    @endif
</div>
