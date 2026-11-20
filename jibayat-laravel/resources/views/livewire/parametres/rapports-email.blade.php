@php use App\Domain\Systeme\ReportService; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())
            <x-slot:actions>
                <x-btn variant="secondary" icon="bell-ring" wire:click="runAlerts">{{ __('Envoyer les alertes maintenant') }}</x-btn>
                <x-btn variant="secondary" icon="send" wire:click="sendTest">{{ __('M’envoyer un rapport d’essai') }}</x-btn>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (in_array($mailer, ['log', 'array'], true))
        <div class="rounded-2xl border border-gold/40 bg-gold/10 p-4 text-sm">
            L’envoi d’e-mails n’est pas encore configuré (mode « {{ $mailer }} » : les messages sont écrits dans le journal du serveur).
            Renseignez <span class="font-mono">{{ __('MAIL_MAILER') }}</span>, <span class="font-mono">{{ __('MAIL_HOST') }}</span>, <span class="font-mono">{{ __('MAIL_USERNAME') }}</span>{{ __('… dans le fichier') }} <span class="font-mono">{{ __('.env') }}</span> {{ __('du serveur.') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="lg:col-span-2">
            <x-card :padding="false">
                <x-slot:title>{{ __('Destinataires') }}</x-slot:title>
                <x-slot:actions>@if ($this->canWrite())<x-btn type="submit" variant="gold" size="sm" icon="check">{{ __('Enregistrer') }}</x-btn>@endif</x-slot:actions>
                <ul class="divide-y divide-line text-sm">
                    @forelse ($users as $u)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="u-{{ $u->id }}">
                            <span class="min-w-0 flex-1"><span class="font-semibold">{{ $u->name }}</span><span class="block text-xs text-muted">{{ $u->email }} · {{ $u->roleLabel() }}</span></span>
                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="prefs.{{ $u->id }}.alerts" @disabled(! $this->canWrite()) class="rounded border-line text-accent focus:ring-gold"> Alertes</label>
                            <x-select wire:model="prefs.{{ $u->id }}.report" class="!w-60" :disabled="! $this->canWrite()" aria-label="Rapport de recettes pour {{ $u->name }}">
                                @foreach (ReportService::FREQUENCIES as $k => $l)<option value="{{ $k }}">Rapport : {{ tr($l) }}</option>@endforeach
                            </x-select>
                        </li>
                    @empty
                        <li class="p-4"><x-empty-state icon="mail" :title="__('Aucun compte n’a d’adresse e-mail')" /></li>
                    @endforelse
                </ul>
                @if ($withoutEmail)<p class="border-t border-line px-4 py-2 text-xs text-muted">{{ $withoutEmail }} compte(s) sans adresse e-mail (Paramètres ➜ Utilisateurs).</p>@endif
            </x-card>
        </form>

        <x-card :title="__('Alertes du jour')">
            <ul class="space-y-3 text-sm">
                @forelse ($alerts as $a)
                    <li><a href="{{ $a['url'] }}" class="block hover:underline"><span class="font-semibold {{ $a['level'] === 'error' ? 'text-red-600' : '' }}">{{ tr($a['title']) }}</span>
                        <span class="block text-xs text-muted">{{ $a['message'] }}</span></a></li>
                @empty
                    <li class="text-xs text-muted">{{ __('Rien à signaler.') }}</li>
                @endforelse
            </ul>
            <p class="mt-3 text-xs text-muted">{{ __('Envoyées chaque jour à 7 h aux comptes concernés (cloche, et e-mail si « Alertes » est coché). Rapports : 7 h 15 (quotidien), lundi (hebdomadaire), le 1er du mois (mensuel).') }}</p>
        </x-card>
    </div>
</div>
