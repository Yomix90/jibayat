@php use App\Support\Format; use App\Domain\DomainePublic\Services\OccupationService; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Autorisations expirées ou échues dans les') }}
            <x-input type="number" min="0" wire:model.live.debounce.400ms="withinDays" class="!w-20 text-center" :aria-label="__('Nombre de jours')" />
            {{ __('prochains jours') }}</label>
        <span class="ms-auto font-semibold">{{ $rows->count() }} occupation(s)</span>
    </div>
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $o)
                @php($expired = $o->ends_on && $o->ends_on->isPast())
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="r-{{ $o->id }}">
                    <span class="font-mono text-xs font-bold">N° {{ $o->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $o->taxpayer?->displayName() }} · {{ $o->type }} ({{ Format::money($o->area) }} m²)</p>
                        <p class="truncate text-xs text-muted">{{ $o->location }}</p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs">
                            @if ($o->taxpayer?->phone)<a href="tel:{{ $o->taxpayer->phone }}" class="inline-flex items-center gap-1 font-semibold text-accent hover:underline"><x-svg-icon name="phone" class="h-3.5 w-3.5" />{{ $o->taxpayer->phone }}</a>@else<span class="text-red-600">{{ __('Téléphone non renseigné') }}</span>@endif
                            @if ($o->renewal_contacted_at)<span class="text-muted">· {{ __('Contacté le') }} {{ $o->renewal_contacted_at->format('d/m/Y') }} : {{ $o->renewal_contact_note }}</span>@else<x-badge color="red">{{ __('À contacter') }}</x-badge>@endif
                        </p>
                    </div>
                    <x-badge :color="$expired ? 'red' : 'gold'">{{ $o->ends_on ? ($expired ? 'Expirée le ' : 'Échéance le ').$o->ends_on->format('d/m/Y') : ucfirst($o->status) }}</x-badge>
                    @if ($this->allowed('domaine_public.modifier'))
                        <x-btn variant="ghost" size="sm" icon="phone-call" wire:click="openContact({{ $o->id }})">{{ __('Contacté') }}</x-btn>
                        <div class="flex gap-1">
                            @foreach (OccupationService::DURATIONS as $k => $label)
                                <x-btn variant="secondary" size="sm" wire:click="renew({{ $o->id }}, '{{ $k }}')">+ {{ tr($label) }}</x-btn>
                            @endforeach
                        </div>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucune autorisation à renouveler')" /></li>
            @endforelse
        </ul>
    </x-card>

    @if ($contactId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Contact du titulaire') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('contactId', null)"></div>
            <form wire:submit="saveContact" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Contact du titulaire') }}</h2>
                <x-field :label="__('Résultat du contact')" for="c-note" error="contactNote"><x-input id="c-note" wire:model="contactNote" :placeholder="__('Ex. passera la semaine prochaine pour renouveler')" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('contactId', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
            </form>
        </div>
    @endif
</div>
