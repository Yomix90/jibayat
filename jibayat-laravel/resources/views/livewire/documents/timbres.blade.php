@php use App\Support\Format; $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']; $max = max(1, max($stats['monthly'])); @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="package" :href="route('documents.paquets')">{{ __('Paquets') }}</x-btn>
            <x-btn variant="secondary" icon="book-copy" :href="route('documents.carnets')">{{ __('Carnets') }}</x-btn>
            <x-btn variant="gold" icon="hand-coins" :href="route('documents.versements')">{{ __('Versements') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach ([['Paquets reçus', $stats['total_paquets']], ['Carnets en stock', $stats['en_stock']], ['Carnets affectés', $stats['affectes']], ['Carnets consommés', $stats['consommes']], ['Total versé', Format::money($stats['total_verse']).' DH']] as [$l, $v])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr($l) }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $v }}</p></div>
        @endforeach
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Stock par valeur')">
            <ul class="space-y-2 text-sm">
                @forelse ($stock as $s)
                    <li class="flex items-center justify-between gap-2"><span>{{ $s->label }} <span class="text-xs text-muted">({{ Format::money($s->unit_value) }} × {{ $s->units_per_book }})</span></span><x-badge :color="$s->books ? 'green' : 'red'">{{ $s->books }} carnet(s)</x-badge></li>
                @empty
                    <li class="text-xs text-muted">{{ __('Aucune valeur : ajoutez-les dans Paramètres › Régie.') }}</li>
                @endforelse
            </ul>
        </x-card>
        <x-card :title="__('Versements de l\'année')" class="lg:col-span-2">
            <div class="flex h-36 items-end gap-1.5" role="img" aria-label="{{ __('Versements mensuels') }}">
                @foreach ($stats['monthly'] as $i => $v)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="w-full rounded-t-md bg-gold/80" style="height: {{ max(2, round($v / $max * 110)) }}px" title="{{ tr($months[$i]) }} : {{ Format::money($v) }} DH"></div>
                        <span class="text-[10px] text-muted">{{ tr($months[$i]) }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Par service')" :padding="false">
            <table class="w-full text-sm"><tbody class="divide-y divide-line">
                @forelse ($byService as $s)
                    <tr><td class="px-4 py-2 font-semibold">{{ $s->name }}</td><td class="px-4 py-2 text-xs text-muted">{{ (int) $s->assigned }} affecté(s) · {{ (int) $s->consumed }} consommé(s)</td><td class="px-4 py-2 text-end tabular-nums">{{ Format::money($s->paid) }} DH</td></tr>
                @empty
                    <tr><td class="p-4 text-xs text-muted">{{ __('Aucun service.') }}</td></tr>
                @endforelse
            </tbody></table>
        </x-card>
        <x-card :title="__('Par employé (10 premiers)')" :padding="false">
            <table class="w-full text-sm"><tbody class="divide-y divide-line">
                @forelse ($byEmployee as $e)
                    <tr><td class="px-4 py-2"><p class="font-semibold">{{ $e->last_name }} {{ $e->first_name }}</p><p class="text-xs text-muted">{{ $e->service }}</p></td><td class="px-4 py-2 text-xs text-muted">{{ (int) $e->assigned }} / {{ (int) $e->consumed }}</td><td class="px-4 py-2 text-end tabular-nums">{{ Format::money($e->paid) }} DH</td></tr>
                @empty
                    <tr><td class="p-4 text-xs text-muted">{{ __('Aucun employé.') }}</td></tr>
                @endforelse
            </tbody></table>
        </x-card>
    </div>

    <x-card :title="__('Derniers carnets affectés')" :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($assigned as $b)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2"><span class="font-mono text-xs font-bold">{{ $b->number }}</span><span class="flex-1 text-xs text-muted">{{ $b->stampValue?->label }} · n° {{ $b->first_number }}–{{ $b->last_number }}</span>
                    <span class="text-xs">{{ $b->employee?->last_name }} {{ $b->employee?->first_name }} · {{ $b->service?->name }}</span><span class="text-xs text-muted">{{ $b->assigned_on?->format('d/m/Y') }}</span></li>
            @empty
                <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun carnet affecté.') }}</li>
            @endforelse
        </ul>
    </x-card>
</div>
