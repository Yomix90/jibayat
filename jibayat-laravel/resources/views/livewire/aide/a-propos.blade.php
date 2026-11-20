<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <section class="zellige rounded-3xl bg-navy p-6 text-white">
        <div class="flex flex-wrap items-center gap-4">
            <x-emblem icon="landmark" :size="64" fill="#C9A13B" stroke="rgba(255,255,255,.2)" icon-class="relative h-[42%] w-[42%] text-navy" />
            <div>
                <h2 class="text-2xl font-bold">{{ __('Jibayat') }} <span class="font-normal text-white/70" dir="rtl">جبايات</span></h2>
                <p class="text-sm text-white/80">Gestion de la régie des recettes · {{ $commune?->name ?? 'Commune' }}</p>
                <p class="mt-1 inline-flex rounded-full bg-gold px-3 py-0.5 text-xs font-bold text-navy">Version {{ $version }}</p>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="__('Quoi de neuf ?')">
            <div class="prose prose-sm max-w-none text-ink [&_h1]:hidden [&_h2]:text-base [&_h2]:font-bold [&_li]:my-0.5 [&_ul]:list-disc [&_ul]:ps-5">{!! $changelog !!}</div>
        </x-card>
        <x-card :title="__('Mises à jour')">
            <p class="text-sm text-muted">{{ __('Version installée :') }} <strong class="text-ink">{{ $version }}</strong></p>
            <x-btn variant="secondary" icon="refresh-cw" class="mt-3 w-full" wire:click="check" wire:loading.attr="disabled">{{ __('Vérifier les mises à jour') }}</x-btn>
            @if ($update)
                <div class="mt-3 rounded-xl bg-bg p-3 text-sm">
                    @if (! $update['ok'])
                        <p class="text-muted">{{ $update['message'] }}</p>
                    @elseif ($update['has_update'])
                        <p class="font-semibold text-accent">Nouvelle version disponible : {{ $update['name'] }}</p>
                        @if ($update['notes'])<p class="mt-1 whitespace-pre-line text-xs text-muted">{{ $update['notes'] }}</p>@endif
                        <p class="mt-2 text-xs text-muted">{{ __('La mise à jour s’installe sur le serveur (voir le guide de déploiement).') }}</p>
                    @else
                        <p class="font-semibold text-online">{{ __('Vous utilisez la dernière version.') }}</p>
                    @endif
                </div>
            @endif
            <x-btn variant="ghost" icon="triangle-alert" class="mt-3 w-full" :href="route('parametres.signaler')">{{ __('Signaler un problème') }}</x-btn>
        </x-card>
    </div>
</div>
