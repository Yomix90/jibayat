@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@include('livewire.regie.partials.period', ['yearOnly' => true])</x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Un mois clôturé est verrouillé : plus aucune recette, quittance, annulation ni versement ne peut y être daté. L’état mensuel (Modèle 10) et le solde sont figés.') }}</p>

    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($months as $m)
            <x-card wire:key="m-{{ $m['month'] }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $m['closure'] ? 'bg-green-50 text-green-700' : 'bg-gold/15 text-[#7a5d16]' }}"><x-svg-icon :name="$m['closure'] ? 'lock' : 'lock-open'" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ tr(Ledger::MONTHS[$m['month']]) }} {{ $year }}</p>
                        @if ($m['closure'])
                            <p class="text-xs text-muted">{{ __('Clôturé le') }} {{ $m['closure']->closed_at->format('d/m/Y H:i') }} {{ __('par') }} {{ $m['closure']->closer?->name ?? '—' }}</p>
                            <p class="mt-1 text-sm">{{ __('Recettes') }} <strong class="tabular-nums">{{ Format::money($m['closure']->receipts) }}</strong> · {{ __('versements') }} <strong class="tabular-nums">{{ Format::money($m['closure']->remittances) }}</strong> · {{ __('solde') }} <strong class="tabular-nums">{{ Format::money($m['closure']->closing_balance) }} DH</strong></p>
                        @else
                            <p class="mt-1 text-sm">{{ __('Recettes') }} <strong class="tabular-nums">{{ Format::money($m['book']['total_receipts']) }}</strong> · {{ __('versements') }} <strong class="tabular-nums">{{ Format::money($m['book']['total_remittances']) }}</strong> · {{ __('solde') }} <strong class="tabular-nums">{{ Format::money($m['book']['closing']) }} DH</strong></p>
                            @foreach ($m['blockers'] as $b)<p class="mt-1 flex items-center gap-1 text-xs text-red-600"><x-svg-icon name="circle-alert" class="h-3.5 w-3.5" />{{ $b }}</p>@endforeach
                        @endif
                        @foreach ($m['history'] as $h)
                            <p class="mt-1 text-[11px] text-muted">{{ __('Rouvert le') }} {{ $h->reopened_at->format('d/m/Y') }} : {{ $h->reopen_reason }}</p>
                        @endforeach
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @if ($m['closure'])
                            <x-badge color="green">{{ __('Clôturé') }}</x-badge>
                            @if ($this->allowed('config.gerer'))<x-btn variant="ghost" size="sm" icon="lock-open" wire:click="askReopen({{ $m['closure']->id }})">{{ __('Rouvrir') }}</x-btn>@endif
                        @elseif ($this->allowed('paiements.valider'))
                            <x-btn variant="gold" size="sm" icon="lock" wire:click="close({{ $m['month'] }})" :disabled="(bool) $m['blockers']" wire:confirm="{{ __('Clôturer le mois ? Il ne sera plus possible d’y enregistrer des opérations.') }}">{{ __('Clôturer') }}</x-btn>
                        @endif
                        <a class="text-xs font-semibold text-accent" href="{{ route('regie.etat-mensuel', ['annee' => $year, 'mois' => $m['month']]) }}">{{ __('Modèle 10') }}</a>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    @if ($reopenId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Rouvrir le mois') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('reopenId', null)"></div>
            <form wire:submit="confirmReopen" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Rouvrir le mois') }}</h2>
                <p class="text-xs text-muted">{{ __('La réouverture est motivée et inscrite au journal ; le mois devra être clôturé à nouveau.') }}</p>
                <x-field :label="__('Motif')" for="r-reason" error="reopenReason"><x-textarea id="r-reason" rows="2" wire:model="reopenReason" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('reopenId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="danger" icon="lock-open">{{ __('Rouvrir') }}</x-btn></div>
            </form>
        </div>
    @endif
</div>
