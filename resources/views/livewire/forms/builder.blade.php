<div class="space-y-6">
    <div class="flex flex-wrap justify-end gap-2">
        @can('viewResponses', $form)
            <a href="{{ route('forms.responses', $form) }}" wire:navigate
                class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                {{ __('Responses') }}
            </a>
        @endcan
        <a href="{{ route('forms.preview', $form) }}" target="_blank" rel="noopener"
            class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            {{ __('Preview') }}
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    @if ($form->status->value === 'published' && $form->organization)
        <div
            x-data="{
                copied: false,
                url: @js($form->publicUrl()),
                async copy() {
                    try {
                        await navigator.clipboard.writeText(this.url);
                    } catch (e) {
                        this.$refs.urlInput.select();
                        document.execCommand('copy');
                    }
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }"
            class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-violet-50 p-5 shadow-sm"
        >
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Your public form link') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Share this link so anyone can submit a response — no account needed.') }}</p>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="text" readonly x-ref="urlInput" value="{{ $form->publicUrl() }}" @focus="$event.target.select()"
                    class="min-w-0 flex-1 rounded-lg border-slate-200 bg-white/80 font-mono text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <div class="flex gap-2">
                    <button type="button" @click="copy()" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                    </button>
                    <a href="{{ $form->publicUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        {{ __('Open') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Form Settings -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Form Settings') }}</h2>

        <form wire:submit="saveFormSettings" class="mt-4 space-y-4">
            <div>
                <x-input-label for="formTitle" :value="__('Title')" />
                <x-text-input wire:model="formTitle" id="formTitle" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('formTitle')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="formDescription" :value="__('Description')" />
                <textarea wire:model="formDescription" id="formDescription" rows="3" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('formDescription')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select wire:model="status" id="status" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}">{{ ucfirst($statusOption->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="closes_at" :value="__('Closes At (optional)')" />
                    <input wire:model="closes_at" id="closes_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-input-error :messages="$errors->get('closes_at')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Form Settings') }}</x-primary-button>
            </div>
        </form>
    </div>

    <!-- Fields -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Fields') }}</h2>
            <x-secondary-button wire:click="addField">{{ __('Add Field') }}</x-secondary-button>
        </div>

        @if ($this->hasResponses())
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-inset ring-amber-600/20">
                {{ __('This form already has responses, so existing fields can no longer be deleted or have their type/options changed — only new fields may be added.') }}
            </p>
        @endif

        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($fields as $index => $field)
                <div wire:key="field-{{ $field->id }}" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">
                            {{ $field->label }}
                            @if ($field->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ ucfirst($field->type->value) }}
                            @if ($field->type->isChoice())
                                &middot; {{ implode(', ', $field->options ?? []) }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-sm font-medium">
                        <button type="button" wire:click="moveFieldUp({{ $field->id }})" @if ($index === 0) disabled @endif class="text-slate-400 hover:text-slate-600 disabled:opacity-30">&uarr;</button>
                        <button type="button" wire:click="moveFieldDown({{ $field->id }})" @if ($index === $fields->count() - 1) disabled @endif class="text-slate-400 hover:text-slate-600 disabled:opacity-30">&darr;</button>
                        <button type="button" wire:click="editField({{ $field->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                        <button type="button" wire:click="confirmDeleteField({{ $field->id }})" @if ($this->hasResponses()) disabled @endif class="text-red-600 hover:text-red-700 disabled:opacity-30">{{ __('Delete') }}</button>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-500">{{ __('No fields yet — add your first one above.') }}</p>
            @endforelse
        </div>
    </div>

    <!-- Field Modal -->
    @if ($showFieldModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeFieldModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="saveField" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $editingFieldId ? __('Edit Field') : __('Add Field') }}
                    </h2>

                    <div class="mt-4">
                        <x-input-label for="fieldLabel" :value="__('Label')" />
                        <x-text-input wire:model="fieldLabel" id="fieldLabel" type="text" class="mt-1 block w-full" autofocus />
                        <x-input-error :messages="$errors->get('fieldLabel')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="fieldType" :value="__('Type')" />
                        <select wire:model.live="fieldType" id="fieldType" @if ($editingFieldId && $this->hasResponses()) disabled @endif class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50">
                            @foreach ($fieldTypes as $type)
                                <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('fieldType')" class="mt-2" />
                    </div>

                    @if (in_array($fieldType, ['select', 'radio', 'checkbox']))
                        <div class="mt-4">
                            <x-input-label for="fieldOptions" :value="__('Options (one per line)')" />
                            <textarea wire:model="fieldOptions" id="fieldOptions" rows="4" @if ($editingFieldId && $this->hasResponses()) disabled @endif
                                class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50"></textarea>
                            <x-input-error :messages="$errors->get('fieldOptions')" class="mt-2" />
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="fieldHelpText" :value="__('Help text (optional)')" />
                        <x-text-input wire:model="fieldHelpText" id="fieldHelpText" type="text" class="mt-1 block w-full" />
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="fieldRequired" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">{{ __('Required') }}</span>
                    </label>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeFieldModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Field Confirmation Modal -->
    @if ($confirmingDeleteFieldId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteFieldModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Field') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure you want to delete this field?') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteFieldModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="deleteField">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
