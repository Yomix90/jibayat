<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Serveur & base')">
            <dl class="divide-y divide-line text-sm">
                @foreach ($info as $label => $value)
                    <div class="flex justify-between gap-4 py-2"><dt class="text-muted">{{ tr($label) }}</dt><dd class="text-end font-semibold text-ink">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-card>

        <x-card :title="__('Volumes')">
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($counts as $c)
                    <div class="rounded-xl bg-bg p-3">
                        <dd class="text-xl font-bold tabular-nums text-ink">{{ number_format($c['count'], 0, ',', ' ') }}</dd>
                        <dt class="text-xs text-muted">{{ tr($c['label']) }}</dt>
                    </div>
                @endforeach
            </dl>
        </x-card>
    </div>

    <x-card :title="__('Dernier import de la base Flask')">
        @if ($lastImport)
            <p class="text-sm">
                @if ($lastImport['ok'])<x-badge color="green">{{ __('Sans écart') }}</x-badge>@else<x-badge color="red">{{ __('Avec écarts') }}</x-badge>@endif
                le {{ \Carbon\Carbon::parse($lastImport['finished_at'])->format('d/m/Y à H:i') }} —
                {{ collect($lastImport['tables'])->sum('source_count') }} ligne(s) source,
                {{ collect($lastImport['tables'])->sum('inserted') }} insérée(s),
                {{ collect($lastImport['tables'])->sum('updated') }} mise(s) à jour,
                {{ collect($lastImport['tables'])->sum('rejected') }} rejetée(s).
            </p>
        @else
            <p class="text-sm text-muted">{{ __('Aucun import réalisé.') }}</p>
        @endif
    </x-card>
</div>
