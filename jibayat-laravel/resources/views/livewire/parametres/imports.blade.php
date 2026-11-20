<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="file-spreadsheet" :href="route('parametres.importation')">{{ __('Nouvel import') }}</x-btn></x-slot:actions>
    </x-page-header>
    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($logs as $l)
                <li class="px-4 py-2.5" wire:key="log-{{ $l->id }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="min-w-0 flex-1"><span class="font-semibold">{{ $labels[$l->module] ?? $l->module }}</span>
                            <span class="block text-xs text-muted">{{ $l->file_name }} · {{ $l->created_at?->format('d/m/Y H:i') }}@if ($l->agent) · {{ $l->agent->name }}@endif</span></span>
                        <span class="text-xs text-muted">{{ $l->total_rows }} ligne(s)</span>
                        <x-badge color="green">{{ $l->success_count }} importée(s)</x-badge>
                        @if ($l->error_count)<x-badge color="red">{{ $l->error_count }} erreur(s)</x-badge>
                            <x-btn variant="ghost" size="sm" icon="chevron-down" wire:click="$set('openId', {{ $openId === $l->id ? 'null' : $l->id }})" :aria-label="__('Voir les erreurs')" />@endif
                    </div>
                    @if ($openId === $l->id)
                        <div class="mt-2 rounded-xl bg-bg p-3 text-xs text-red-700">
                            @foreach ((array) ($l->details ?? []) as $d)<p>{{ is_string($d) ? $d : json_encode($d, JSON_UNESCAPED_UNICODE) }}</p>@endforeach
                        </div>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="history" :title="__('Aucun import')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $logs->links() }}</div>
    </x-card>
</div>
