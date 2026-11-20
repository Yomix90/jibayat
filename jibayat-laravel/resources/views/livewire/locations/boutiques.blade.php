@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="grid-2x2" :href="route('locations.secteurs')">{{ __('Gérer les boutiques') }}</x-btn></x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2">
        @foreach (['disponible' => 'Disponibles', 'exploitee' => 'Exploitées', 'fermee' => 'Fermées', '' => 'Toutes'] as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key }}')" @class(['chip', '!border-gold !bg-gold/15' => $status === $key])>
                <span class="ps-1.5">{{ tr($label) }}</span>
                <x-badge>{{ $key === '' ? $counts->sum() : ($counts[$key] ?? 0) }}</x-badge>
            </button>
        @endforeach
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Boutique') }}</th><th class="px-4 py-2 text-start">{{ __('Secteur') }}</th><th class="px-4 py-2 text-start">{{ __('Groupe de tarif') }}</th><th class="px-4 py-2 text-end">{{ __('Loyer') }}</th><th class="px-4 py-2 text-end">{{ __('Superficie') }}</th><th class="px-4 py-2 text-start">{{ __('Occupant') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($shops as $b)
                        @php($t = $b->leaseTariff)
                        <tr wire:key="b-{{ $b->id }}">
                            <td class="px-4 py-2.5"><p class="font-semibold text-ink">{{ $b->number }}</p><p class="text-xs text-muted">{{ $b->label }}</p></td>
                            <td class="px-4 py-2.5 text-xs">{{ $t?->leaseSector?->code }} · {{ $t?->leaseSector?->label }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ $t?->label }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-end tabular-nums">
                                {{ Format::money($t && $t->tariff_type === 'm2' && (float) $b->area > 0 ? (float) $t->value * (float) $b->area : $t?->value) }} DH/mois
                            </td>
                            <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($b->area) }} m²</td>
                            <td class="px-4 py-2.5">
                                @if ($occupants->has($b->id))
                                    <a href="{{ route('locations.fiche', ['bail' => $occupants[$b->id]->id]) }}" class="text-sm font-semibold text-accent hover:underline">{{ $occupants[$b->id]->taxpayer?->displayName() }}</a>
                                @elseif ($b->status === 'disponible')
                                    <x-badge color="green">{{ __('Libre') }}</x-badge>
                                @else
                                    <x-badge>{{ ucfirst($b->status) }}</x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="warehouse" :title="__('Aucune boutique')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
