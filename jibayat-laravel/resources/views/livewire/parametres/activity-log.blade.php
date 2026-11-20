<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <div class="grid gap-2 sm:grid-cols-4">
        <x-select wire:model.live="log" :aria-label="__('Journal')">
            <option value="">{{ __('Tous les journaux') }}</option>
            @foreach ($logs as $key => $label)<option value="{{ $key }}">{{ tr($label) }}</option>@endforeach
        </x-select>
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher…')" :aria-label="__('Rechercher')" />
        <x-input type="date" wire:model.live="from" :aria-label="__('Du')" />
        <x-input type="date" wire:model.live="to" :aria-label="__('Au')" />
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($activities as $a)
                <li class="px-4 py-3" wire:key="a-{{ $a->id }}">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span class="text-sm font-semibold text-ink">{{ $a->description }}</span>
                        @if ($a->subject_type)
                            <x-badge>{{ \App\Livewire\Parametres\ActivityLog::subjectLabel($a->subject_type) }}@if($a->subject_id && $a->log_name !== 'flask') #{{ $a->subject_id }}@endif</x-badge>
                        @endif
                        <x-badge color="blue">{{ $logs[$a->log_name] ?? $a->log_name }}</x-badge>
                        <span class="ms-auto text-xs tabular-nums text-muted">{{ $a->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted">{{ $a->causer?->name ?? 'Système' }}</p>
                    @php($props = ($a->properties?->toArray() ?? []) + ($a->attribute_changes?->toArray() ?? []))
                    @if (! empty($props['attributes']) || ! empty($props['motif']))
                        <details class="mt-1 text-xs">
                            <summary class="cursor-pointer text-accent">{{ __('Détail') }}</summary>
                            <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 rounded-lg bg-bg p-2 font-mono">
                                @if (! empty($props['motif']))<dt class="text-muted">{{ __('motif') }}</dt><dd>{{ $props['motif'] }}</dd>@endif
                                @foreach ($props['attributes'] ?? [] as $k => $v)
                                    <dt class="text-muted">{{ $k }}</dt>
                                    <dd>@if (isset($props['old'][$k]))<span class="text-red-600 line-through">{{ is_scalar($props['old'][$k]) ? $props['old'][$k] : json_encode($props['old'][$k]) }}</span> → @endif{{ is_scalar($v) || $v === null ? ($v ?? '∅') : json_encode($v) }}</dd>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="clipboard-list" :title="__('Aucune activité pour ces critères')" /></li>
            @endforelse
        </ul>
    </x-card>

    {{ $activities->links() }}
</div>
