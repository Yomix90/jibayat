@php
    use App\Domain\Declarations\Services\DeclarationService;
    $ar = app()->isLocale('ar');
    $label = fn ($x) => $ar ? $x['ar'] : $x['fr'];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn icon="file-plus" :href="route('declarations.nouvelle')">{{ __('Nouvelle déclaration') }}</x-btn></x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Déclarations déposées'), $kpi['total'], 'text-ink'], [__('Déposées hors délai'), $kpi['late'], 'text-red-600'],
                   [__('Non signées'), $kpi['unsigned'], 'text-[#8a5a12]'], [__('Arrêts d’activité'), $kpi['cessations'], 'text-ink']] as [$l, $v, $c])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-2xl font-bold tabular-nums {{ $c }}">{{ $v }}</p></div>
        @endforeach
    </section>

    <div class="grid gap-2 sm:grid-cols-4">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N°, redevable, CIN, motif…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="module" :aria-label="__('Taxe')"><option value="">{{ __('Toutes les taxes') }}</option>@foreach (DeclarationService::MODULES as $k => $m)<option value="{{ $k }}">{{ $label($m) }}</option>@endforeach</x-select>
        <x-select wire:model.live="type" :aria-label="__('Type')" :disabled="! $module"><option value="">{{ __('Tous les types') }}</option>@if ($module)@foreach (DeclarationService::TYPES[$module] as $k => $ty)<option value="{{ $k }}">{{ $label($ty) }}</option>@endforeach @endif</x-select>
        <x-select wire:model.live="status" :aria-label="__('État')"><option value="">{{ __('Toutes') }}</option><option value="late">{{ __('Hors délai') }}</option><option value="unsigned">{{ __('Non signées') }}</option><option value="cancelled">{{ __('Annulées') }}</option></x-select>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $d)
                @php($ty = DeclarationService::TYPES[$d->module][$d->type] ?? ['fr' => $d->type, 'ar' => $d->type, 'art' => '—'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $d->cancelled_at ? 'opacity-60' : '' }}" wire:key="d-{{ $d->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $d->number }}</span>
                            <span class="font-semibold">{{ $label($ty) }}</span><x-badge>{{ $ty['art'] === '—' ? __('Interne') : __('Art.').' '.$ty['art'] }}</x-badge></p>
                        <p class="text-xs text-muted">{{ $d->taxpayer?->displayName() }} · {{ DeclarationService::objectLabel($d->declarable) }}
                            @if ($d->fiscal_year) · {{ __('Année') }} {{ $d->fiscal_year }}@endif
                            @if ($d->event_on) · {{ __('Fait le') }} {{ $d->event_on->format('d/m/Y') }}@endif
                            @if ($d->resume_on) · {{ __('Reprise') }} {{ $d->resume_on->format('d/m/Y') }}@endif
                            @if ($d->reason) · {{ $d->reason }}@endif</p>
                        <p class="text-xs text-muted">{{ __('Déposée le') }} {{ $d->filed_on->format('d/m/Y') }}@if ($d->deadline) · {{ __('délai') }} {{ $d->deadline->format('d/m/Y') }}@endif @if ($d->agent) · {{ $d->agent->name }}@endif
                            @if ($d->cancelled_at) · <span class="text-red-600">{{ __('Annulée') }} : {{ $d->cancellation_reason }}</span>@endif</p>
                    </div>
                    @if ($d->is_late)<x-badge color="red">{{ __('Hors délai') }}</x-badge>@endif
                    @if ($d->signed)<x-badge color="green">{{ __('Signée') }}</x-badge>@elseif (! $d->cancelled_at)<x-badge color="gold">{{ __('À faire signer') }}</x-badge>@endif
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('declarations.imprimer', $d)" target="_blank">{{ __('Imprimer') }}</x-btn>
                    @if (! $d->cancelled_at && $this->allowed(DeclarationService::MODULES[$d->module]['perm'].'.modifier'))
                        @unless ($d->signed)<x-btn variant="ghost" size="sm" icon="signature" wire:click="markSigned({{ $d->id }})">{{ __('Signée') }}</x-btn>@endunless
                        <x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $d->id }})" :aria-label="__('Annuler')" />
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="files" :title="__('Aucune déclaration pour ces critères')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    @if ($cancelId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Annuler la déclaration') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('cancelId', null)"></div>
            <form wire:submit="confirmCancel" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Annuler la déclaration') }}</h2>
                <p class="text-xs text-muted">{{ __('La déclaration reste au registre, marquée annulée avec son motif. Si c’était un arrêt ou une reprise, le statut de l’établissement est rétabli.') }}</p>
                <x-field :label="__('Motif')" for="c-reason" error="cancelReason"><x-textarea id="c-reason" rows="2" wire:model="cancelReason" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('cancelId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="danger" icon="ban">{{ __('Annuler la déclaration') }}</x-btn></div>
            </form>
        </div>
    @endif
</div>
