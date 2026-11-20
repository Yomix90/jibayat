<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite() && $role !== 'administrateur')
            <x-slot:actions><x-btn icon="check" wire:click="save">{{ __('Enregistrer') }}</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-wrap lg:px-0">
        @foreach ($roles as $r)
            <button type="button" wire:click="$set('role', '{{ $r->name }}')" wire:key="r-{{ $r->id }}"
                    @class(['chip', '!border-gold !bg-gold/15' => $role === $r->name])>
                <span class="whitespace-nowrap ps-1.5">{{ config('jibayat.roles.'.$r->name, $r->name) }}</span>
                <x-badge>{{ $r->users_count }}</x-badge>
            </button>
        @endforeach
    </div>

    @if ($role === 'administrateur')
        <x-card>
            <p class="text-sm text-muted">{{ __('L\'administrateur a') }} <strong>{{ __('tous les droits') }}</strong> sur tous les modules ; ils ne se modifient pas.</p>
        </x-card>
    @else
        @php($disabled = ! $this->canWrite())
        <x-card :title="__('Droits transversaux')">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($globals as $perm => $label)
                    <label class="flex items-center gap-2 text-sm" wire:key="g-{{ $perm }}">
                        <input type="checkbox" wire:model="granted.{{ str_replace('.', '__', $perm) }}" @disabled($disabled)
                               class="rounded border-line text-accent focus:ring-gold">
                        {{ tr($label) }}
                    </label>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Droits par module')" :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ __('Module') }}</th>
                            @foreach ($actions as $a)<th class="px-2 py-2 text-center">{{ $a }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($modules as $mod => $label)
                            <tr wire:key="m-{{ $mod }}">
                                <td class="px-4 py-2 font-semibold text-ink">{{ tr($label) }}</td>
                                @foreach ($actions as $a)
                                    <td class="px-2 py-2 text-center">
                                        <input type="checkbox" aria-label="{{ tr($label) }} : {{ $a }}" wire:model="granted.{{ $mod }}__{{ $a }}" @disabled($disabled)
                                               class="rounded border-line text-accent focus:ring-gold">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
