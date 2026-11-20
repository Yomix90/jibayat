@php
    use App\Domain\Declarations\Services\DeclarationService;
    $ar = app()->isLocale('ar');
    $label = fn ($x) => $ar ? $x['ar'] : $x['fr'];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="files" :href="route('declarations.registre')">{{ __('Registre des déclarations d’assiette') }}</x-btn></x-slot:actions>
    </x-page-header>

    {{-- 1. Taxe --}}
    <section aria-labelledby="step-1">
        <x-section-title id="step-1">{{ __('1. Taxe concernée') }}</x-section-title>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            @foreach (DeclarationService::MODULES as $code => $m)
                <button type="button" wire:click="chooseModule('{{ $code }}')" wire:key="mod-{{ $code }}"
                        class="pressable surface p-4 text-start {{ $module === $code ? 'ring-2 ring-gold' : '' }}" aria-pressed="{{ $module === $code ? 'true' : 'false' }}">
                    <span class="block font-semibold">{{ $label($m) }}</span>
                    <span class="mt-0.5 block text-xs text-muted" @if (! $ar) dir="rtl" @endif>{{ $ar ? $m['fr'] : $m['ar'] }}</span>
                </button>
            @endforeach
        </div>
    </section>

    {{-- 2. Type --}}
    <section aria-labelledby="step-2">
        <x-section-title id="step-2">{{ __('2. Type de déclaration') }}</x-section-title>
        <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($types as $k => $ty)
                <button type="button" wire:click="chooseType('{{ $k }}')" wire:key="type-{{ $module }}-{{ $k }}"
                        class="pressable surface flex flex-col gap-1 p-4 text-start {{ $type === $k ? 'ring-2 ring-gold' : '' }}" aria-pressed="{{ $type === $k ? 'true' : 'false' }}">
                    <span class="flex items-start justify-between gap-2"><span class="font-semibold">{{ $label($ty) }}</span>
                        <x-badge>{{ $ty['art'] === '—' ? __('Interne') : __('Art.').' '.$ty['art'] }}</x-badge></span>
                    <span class="text-xs text-muted" @if (! $ar) dir="rtl" @endif>{{ $ar ? $ty['fr'] : $ty['ar'] }}</span>
                    <span class="text-xs text-ink/70">{{ __($ty['hint']) }}</span>
                </button>
            @endforeach
        </div>
    </section>

    @if ($t)
        {{-- 3. Objet --}}
        <section aria-labelledby="step-3">
            <x-section-title id="step-3">{{ __('3. Objet de la déclaration') }}</x-section-title>
            <div class="mt-3 max-w-2xl space-y-2">
                @if ($object)
                    <div class="surface flex flex-wrap items-center gap-3 p-4">
                        <x-svg-icon :name="['TNB' => 'map-pin', 'DEBITS_BOISSONS' => 'coffee', 'STATIONNEMENT' => 'bus'][$module]" class="h-5 w-5 text-accent" />
                        <div class="min-w-0 flex-1"><p class="font-semibold">{{ DeclarationService::objectLabel($object) }}</p>
                            <p class="text-xs text-muted">{{ $object->taxpayer?->displayName() }} @if ($object->taxpayer?->cin)· {{ $object->taxpayer->cin }}@endif · {{ __('Statut') }} : {{ __($object->status ?? '—') }}</p></div>
                        <x-btn variant="ghost" size="sm" wire:click="$set('objectId', null)">{{ __('Changer') }}</x-btn>
                    </div>
                    @if ($open)
                        <p class="rounded-xl border border-gold/40 bg-gold/10 p-3 text-sm">{{ __('Arrêt d’activité en cours depuis le') }} {{ $open->event_on?->format('d/m/Y') }} · {{ $open->reason }}
                            @if ($open->resume_on) · {{ __('reprise prévue le') }} {{ $open->resume_on->format('d/m/Y') }}@endif</p>
                    @endif
                @else
                    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher : n°, nom, CIN, immatriculation, titre foncier…')" :aria-label="__('Rechercher')" />
                    <ul class="surface divide-y divide-line text-sm">
                        @forelse ($results as $o)
                            <li wire:key="o-{{ $o->id }}"><button type="button" wire:click="chooseObject({{ $o->id }})" class="w-full px-4 py-2.5 text-start hover:bg-bg">
                                <span class="font-semibold">{{ DeclarationService::objectLabel($o) }}</span>
                                <span class="block text-xs text-muted">{{ $o->taxpayer?->displayName() }} @if ($o->taxpayer?->cin)· {{ $o->taxpayer->cin }}@endif</span></button></li>
                        @empty
                            <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun résultat.') }}</li>
                        @endforelse
                    </ul>
                    @error('objectId')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                @endif
            </div>
        </section>

        {{-- 4. Contenu --}}
        <form wire:submit="save" class="max-w-3xl">
            <x-card :title="__('4. Contenu de la déclaration')">
                <div class="grid gap-3 sm:grid-cols-2">
                    @if (in_array('fiscal_year', $t['fields'], true))
                        <x-field :label="__('Année concernée')" for="f-year" error="form.fiscal_year"><x-input id="f-year" type="number" wire:model.live.debounce.400ms="form.fiscal_year" /></x-field>
                    @endif
                    @if (in_array('event_on', $t['fields'], true))
                        <x-field :label="__(['cessation' => 'Date de l’arrêt d’activité', 'reprise' => 'Date de reprise', 'creation' => 'Date de début d’activité'][$type] ?? 'Date du fait (changement, cession, transfert…)')" for="f-event" error="form.event_on">
                            <x-input id="f-event" type="date" wire:model.live="form.event_on" /></x-field>
                    @endif
                    @if (in_array('resume_on', $t['fields'], true))
                        <x-field :label="__('Date de reprise prévue')" for="f-resume" error="form.resume_on" :hint="__('Indiquée et signée par le redevable.')"><x-input id="f-resume" type="date" wire:model="form.resume_on" /></x-field>
                    @endif
                    @if (in_array('amount', $t['fields'], true))
                        <x-field :label="__($t['amount_label'])" for="f-amount" error="form.amount"><x-input id="f-amount" type="number" step="0.01" min="0" wire:model="form.amount" /></x-field>
                    @endif
                    @if (in_array('inactivity', $t['fields'], true))
                        <x-field :label="__('Inactivité')" for="f-inact" error="form.inactivity"><x-select id="f-inact" wire:model.live="form.inactivity"><option value="totale">{{ __('Totale') }}</option><option value="partielle">{{ __('Partielle') }}</option></x-select></x-field>
                        @if (($form['inactivity'] ?? '') === 'partielle')
                            <x-field :label="__('Partie inactive (description)')" for="f-part" error="form.inactive_part"><x-input id="f-part" wire:model="form.inactive_part" /></x-field>
                        @endif
                    @endif
                    @if (in_array('new_holder', $t['fields'], true))
                        <x-field :label="__('Nouveau titulaire / acquéreur')" for="f-holder" error="form.new_holder"><x-input id="f-holder" wire:model="form.new_holder" /></x-field>
                    @endif
                    @if (in_array('new_address', $t['fields'], true))
                        <x-field :label="__('Nouvelle adresse')" for="f-addr" error="form.new_address"><x-input id="f-addr" wire:model="form.new_address" /></x-field>
                    @endif
                    @if (in_array('reason', $t['fields'], true))
                        <x-field :label="__($type === 'cessation' ? 'Motif de l’arrêt' : 'Motif / précisions')" for="f-reason" error="form.reason" class="sm:col-span-2">
                            @if ($type === 'cessation')
                                <div class="mb-2 flex flex-wrap gap-1.5">
                                    @foreach (DeclarationService::CESSATION_REASONS as $r)
                                        <button type="button" wire:click="$set('form.reason', @js(__($r)))" class="rounded-full border px-3 py-1 text-xs {{ ($form['reason'] ?? '') === __($r) ? 'border-gold bg-gold/15 font-semibold' : 'border-line bg-white hover:border-gold' }}">{{ __($r) }}</button>
                                    @endforeach
                                </div>
                            @endif
                            <x-input id="f-reason" wire:model="form.reason" />
                        </x-field>
                    @endif
                    <x-field :label="__('Date de dépôt')" for="f-filed" error="form.filed_on"><x-input id="f-filed" type="date" wire:model.live="form.filed_on" /></x-field>
                    <x-field :label="__('Observation')" for="f-notes" error="form.notes"><x-input id="f-notes" wire:model="form.notes" /></x-field>
                </div>

                @if ($deadline)
                    <div class="mt-4 rounded-xl p-3 text-sm {{ $late ? 'border border-red-200 bg-red-50 text-red-800' : 'bg-bg' }}">
                        {{ __('Délai légal') }} : <strong>{{ $deadline->format('d/m/Y') }}</strong> ({{ __('Art.') }} {{ $t['art'] }}).
                        @if ($late)
                            <strong>{{ __('Déclaration hors délai') }}</strong> — {{ __('majoration de 15 % du droit dû prévue par la loi (art. 134 : minimum 500 DH ; art. 135 pour les déclarations de cession, d’arrêt ou de transfert).') }}
                        @endif
                    </div>
                @endif

                <label class="mt-4 flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="form.signed" class="mt-0.5 rounded border-line text-accent focus:ring-gold">
                    <span>{{ __('Le redevable a signé la déclaration (le formulaire imprimé est conservé au dossier).') }}</span>
                </label>
                @if ($canWrite)
                    <div class="mt-4 flex justify-end"><x-btn type="submit" variant="gold" icon="file-check" wire:loading.attr="disabled">{{ __('Enregistrer la déclaration') }}</x-btn></div>
                @else
                    <p class="mt-4 text-xs text-muted">{{ __('Vous n’avez pas le droit d’enregistrer des déclarations pour cette taxe.') }}</p>
                @endif
            </x-card>
        </form>
    @endif
</div>
