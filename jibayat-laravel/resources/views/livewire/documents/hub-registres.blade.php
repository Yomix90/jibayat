@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="book-open" :href="route('documents.grand-livre')">{{ __('Grand livre') }}</x-btn>
            <x-btn variant="secondary" icon="file-text" :href="route('documents.declarations')">{{ __('Déclarations') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Total émis') }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ Format::money($totals['emitted']) }} DH</p><p class="text-xs text-muted">{{ $totals['declarations'] }} déclaration(s)</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Recouvré') }}</p><p class="mt-1 text-xl font-bold tabular-nums text-online">{{ Format::money($totals['paid']) }} DH</p><p class="text-xs text-muted">{{ $totals['receipts'] }} quittance(s)</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Reste à recouvrer') }}</p><p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ Format::money($totals['remaining']) }} DH</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Taux global') }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($totals['rate'], 1, ',', ' ') }} %</p></div>
    </section>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($stats as $key => $s)
            <div class="surface p-4" wire:key="m-{{ $key }}">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white" style="background: {{ $s['color'] }}"><x-svg-icon :name="$s['icon']" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="font-semibold leading-tight">{{ $s[app()->isLocale('ar') ? 'ar' : 'fr'] }}</p>
                        <p class="font-ar text-xs text-muted" lang="ar">{{ $s['ar'] }}</p>
                        <p class="text-[11px] text-muted">{{ $s['periodicity'] }} · {{ $s['taxpayers'] }} redevable(s)</p>
                    </div>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div><dt class="text-muted">{{ __('Émis') }}</dt><dd class="font-semibold tabular-nums">{{ Format::money($s['emitted']) }}</dd></div>
                    <div><dt class="text-muted">{{ __('Recouvré') }}</dt><dd class="font-semibold tabular-nums text-online">{{ Format::money($s['paid']) }}</dd></div>
                    <div><dt class="text-muted">{{ __('Reste') }}</dt><dd class="font-semibold tabular-nums text-red-600">{{ Format::money($s['remaining']) }}</dd></div>
                    <div><dt class="text-muted">{{ __('Taux') }}</dt><dd class="font-semibold tabular-nums">{{ number_format($s['rate'], 1, ',', ' ') }} %</dd></div>
                </dl>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-bg" role="progressbar" aria-valuenow="{{ $s['rate'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Taux de recouvrement') }}">
                    <div class="h-full rounded-full" style="width: {{ min(100, $s['rate']) }}%; background: {{ $s['color'] }}"></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1">
                    <x-btn variant="ghost" size="sm" icon="book-marked" :href="route('documents.grand-livre', ['module' => $key])">{{ __('Grand livre') }}</x-btn>
                    @if (Route::has($s['space'].'.registre'))<x-btn variant="ghost" size="sm" icon="layout-list" :href="route($s['space'].'.registre')">{{ __('Registre') }}</x-btn>@endif
                </div>
            </div>
        @endforeach
    </div>
</div>
