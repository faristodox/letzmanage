<div>
    <div class="mb-4 flex items-center justify-end">
        @can('create', App\Models\Form::class)
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Form') }}
            </x-primary-button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Responses') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Created') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($forms as $form)
                    @php
                        $statusColors = [
                            'draft' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                            'published' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                            'closed' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                        ];
                    @endphp
                    <tr wire:key="form-{{ $form->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $form->title }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors[$form->status->value] }}">
                                {{ ucfirst($form->status->value) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $form->responses_count }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $form->created_at->format('d M Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-3">
                            <a href="{{ route('forms.builder', $form) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</a>
                            @can('viewResponses', $form)
                                <a href="{{ route('forms.responses', $form) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Responses') }}</a>
                            @endcan
                            @can('delete', $form)
                                <button wire:click="confirmDelete({{ $form->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No forms yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $forms->links() }}
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New Form') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __("You'll add fields on the next screen.") }}</p>

                    <div class="mt-4">
                        <x-input-label for="title" :value="__('Title')" />
                        <x-text-input wire:model="title" id="title" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Volunteer Sign-up') }}" autofocus />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Create & Continue') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($confirmingDeleteId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Form') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Are you sure you want to delete this form? This will also permanently delete all of its responses.') }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="delete">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
