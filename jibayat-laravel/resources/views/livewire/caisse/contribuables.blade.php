@php use App\Support\Format; use App\Domain\Contribuables\Services\TaxpayerService; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($this->allowed('contribuables.exporter'))
                <x-btn variant="secondary" icon="file-spreadsheet" :href="route('caisse.contribuables-export', $query)">Excel</x-btn>
                <x-btn variant="secondary" icon="printer" :href="route('caisse.contribuables-imprimer', $query)" target="_blank">{{ __('Imprimer') }}</x-btn>
            @endif
            @if ($this->allowed('contribuables.ajouter'))<x-btn icon="user-plus" :href="route('caisse.nouveau-contribuable')">{{ __('Nouveau contribuable') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([['users', 'Contribuables', $rows->total(), '#1E2A3A'], ['triangle-alert', 'Avec impayés', $debtors, '#DC2626'],
                   ['wallet', 'Total impayé', Format::money($totalUnpaid).' DH', '#C8601E'], ['user-x', 'Sans dossier', $withoutModule, '#5B7590']] as [$icon, $label, $value, $color])
            <div class="surface p-4">
                <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg text-white" style="background: {{ $color }}"><x-svg-icon :name="$icon" class="h-4 w-4" /></span>
                    <p class="text-xs font-semibold text-muted">{{ tr($label) }}</p></div>
                <p class="mt-2 text-xl font-bold tabular-nums text-ink">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-2 sm:grid-cols-[2fr_1fr]">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Nom, CIN, n°, raison sociale, téléphone, adresse…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="module" :aria-label="__('Taxe')">
            <option value="">{{ __('Toutes les taxes') }}</option>
            @foreach (TaxpayerService::MODULES as $code => [, $label])<option value="{{ $code }}">{{ tr($label) }}</option>@endforeach
            <option value="AUCUN">{{ __('Sans dossier') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $t)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="t-{{ $t->id }}">
                    <a href="{{ route('caisse.fiche-contribuable', ['ctb' => $t->id]) }}" class="min-w-0 flex-1 rounded-lg focus-visible:ring-2 focus-visible:ring-gold">
                        <p class="truncate font-semibold text-ink">{{ $t->displayName() }} @if ($t->displayNameAr())<span class="font-normal text-muted" dir="rtl">· {{ $t->displayNameAr() }}</span>@endif</p>
                        <p class="text-xs text-muted"><span class="font-mono">{{ $t->number }}</span>@if ($t->cin) · CIN {{ $t->cin }}@endif @if ($t->ice) · ICE {{ $t->ice }}@endif @if ($t->phone) · {{ $t->phone }}@endif</p>
                    </a>
                    <div class="flex flex-wrap gap-1">@foreach ($t->modules as $m)<x-badge>{{ tr($m['label']) }}@if ($m['nb'] > 1) ×{{ $m['nb'] }}@endif</x-badge>@endforeach</div>
                    <span class="w-28 text-end text-sm font-semibold tabular-nums {{ $t->unpaid > 0 ? 'text-red-600' : 'text-online' }}">{{ $t->unpaid > 0 ? Format::money($t->unpaid).' DH' : 'À jour' }}</span>
                    @if ($this->allowed('contribuables.supprimer'))
                        <x-btn variant="ghost" size="sm" icon="user-minus" wire:click="deactivate({{ $t->id }})" wire:confirm="Retirer {{ $t->displayName() }} du répertoire ? Ses dossiers et son historique sont conservés." aria-label="Retirer {{ $t->displayName() }}" />
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="users" :title="__('Aucun contribuable pour ces critères')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>
</div>
