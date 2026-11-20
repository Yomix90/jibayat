@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@include('livewire.regie.partials.period')</x-slot:actions>
    </x-page-header>

    <x-card :title="__('Contrôles')">
        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($c['checks'] as $label => $ok)
                <li class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm {{ $ok ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700' }}"><x-svg-icon :name="$ok ? 'circle-check' : 'circle-x'" class="h-4 w-4" />{{ __($label) }}</li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-muted">{{ __('Recettes du mois (caisse)') }} {{ Format::money($c['book']['total_receipts']) }} DH · {{ __('versées au comptable sur la période') }} {{ Format::money($c['remitted']) }} DH
            @if ($c['no_reference']) · {{ $c['no_reference'] }} {{ __('saisie(s) sans référence') }}@endif</p>
    </x-card>

    @if ($c['duplicates'])
        <x-card :title="__('Références en double')" :padding="false">
            <ul class="divide-y divide-line text-sm">@foreach ($c['duplicates'] as $d)<li class="flex justify-between gap-3 px-4 py-2"><span><strong>{{ $d->reference }}</strong> · {{ $d->n }} {{ __('saisies') }} : {{ $d->numbers }}</span><span class="tabular-nums">{{ Format::money($d->total) }}</span></li>@endforeach</ul>
        </x-card>
    @endif

    <x-card :padding="false" :title="__('Recettes par jour')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Date') }}</th><th class="px-3 py-2 text-end">{{ __('Pièces') }}</th>
                    <th class="px-3 py-2 text-end">{{ __('Bulletins des modules') }}</th><th class="px-3 py-2 text-end">{{ __('Saisies de la régie') }}</th><th class="px-3 py-2 text-end">{{ __('dont virements Ministère') }}</th><th class="px-3 py-2 text-end">{{ __('Total') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($c['days'] as $d)
                        <tr><td class="px-3 py-2"><a class="font-semibold hover:text-accent" href="{{ route('regie.situation', ['date' => $d['date']]) }}">{{ \Carbon\Carbon::parse($d['date'])->format('d/m/Y') }}</a></td><td class="px-3 py-2 text-end tabular-nums">{{ $d['pieces'] }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['bv']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['saisie']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums text-muted">{{ $d['ministry'] ? Format::money($d['ministry']) : '—' }}</td><td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($d['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="git-compare" :title="__('Aucune recette ce mois')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
