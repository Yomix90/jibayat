@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="$case->registration.' — dossier '.$case->number" :subtitle="$case->vehicle_type.' · entré le '.$case->impounded_on?->format('d/m/Y')">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('fourriere.fiche', ['dossier' => $case->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('caseId', null)">{{ __('Autre véhicule') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @if ($blocked)
        <x-card><x-empty-state icon="info" :title="__('Encaissement impossible')">{{ __('Ce dossier a déjà un bulletin en attente, est déjà payé ou le véhicule est sorti.') }}</x-empty-state></x-card>
    @else
        <form wire:submit="pay" class="grid gap-4 lg:grid-cols-3">
            <x-card :title="__('Propriétaire')" class="lg:col-span-2">
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-field :label="__('Nom et prénom')" for="o-name" error="owner_name" class="sm:col-span-3"><x-input id="o-name" wire:model="owner_name" /></x-field>
                    <x-field label="CIN" for="o-cin" error="owner_cin"><x-input id="o-cin" wire:model="owner_cin" /></x-field>
                    <x-field :label="__('Téléphone')" for="o-phone" error="owner_phone"><x-input id="o-phone" wire:model="owner_phone" /></x-field>
                </div>
                <p class="mt-3 text-[11px] text-muted">Après validation du bulletin par le régisseur, le véhicule passe « en attente de sortie » ; la sortie se valide ensuite depuis la fiche ou la page Sortie.</p>
            </x-card>
            <x-card :title="__('Frais de fourrière')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ $info['days'] }} jour(s) × {{ Format::money($case->daily_rate) }} + remorquage {{ Format::money($case->towing_fee) }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($info['amount']) }} DH</p>
                    </div>
                    <x-field label="N° de bulletin (vide = automatique)" for="p-slip" error="slip_number"><x-input id="p-slip" wire:model="slip_number" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full">{{ __('Émettre le bulletin') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @endif
</div>
