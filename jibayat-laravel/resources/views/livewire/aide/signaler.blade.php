@php use App\Models\FeedbackReport; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <form wire:submit="send" class="max-w-3xl">
        <x-card :title="__('Votre message')">
            <div class="space-y-3">
                <div class="inline-flex rounded-full border border-line bg-white p-0.5 text-sm font-semibold" role="radiogroup" aria-label="{{ __('Type') }}">
                    @foreach (FeedbackReport::TYPES as $k => $l)
                        <label class="cursor-pointer rounded-full px-4 py-1.5 {{ $type === $k ? 'bg-navy text-white' : 'text-muted' }}"><input type="radio" class="sr-only" value="{{ $k }}" wire:model.live="type"> {{ tr($l) }}</label>
                    @endforeach
                </div>
                <x-field :label="__('Espace concerné')" for="f-mod"><x-select id="f-mod" wire:model="module"><option value="">{{ __('Général') }}</option>@foreach ($spaces as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</x-select></x-field>
                <x-field :label="__('Message')" for="f-msg" error="message" :hint="__('Que faisiez-vous, qu’attendiez-vous, que s’est-il passé ?')"><x-textarea id="f-msg" rows="5" wire:model="message" /></x-field>
                @if ($type === 'suggestion')
                    <div class="flex items-center gap-1" role="radiogroup" aria-label="{{ __('Note de l’application') }}">
                        <span class="me-2 text-sm text-muted">{{ __('Votre note :') }}</span>
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button" wire:click="$set('rating', {{ $i }})" aria-label="{{ $i }} sur 5" class="{{ ($rating ?? 0) >= $i ? 'text-gold' : 'text-line' }}"><x-svg-icon name="star" class="h-6 w-6" /></button>
                        @endfor
                    </div>
                @endif
                <div class="flex justify-end"><x-btn type="submit" variant="gold" icon="send" wire:loading.attr="disabled">{{ __('Envoyer') }}</x-btn></div>
            </div>
        </x-card>
    </form>

    <x-card :padding="false">
        <x-slot:title>{{ $admin ? 'Signalements reçus' : 'Mes signalements' }}</x-slot:title>
        <ul class="divide-y divide-line text-sm">
            @forelse ($reports as $r)
                <li class="px-4 py-3" wire:key="fb-{{ $r->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :color="$r->type === 'probleme' ? 'red' : ($r->type === 'suggestion' ? 'gold' : 'blue')">{{ FeedbackReport::TYPES[$r->type] ?? $r->type }}</x-badge>
                        <span class="text-xs text-muted">{{ $r->created_at->format('d/m/Y H:i') }}@if ($admin && $r->user) · {{ $r->user->name }}@endif @if ($r->module) · {{ $r->module }}@endif</span>
                        <x-badge :color="$r->status === 'traite' ? 'green' : null" class="ms-auto">{{ FeedbackReport::STATUSES[$r->status] ?? $r->status }}</x-badge>
                        @if ($admin)<x-btn variant="ghost" size="sm" icon="message-square-reply" wire:click="openAnswer({{ $r->id }})">{{ __('Répondre') }}</x-btn>@endif
                    </div>
                    <p class="mt-1 whitespace-pre-line">{{ $r->message }}</p>
                    @if ($r->answer)<p class="mt-2 rounded-xl bg-bg p-2 text-xs"><strong>Réponse{{ $r->handler ? ' de '.$r->handler->name : '' }} :</strong> {{ $r->answer }}</p>@endif
                    @if ($answerId === $r->id)
                        <form wire:submit="saveAnswer" class="mt-2 space-y-2">
                            <x-textarea rows="2" wire:model="answer" :aria-label="__('Réponse')" />
                            <div class="flex gap-2"><x-select wire:model="answerStatus" class="!w-40" :aria-label="__('Statut')">@foreach (FeedbackReport::STATUSES as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select>
                                <x-btn type="submit" size="sm" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn><x-btn size="sm" variant="ghost" wire:click="$set('answerId', null)">{{ __('Annuler') }}</x-btn></div>
                        </form>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="message-circle" :title="__('Aucun signalement')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
