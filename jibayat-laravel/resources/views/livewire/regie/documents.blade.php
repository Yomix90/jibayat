@php use App\Domain\Regie\Services\DocumentRegistry; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="scan-line" :href="route('regie.scanner')">{{ __('Scanner') }}</x-btn></x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Chaque pièce imprimée par Jibayat porte en pied de page son n° de registre et un QR de contrôle. Une réimpression garde le même n°.') }}</p>

    <div class="grid gap-2 sm:grid-cols-4">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N° DOC, titre…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="family" :aria-label="__('Famille')"><option value="">{{ __('Toutes les familles') }}</option>@foreach ($families as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select>
        <x-select wire:model.live="state" :aria-label="__('État')"><option value="">{{ __('Toutes') }}</option><option value="unscanned">{{ __('Non scannées') }}</option><option value="scanned">{{ __('Scannées') }}</option><option value="unfiled">{{ __('Non rangées') }}</option></x-select>
        <x-select wire:model.live="box" :aria-label="__('Boîte')"><option value="">{{ __('Toutes les boîtes') }}</option>@foreach ($boxes as $b)<option value="{{ $b->id }}">{{ $b->code }} · {{ $b->label }}</option>@endforeach</x-select>
    </div>

    @if ($selected && $this->allowed('archives.modifier'))
        <div class="material sticky top-3 z-10 flex flex-wrap items-center gap-2 rounded-full px-4 py-2" style="box-shadow: var(--shadow-md)">
            <span class="me-auto text-sm">{{ count($selected) }} {{ __('sélectionnée(s)') }}</span>
            <x-select wire:model="targetBox" :aria-label="__('Boîte')" class="!w-56"><option value="">— {{ __('Boîte') }} —</option><option value="none">{{ __('Retirer de la boîte') }}</option>@foreach ($boxes->where('is_closed', false) as $b)<option value="{{ $b->id }}">{{ $b->code }} · {{ $b->label }}</option>@endforeach</x-select>
            <x-btn variant="gold" size="sm" icon="archive" wire:click="fileSelected">{{ __('Ranger') }}</x-btn>
            @error('targetBox')<span class="w-full text-xs text-red-600">{{ $message }}</span>@enderror
        </div>
    @endif

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $d)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="doc-{{ $d->id }}">
                    @if ($this->allowed('archives.modifier'))<input type="checkbox" value="{{ $d->id }}" wire:model.live="selected" class="h-4 w-4 rounded border-line text-gold" aria-label="{{ __('Sélectionner') }} {{ $d->number }}">@endif
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $d->number }}</span><span class="font-semibold">{{ $d->title }}</span><x-badge>{{ tr(DocumentRegistry::family($d->type)) }}</x-badge></p>
                        <p class="text-xs text-muted">{{ __('Imprimé le') }} {{ $d->first_printed_at->format('d/m/Y H:i') }} {{ __('par') }} {{ $d->printer?->name ?? '—' }}@if ($d->print_count > 1) · {{ $d->print_count }} {{ __('impressions') }}, {{ __('dernière le') }} {{ $d->last_printed_at->format('d/m/Y') }}@endif
                            · {{ __('contrôle') }} <span class="font-mono">{{ $d->token }}</span>@if ($d->box) · {{ __('boîte') }} {{ $d->box->code }}@endif</p>
                    </div>
                    @if ($d->scan_path)<x-badge color="green">{{ __('Scannée') }}</x-badge>@else<x-badge color="gold">{{ __('À scanner') }}</x-badge>@endif
                    @if ($d->url)<x-btn variant="ghost" size="sm" icon="printer" :href="$d->url" target="_blank" :aria-label="__('Réimprimer')" />@endif
                    @if ($d->scan_path)<x-btn variant="ghost" size="sm" icon="file-search" :href="route('regie.scan', $d)" target="_blank" :aria-label="__('Voir le scan')" />@endif
                    @if ($this->allowed('archives.ajouter'))<x-btn variant="secondary" size="sm" icon="upload" wire:click="askScan({{ $d->id }})">{{ $d->scan_path ? __('Remplacer le scan') : __('Joindre le scan') }}</x-btn>@endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="qr-code" :title="__('Aucune pièce imprimée pour ces critères')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    @if ($scanId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Joindre le scan') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('scanId', null)"></div>
            <form wire:submit="saveScan" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Joindre le scan') }}</h2>
                <x-field :label="__('Fichier (PDF, JPG, PNG — 10 Mo max)')" for="sc-f" error="scan">
                    <input id="sc-f" type="file" wire:model="scan" accept=".pdf,.jpg,.jpeg,.png" capture="environment" class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                </x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('scanId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="gold" icon="upload" wire:loading.attr="disabled" wire:target="scan,saveScan">{{ __('Joindre') }}</x-btn></div>
            </form>
        </div>
    @endif
</div>
