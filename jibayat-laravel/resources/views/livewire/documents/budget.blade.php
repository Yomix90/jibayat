@php
    use App\Support\Format;
    use App\Domain\Documents\Support\BudgetNomenclature;
    $ar = $lang === 'ar';
    $canEdit = $this->allowed('budget.modifier');
    $rateColor = fn ($r) => $r >= 100 ? 'text-online' : ($r >= 50 ? 'text-gold' : 'text-red-600');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-select wire:model.live="year" :aria-label="__('Exercice')" class="!w-28">@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</x-select>
            <div class="inline-flex rounded-full border border-line bg-white p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('Langue des libellés') }}">
                <button type="button" wire:click="$set('lang', 'fr')" class="rounded-full px-3 py-1.5 {{ $ar ? 'text-muted' : 'bg-navy text-white' }}">FR</button>
                <button type="button" wire:click="$set('lang', 'ar')" class="rounded-full px-3 py-1.5 {{ $ar ? 'bg-navy text-white' : 'text-muted' }}">ع</button>
            </div>
            @if ($this->allowed('budget.exporter'))<x-btn variant="secondary" icon="file-spreadsheet" :href="route('documents.budget-export', ['annee' => $year, 'lang' => $lang])">Excel</x-btn> @endif
            <x-btn variant="secondary" icon="printer" :href="route('documents.budget-imprimer', ['annee' => $year, 'lang' => $lang])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="Totaux {{ $year }}">
        @foreach ([["Réalisé $annee_n1", $grand_total['total_n1'], 'text-ink'], ["Proposé $year", $grand_total['total_propose'], 'text-ink'], ["Réalisé $year", $grand_total['total_realise'], 'text-online']] as [$l, $v, $c])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr($l) }}</p><p class="mt-1 text-lg font-bold tabular-nums {{ $c }}">{{ Format::money($v) }} DH</p></div>
        @endforeach
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Taux de réalisation') }}</p>
            <p class="mt-1 text-lg font-bold tabular-nums {{ $rateColor($grand_total['total_taux']) }}">{{ number_format($grand_total['total_taux'], 1, ',', ' ') }} %</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-bg"><div class="h-full rounded-full bg-gold" style="width: {{ min(100, $grand_total['total_taux']) }}%"></div></div>
            <p class="mt-1 text-xs text-muted">Écart : {{ Format::money($grand_total['total_ecart']) }} DH</p></div>
    </section>

    @if ($canEdit)
        <div class="flex flex-wrap gap-2">
            <x-btn variant="gold" icon="refresh-cw" wire:click="sync" wire:loading.attr="disabled" wire:confirm="Remplacer le réalisé {{ $year }} des chapitres liés à Jibayat (TNB, loyers, souk, boissons, ODP, transport, fourrière) par les encaissements de l'année ?">{{ __('Synchroniser avec les encaissements') }}</x-btn>
            <x-btn variant="secondary" icon="upload" wire:click="$set('showImport', true)">{{ __('Importer un fichier Excel') }}</x-btn>
            <x-btn variant="secondary" icon="plus" wire:click="openAdd">{{ __('Ajouter un chapitre') }}</x-btn>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        @foreach ($domaines as $d)
            <x-card :padding="false" wire:key="d-{{ $d['code'] }}">
                <x-slot:title><span @if ($ar) dir="rtl" @endif>{{ $ar ? $d['nom_ar'] : $d['nom_fr'] }}</span></x-slot:title>
                <x-slot:actions><span class="text-xs text-muted">{{ count($d['lignes']) }} chapitre(s) · <strong class="tabular-nums {{ $rateColor($d['total_taux']) }}">{{ number_format($d['total_taux'], 1, ',', ' ') }} %</strong></span></x-slot:actions>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                            <tr><th class="px-3 py-2 text-start">{{ __('Chapitre') }}</th><th class="px-3 py-2 text-start">{{ __('Nature des recettes') }}</th>
                                <th class="px-3 py-2 text-end">Réalisé {{ $annee_n1 }}</th><th class="px-3 py-2 text-end">Proposé {{ $year }}</th><th class="px-3 py-2 text-end">Réalisé {{ $year }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Taux') }}</th><th class="px-3 py-2 text-end">{{ __('Écart') }}</th>@if ($canEdit)<th></th>@endif</tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($d['lignes'] as $l)
                                <tr wire:key="l-{{ $l->id }}">
                                    <td class="whitespace-nowrap px-3 py-2"><p class="font-mono text-xs font-bold">{{ $l->chapter }}</p><p class="font-mono text-[11px] text-muted">{{ $l->gir_code }}</p></td>
                                    <td class="min-w-56 px-3 py-2"><p @if ($ar) dir="rtl" class="text-end" @endif>{{ $ar ? ($l->label_ar ?: $l->label) : ($l->label_fr ?: $l->label) }}</p>
                                        @if ($l->module)<x-badge color="gold">synchronisé · {{ $l->module }}</x-badge>@endif</td>
                                    @foreach (['previous_actual', 'proposed', 'actual'] as $f)
                                        <td class="px-2 py-2 text-end">
                                            @if ($canEdit)
                                                <x-input type="number" step="0.01" min="0" wire:model="values.{{ $l->id }}.{{ $f }}" class="!w-32 text-end tabular-nums" aria-label="{{ $f }} {{ $l->chapter }}" />
                                                @error("values.{$l->id}.{$f}")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                            @else
                                                <span class="tabular-nums">{{ Format::money($l->{$f}) }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-end font-semibold tabular-nums {{ $rateColor($l->rate) }}">{{ number_format($l->rate, 1, ',', ' ') }} %</td>
                                    <td class="px-3 py-2 text-end tabular-nums {{ $l->gap < 0 ? 'text-red-600' : 'text-online' }}">{{ Format::money($l->gap) }}</td>
                                    @if ($canEdit)
                                        <td class="px-2 py-2 text-end">@if ($this->allowed('budget.supprimer'))<x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteLine({{ $l->id }})" wire:confirm="Retirer le chapitre {{ $l->chapter }} du budget {{ $year }} ?" aria-label="Retirer le chapitre {{ $l->chapter }}" />@endif</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td colspan="2" class="px-3 py-2">{{ __('Sous-total') }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['total_n1']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['total_propose']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['total_realise']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ number_format($d['total_taux'], 1, ',', ' ') }} %</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['total_ecart']) }}</td>@if ($canEdit)<td></td>@endif</tr></tfoot>
                    </table>
                </div>
            </x-card>
        @endforeach
        @if ($canEdit)
            <div class="material sticky bottom-3 z-10 flex items-center justify-between gap-3 rounded-full px-4 py-2" style="box-shadow: var(--shadow-md)">
                <span class="text-xs text-muted">{{ __('Les montants saisis ne sont enregistrés qu\'au clic.') }}</span>
                <x-btn type="submit" variant="gold" icon="check" wire:loading.attr="disabled" wire:target="save">Enregistrer le budget {{ $year }}</x-btn>
            </div>
        @endif
    </form>

    <x-panel show="showImport" title="Importer le budget {{ $year }} (Excel)">
        <form wire:submit="import" class="space-y-3">
            <p class="text-xs text-muted">Colonnes attendues : chapitre, nature, réalisé N-1, proposé, réalisé. Les lignes « المجموع » changent de domaine ; la feuille dont le nom contient {{ $year }} est lue en priorité. Un réalisé vide conserve le montant existant.</p>
            <x-field :label="__('Fichier (.xls, .xlsx)')" for="b-file" error="file">
                <input id="b-file" type="file" wire:model="file" accept=".xls,.xlsx" class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
            </x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showImport', false)">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" variant="gold" icon="upload" wire:loading.attr="disabled" wire:target="file,import">{{ __('Importer') }}</x-btn></div>
        </form>
    </x-panel>

    <x-panel show="showAdd" title="Ajouter un chapitre au budget {{ $year }}" max-width="max-w-xl">
        <form wire:submit="addLine" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Chapitre')" for="n-chap" error="line.chapter"><x-input id="n-chap" wire:model="line.chapter" placeholder="40.10.20.26" /></x-field>
                <x-field :label="__('Code GIR')" for="n-gir" error="line.gir_code"><x-input id="n-gir" wire:model="line.gir_code" inputmode="numeric" /></x-field>
            </div>
            <x-field :label="__('Libellé (français)')" for="n-fr" error="line.label_fr"><x-input id="n-fr" wire:model="line.label_fr" /></x-field>
            <x-field :label="__('Libellé (arabe)')" for="n-ar" error="line.label_ar"><x-input id="n-ar" wire:model="line.label_ar" dir="rtl" /></x-field>
            <x-field :label="__('Domaine')" for="n-dom" error="line.domain"><x-select id="n-dom" wire:model="line.domain">@foreach (BudgetNomenclature::DOMAINS as $k => [$dar, $dfr])<option value="{{ $k }}">{{ $dfr }} — {{ $dar }}</option>@endforeach</x-select></x-field>
            <div class="grid grid-cols-3 gap-3">
                <x-field :label="__('Réalisé N-1')" for="n-n1" error="line.previous_actual"><x-input id="n-n1" type="number" step="0.01" min="0" wire:model="line.previous_actual" /></x-field>
                <x-field :label="__('Proposé')" for="n-p" error="line.proposed"><x-input id="n-p" type="number" step="0.01" min="0" wire:model="line.proposed" /></x-field>
                <x-field :label="__('Réalisé')" for="n-r" error="line.actual"><x-input id="n-r" type="number" step="0.01" min="0" wire:model="line.actual" /></x-field>
            </div>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showAdd', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="plus">{{ __('Ajouter') }}</x-btn></div>
        </form>
    </x-panel>
</div>
