@php
    use App\Support\Format;
    use App\Domain\Caisse\Services\NoticeService;
    $canWrite = $this->allowed('bulletins.creer');
    $st = ['brouillon' => ['Brouillon', 'gold'], 'envoyee' => ['Envoyée', 'green'], 'annulee' => ['Annulée', 'red'], 'annule' => ['Annulée', 'red']];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="mail-warning" :href="route('caisse.avis')">{{ __('Avis de non-paiement') }}</x-btn></x-slot:actions>
    </x-page-header>

    @if ($canWrite)
        <x-card :title="__('Nouvelle lettre à partir des avis émis')">
            <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <x-field :label="__('Taxe')" for="l-mod"><x-select id="l-mod" wire:model="module"><option value="">{{ __('Toutes les taxes') }}</option>@foreach (NoticeService::LABELS as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select></x-field>
                <x-field :label="__('Type')" for="l-type"><x-select id="l-type" wire:model="letterType">@foreach (NoticeService::LETTER_TYPES as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select></x-field>
                <x-btn variant="gold" icon="send" wire:click="create">{{ __('Créer la lettre') }}</x-btn>
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('Pour choisir les avis un par un, cochez-les dans la page « Avis de non-paiement ».') }}</p>
        </x-card>
    @endif

    @if ($current)
        <x-card :padding="false">
            <x-slot:title>Lettre {{ $current->number }} · {{ NoticeService::LETTER_TYPES[$current->type] ?? $current->type }}</x-slot:title>
            <x-slot:actions>
                <x-btn variant="secondary" size="sm" icon="printer" :href="route('caisse.lettre-imprimer', $current)" target="_blank">{{ __('Imprimer') }}</x-btn>
                <x-btn variant="ghost" size="sm" icon="x" wire:click="$set('letterId', null)" :aria-label="__('Fermer')" />
            </x-slot:actions>
            <ul class="divide-y divide-line text-sm">
                @foreach ($lines as $l)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2"><span class="min-w-0 flex-1"><span class="font-semibold">{{ trim($l->last_name.' '.$l->first_name) ?: $l->company_name }}</span>
                        <span class="block text-xs text-muted">{{ NoticeService::LABELS[$l->module] ?? $l->module }} {{ $l->year }} · {{ $l->decl_number }} · avis {{ $l->notice_number }}</span></span>
                        <span class="font-semibold tabular-nums">{{ Format::money($l->amount_due) }}</span></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card :title="__('Lettres')" :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($letters as $l)
                @php([$sl, $sc] = $st[$l->status] ?? [$l->status, null])
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="l-{{ $l->id }}">
                    <button type="button" wire:click="$set('letterId', {{ $l->id }})" class="min-w-0 flex-1 text-start hover:underline">
                        <span class="font-mono text-xs font-bold">{{ $l->number }}</span>
                        <span class="block text-xs text-muted">{{ NoticeService::LETTER_TYPES[$l->type] ?? $l->type }} · {{ $l->module ? (NoticeService::LABELS[$l->module] ?? $l->module) : 'Toutes taxes' }} · {{ $l->generated_on?->format('d/m/Y') }}@if ($l->agent) · {{ $l->agent->name }}@endif
                            @if ($l->cancellation_reason) · motif : {{ $l->cancellation_reason }}@endif</span>
                    </button>
                    <span class="text-xs text-muted">{{ $l->taxpayer_count }} avis</span>
                    <span class="font-semibold tabular-nums">{{ Format::money($l->total_amount) }}</span>
                    <x-badge :color="$sc">{{ tr($sl) }}@if ($l->sent_on) · {{ $l->sent_on->format('d/m/Y') }}@endif</x-badge>
                    <x-btn variant="ghost" size="sm" icon="printer" :href="route('caisse.lettre-imprimer', $l)" target="_blank" aria-label="Imprimer {{ $l->number }}" />
                    @if ($canWrite && $l->status === 'brouillon')
                        <x-btn variant="secondary" size="sm" icon="check" wire:click="approve({{ $l->id }})" wire:confirm="Marquer la lettre {{ $l->number }} comme envoyée ?">{{ __('Envoyée') }}</x-btn>
                    @endif
                    @if ($canWrite && ! $l->cancelled_at && $l->status !== 'annulee')
                        <x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $l->id }})" aria-label="Annuler {{ $l->number }}" />
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="send" :title="__('Aucune lettre')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $letters->links() }}</div>
    </x-card>

    @if ($cancelId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Annuler la lettre') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('cancelId', null)"></div>
            <form wire:submit="confirmCancel" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Annuler la lettre') }}</h2>
                <p class="text-xs text-muted">{{ __('La lettre reste dans le registre, marquée annulée avec son motif. Ses avis redeviennent disponibles.') }}</p>
                <x-field :label="__('Motif')" for="c-reason" error="cancelReason"><x-textarea id="c-reason" rows="2" wire:model="cancelReason" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('cancelId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="danger" icon="ban">{{ __('Annuler la lettre') }}</x-btn></div>
            </form>
        </div>
    @endif
</div>
