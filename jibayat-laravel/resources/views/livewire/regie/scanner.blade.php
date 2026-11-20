@php
    use App\Domain\Regie\Services\DocumentRegistry;
    $states = [
        'valid' => ['circle-check', 'bg-green-50 text-green-800', 'Pièce authentique', 'Le n° et le code de contrôle correspondent au registre des documents imprimés.'],
        'number_only' => ['info', 'bg-sky-50 text-sky-800', 'Pièce au registre', 'Le n° existe ; saisissez aussi le code de contrôle imprimé à côté pour confirmer l’authenticité.'],
        'forged' => ['circle-x', 'bg-red-50 text-red-700', 'Code de contrôle faux', 'Le n° existe mais le code de contrôle ne correspond pas : la feuille ne fait pas foi.'],
        'unknown' => ['circle-x', 'bg-red-50 text-red-700', 'Pièce inconnue', 'Aucune pièce imprimée ne porte ce n° : la feuille ne fait pas foi.'],
        'invalid' => ['triangle-alert', 'bg-gold/10 text-[#7a5d16]', 'Code illisible', 'Format attendu : DOC202600001-ABC123 (ou le lien du QR).'],
    ];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-card>
        <form wire:submit="$refresh" class="flex flex-wrap items-end gap-2">
            <x-field class="flex-1" :label="__('Code lu sur la feuille (QR, douchette ou saisie)')" for="sc-code">
                <x-input id="sc-code" wire:model.live.debounce.500ms="code" autofocus autocomplete="off" placeholder="DOC202600001-ABC123" class="font-mono" />
            </x-field>
            <x-btn type="submit" icon="scan-line">{{ __('Vérifier') }}</x-btn>
        </form>
        <p class="mt-2 text-xs text-muted">{{ __('Sur téléphone : visez le QR du pied de page avec l’appareil photo, le lien ouvre directement cette page.') }}</p>
    </x-card>

    @if ($result)
        @php([$icon, $cls, $title, $text] = $states[$result['status']])
        <div class="surface flex items-start gap-3 p-5 {{ $cls }}">
            <x-svg-icon :name="$icon" class="h-7 w-7 shrink-0" />
            <div><p class="text-lg font-bold">{{ __($title) }}</p><p class="text-sm">{{ __($text) }}</p></div>
        </div>
        @if ($d = $result['document'])
            <x-card :title="__('Pièce au registre')">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-muted">{{ __('N°') }}</dt><dd class="font-mono font-bold">{{ $d->number }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Pièce') }}</dt><dd class="font-semibold">{{ $d->title }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Famille') }}</dt><dd>{{ tr(DocumentRegistry::family($d->type)) }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Imprimée le') }}</dt><dd>{{ $d->first_printed_at->format('d/m/Y H:i') }} · {{ $d->printer?->name ?? '—' }}@if ($d->print_count > 1) · {{ $d->print_count }} {{ __('impressions') }}@endif</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Scan') }}</dt><dd>@if ($d->scan_path)<a class="font-semibold text-accent" href="{{ route('regie.scan', $d) }}" target="_blank">{{ __('Joint le') }} {{ $d->scanned_at->format('d/m/Y') }}</a>@else {{ __('Pas encore scannée') }} @endif</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Rangement') }}</dt><dd>{{ $d->box ? $d->box->code.' · '.$d->box->label : __('Non rangée') }}</dd></div>
                </dl>
                @if ($result['status'] !== 'forged' && $this->allowed('archives.ajouter'))
                    <form wire:submit="attach" class="mt-4 flex flex-wrap items-end gap-2 border-t border-line pt-4">
                        <x-field class="flex-1" :label="__('Joindre le scan de la feuille')" for="sc-file" error="scan">
                            <input id="sc-file" type="file" wire:model="scan" accept=".pdf,.jpg,.jpeg,.png" capture="environment" class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                        </x-field>
                        <x-btn type="submit" variant="gold" icon="upload" wire:loading.attr="disabled" wire:target="scan,attach">{{ __('Joindre') }}</x-btn>
                    </form>
                @endif
            </x-card>
        @endif
    @endif
</div>
