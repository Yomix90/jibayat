@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('regie.modifier'))<x-btn icon="notebook-tabs" :href="route('documents.carnets')">{{ __('Affecter des carnets') }}</x-btn>@endif</x-slot:actions>
    </x-page-header>

    <div class="inline-flex rounded-full border border-line bg-white p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('État') }}">
        @foreach (['affecte' => 'Chez les fonctionnaires', 'consomme' => 'Versés', '' => 'Tous'] as $k => $l)
            <button type="button" wire:click="$set('status', '{{ $k }}')" class="rounded-full px-3 py-1.5 {{ $status === $k ? 'bg-navy text-white' : 'text-muted' }}">{{ __($l) }}</button>
        @endforeach
    </div>

    @forelse ($groups as $key => $g)
        @php([$date, $emp] = explode('|', $key))
        <x-card :padding="false" wire:key="g-{{ $key }}">
            <x-slot:title>{{ $g->first()->employee ? trim($g->first()->employee->last_name.' '.$g->first()->employee->first_name) : '—' }}</x-slot:title>
            <x-slot:actions>
                <span class="text-xs text-muted">{{ $date !== '—' ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '' }} · {{ $g->count() }} {{ __('carnet(s)') }} · {{ Format::money($g->sum(fn ($b) => (float) $b->stampValue?->unit_value * (int) $b->stampValue?->units_per_book)) }} DH</span>
                <x-btn variant="secondary" size="sm" icon="printer" :href="route('regie.imprimer.decharge-vignettes', ['agent' => $emp, 'date' => $date])" target="_blank">{{ __('Bon de décharge') }}</x-btn>
            </x-slot:actions>
            <ul class="divide-y divide-line text-sm">
                @foreach ($g as $b)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2">
                        <span class="font-mono text-xs font-bold">{{ $b->number }}</span>
                        <span class="min-w-0 flex-1">{{ $b->stampValue?->label }} · {{ __('du') }} {{ $b->first_number }} {{ __('au') }} {{ $b->last_number }}@if ($b->service) · {{ $b->service->name }}@endif</span>
                        @if ($b->status === 'consomme')<x-badge color="green">{{ __('Versé') }} {{ Format::money($b->amount_paid) }}</x-badge>@else<x-badge color="gold">{{ __('À verser') }}</x-badge>@endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @empty
        <x-empty-state icon="notebook-pen" :title="__('Aucun carnet remis')" />
    @endforelse
    <div>{{ $books->links() }}</div>
</div>
