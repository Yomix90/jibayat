@php $pct = fn ($a, $b) => $b ? round($a / $b * 100) : 0; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Pièces imprimées'), $c['total'], ''], [__('Scannées'), $c['scanned'].' ('.$pct($c['scanned'], $c['total']).' %)', 'text-online'], [__('Rangées en boîte'), $c['filed'].' ('.$pct($c['filed'], $c['total']).' %)', ''], [__('Non scannées depuis 30 jours'), $c['late'], $c['late'] ? 'text-red-600' : '']] as [$l, $v, $cl])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums {{ $cl }}">{{ $v }}</p></div>
        @endforeach
    </section>

    <x-card :padding="false" :title="__('Par famille')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Famille') }}</th><th class="px-3 py-2 text-end">{{ __('Imprimées') }}</th><th class="px-3 py-2 text-end">{{ __('Scannées') }}</th><th class="px-3 py-2 text-end">{{ __('Rangées') }}</th><th class="px-3 py-2">{{ __('Suivi des scans') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($c['families'] as $f)
                        <tr><td class="px-3 py-2 font-semibold">{{ tr($f['family']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ $f['total'] }}</td><td class="px-3 py-2 text-end tabular-nums">{{ $f['scanned'] }}</td><td class="px-3 py-2 text-end tabular-nums">{{ $f['filed'] }}</td>
                            <td class="px-3 py-2"><div class="h-1.5 w-full min-w-24 overflow-hidden rounded-full bg-bg"><div class="h-full rounded-full bg-gold" style="width: {{ $pct($f['scanned'], $f['total']) }}%"></div></div></td></tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="scan-search" :title="__('Aucune pièce imprimée')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($late->isNotEmpty())
        <x-card :padding="false" :title="__('À scanner en priorité')">
            <ul class="divide-y divide-line text-sm">@foreach ($late as $d)<li class="flex flex-wrap justify-between gap-2 px-4 py-2"><span><span class="font-mono text-xs font-bold">{{ $d->number }}</span> {{ $d->title }}</span><span class="text-xs text-muted">{{ $d->first_printed_at->format('d/m/Y') }} · {{ $d->printer?->name }}</span></li>@endforeach</ul>
            <div class="border-t border-line px-4 py-2"><a class="text-xs font-semibold text-accent" href="{{ route('regie.documents', ['etat' => 'unscanned']) }}">{{ __('Ouvrir le registre') }}</a></div>
        </x-card>
    @endif
</div>
