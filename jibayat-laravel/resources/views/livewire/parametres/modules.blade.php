<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <p class="text-sm text-muted">{{ __('Désactiver un module le retire de l\'accueil, de la recherche et du menu, et bloque ses pages. Ses données restent en base et réapparaissent dès qu\'il est réactivé.') }}</p>

    <div class="grid gap-3 sm:grid-cols-2">
        @forelse ($modules as $m)
            <div wire:key="mod-{{ $m->id }}" @class(['flex items-center gap-3 rounded-2xl border bg-white p-4', 'border-line' => $m->is_active, 'border-dashed border-line opacity-70' => ! $m->is_active])>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-bg text-xl" aria-hidden="true">{{ $m->icon }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-ink">{{ $m->label ?: $m->code }}</p>
                    <p class="truncate font-mono text-[11px] text-muted">
                        {{ $m->code }}
                        @if (isset($spaces[$m->code])) · espace « {{ config('navigation.'.$spaces[$m->code].'.title') }} »
                        @elseif (isset($pages[$m->code])) · {{ $pages[$m->code] }} page(s)
                        @endif
                    </p>
                </div>
                <button type="button" role="switch" aria-checked="{{ $m->is_active ? 'true' : 'false' }}"
                        aria-label="{{ $m->is_active ? 'Désactiver' : 'Activer' }} {{ $m->label ?: $m->code }}"
                        @if ($this->canWrite()) wire:click="toggle({{ $m->id }})" @else disabled @endif
                        @class(['relative h-7 w-12 shrink-0 rounded-full transition', 'bg-online' => $m->is_active, 'bg-line' => ! $m->is_active])>
                    <span @class(['absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all', 'start-6' => $m->is_active, 'start-1' => ! $m->is_active])></span>
                </button>
            </div>
        @empty
            <x-empty-state icon="grid-2x2" :title="__('Aucun module')" class="sm:col-span-2">{{ __('Importez la base Flask pour retrouver la liste des modules.') }}</x-empty-state>
        @endforelse
    </div>
</div>
