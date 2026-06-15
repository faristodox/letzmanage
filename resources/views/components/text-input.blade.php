@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block rounded-lg border-slate-200 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500']) }}>
