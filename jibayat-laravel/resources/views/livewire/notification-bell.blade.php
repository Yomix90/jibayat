<div class="relative shrink-0" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.120s>
    <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
            class="round-btn relative text-ink hover:bg-black/5" aria-label="Notifications{{ $unread ? ' : '.$unread.' non lue(s)' : '' }}">
        <x-svg-icon name="bell" class="h-5 w-5" />
        @if ($unread)<span class="absolute -end-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </button>
    <div x-show="open" x-cloak x-transition:enter="enter" x-transition:enter-start="enter-from" x-transition:enter-end="enter-to"
         class="absolute end-0 top-12 w-80 overflow-hidden rounded-2xl border border-line bg-white" style="box-shadow: var(--shadow-lg)">
        <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
            <span class="text-sm font-semibold">{{ __('Notifications') }}</span>
            @if ($unread)<button type="button" wire:click="markAll" class="text-xs font-semibold text-accent hover:underline">{{ __('Tout marquer comme lu') }}</button>@endif
        </div>
        <ul class="max-h-96 divide-y divide-line overflow-y-auto">
            @forelse ($items as $n)
                @php($color = ['error' => 'bg-red-600', 'warning' => 'bg-gold', 'info' => 'bg-[#5B7590]'][$n->data['level'] ?? 'info'] ?? 'bg-[#5B7590]')
                <li><button type="button" wire:click="open('{{ $n->id }}')" class="flex w-full gap-3 px-4 py-3 text-start hover:bg-bg {{ $n->read_at ? 'opacity-60' : '' }}">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $color }}" aria-hidden="true"></span>
                    <span class="min-w-0"><span class="block text-sm font-semibold">{{ $n->data['title'] ?? '' }}</span>
                        <span class="block text-xs text-muted">{{ $n->data['message'] ?? '' }}</span>
                        <span class="block text-[11px] text-muted">{{ $n->created_at->diffForHumans() }}</span></span>
                </button></li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-muted">{{ __('Aucune notification.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
