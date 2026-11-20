@php use App\Support\Format; use App\Domain\Caisse\Services\NoticeService; $canWrite = $this->allowed('bulletins.creer'); @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($this->allowed('avis.exporter'))<x-btn variant="secondary" icon="file-spreadsheet" :href="route('caisse.avis-export', array_filter(['module' => $module]))">Excel</x-btn> @endif
            <x-btn variant="secondary" icon="send" :href="route('caisse.lettres')">{{ __('Lettres de relance') }}</x-btn>
            @if ($canWrite)<x-btn variant="gold" icon="mail-plus" wire:click="generate" wire:confirm="Générer un avis pour chaque impayé{{ $module ? ' ('.(NoticeService::LABELS[$module] ?? $module).')' : '' }} qui n'en a pas encore ?">{{ __('Générer les avis') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Total impayé') }}</p><p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ Format::money($total) }} DH</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Déclarations impayées') }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $groups->sum(fn ($g) => $g['rows']->count()) }}</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Avis déjà émis') }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $groups->sum('nb_avis_emis') }}</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Redevables concernés') }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $groups->flatMap(fn ($g) => $g['rows']->pluck('taxpayer_id'))->unique()->count() }}</p></div>
    </section>

    <div class="grid gap-2 sm:grid-cols-3">
        <x-select wire:model.live="module" :aria-label="__('Taxe')"><option value="">{{ __('Toutes les taxes') }}</option>@foreach (NoticeService::LABELS as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select>
        <x-select wire:model.live="status" :aria-label="__('Statut de l\'avis')"><option value="">{{ __('Tous') }}</option><option value="emis">{{ __('Avis émis') }}</option></x-select>
        <x-select wire:model.live="year" :aria-label="__('Année')"><option value="">{{ __('Toutes les années') }}</option>@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</x-select>
    </div>

    @if ($canWrite && count($selected))
        <div class="material sticky top-3 z-10 flex flex-wrap items-center gap-3 rounded-2xl px-4 py-2" style="box-shadow: var(--shadow-md)">
            <span class="text-sm font-semibold">{{ count($selected) }} avis sélectionné(s)</span>
            <x-select wire:model="letterType" class="!w-48" :aria-label="__('Type de lettre')">@foreach (NoticeService::LETTER_TYPES as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select>
            <x-btn variant="gold" size="sm" icon="send" wire:click="createLetter">{{ __('Créer la lettre') }}</x-btn>
            <x-btn variant="ghost" size="sm" wire:click="$set('selected', [])">{{ __('Désélectionner') }}</x-btn>
        </div>
    @endif

    @forelse ($groups as $g)
        <x-card :padding="false" wire:key="g-{{ $g['module'] }}">
            <x-slot:title>{{ tr($g['label']) }}</x-slot:title>
            <x-slot:actions><span class="text-xs text-muted">{{ $g['nb_redevables'] }} redevable(s) · {{ $g['nb_avis_emis'] }} avis · <strong class="tabular-nums text-red-600">{{ Format::money($g['total']) }} DH</strong></span></x-slot:actions>
            <ul class="divide-y divide-line text-sm">
                @foreach ($g['rows'] as $r)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="r-{{ $r->decl_id }}">
                        @if ($canWrite && $r->notice_id && ! $r->reminder_letter_id)
                            <input type="checkbox" value="{{ $r->notice_id }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold" aria-label="Inclure l'avis {{ $r->notice_number }}">
                        @else
                            <span class="w-4"></span>
                        @endif
                        <a href="{{ route('caisse.fiche-contribuable', ['ctb' => $r->taxpayer_id]) }}" class="min-w-0 flex-1 hover:underline">
                            <span class="font-semibold">{{ trim($r->last_name.' '.$r->first_name) ?: $r->company_name }}</span>
                            <span class="block text-xs text-muted"><span class="font-mono">{{ $r->decl_number }}</span> · {{ $r->year }}{{ $r->quarter ? ' T'.$r->quarter : '' }}@if ($r->cin) · {{ $r->cin }}@endif @if ($r->phone) · {{ $r->phone }}@endif</span>
                        </a>
                        <span class="font-semibold tabular-nums">{{ Format::money($r->total) }}</span>
                        @if ($r->notice_id)
                            <x-badge :color="$r->reminder_letter_id ? 'blue' : 'gold'">{{ $r->notice_number }}{{ $r->reminder_letter_id ? ' · en lettre' : '' }}</x-badge>
                        @elseif ($canWrite)
                            <x-btn variant="ghost" size="sm" icon="mail-plus" wire:click="generateOne({{ $r->decl_id }})">{{ __('Émettre l\'avis') }}</x-btn>
                        @else
                            <x-badge>{{ __('Sans avis') }}</x-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @empty
        <x-card><x-empty-state icon="check" :title="__('Aucun impayé pour ces critères')" /></x-card>
    @endforelse

    <x-card :title="__('Recouvrement par taxe (toutes déclarations)')" :padding="false">
        <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-4 py-2 text-start">{{ __('Taxe') }}</th><th class="px-4 py-2 text-end">{{ __('Déclarations') }}</th>
                <th class="px-4 py-2 text-end">{{ __('Redevables') }}</th><th class="px-4 py-2 text-end">{{ __('Émis') }}</th><th class="px-4 py-2 text-end">{{ __('Recouvré') }}</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($stats as $s)
                    <tr><td class="px-4 py-2">{{ NoticeService::LABELS[$s->module] ?? $s->module }}</td><td class="px-4 py-2 text-end tabular-nums">{{ $s->nb }}</td>
                        <td class="px-4 py-2 text-end tabular-nums">{{ $s->nb_redevables }}</td><td class="px-4 py-2 text-end tabular-nums">{{ Format::money($s->total_du) }}</td>
                        <td class="px-4 py-2 text-end font-semibold tabular-nums text-online">{{ Format::money($s->total_recouvre) }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    </x-card>
</div>
