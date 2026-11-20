<x-app-layout :title="__('Mon profil')">
    <div class="space-y-6">
        @if (auth()->user()->must_change_password)
            <div class="flex items-start gap-3 rounded-2xl border border-accent/30 bg-accent/10 p-4 text-sm text-ink" role="alert">
                <x-svg-icon name="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-accent" />
                <p><strong>{{ __('Choisissez un nouveau mot de passe pour continuer.') }}</strong>
                    {{ __('Votre mot de passe a été défini par l\'administrateur (ou importé de l\'ancienne application).') }}</p>
            </div>
        @endif

        <x-section-title>{{ __('Mon profil') }}</x-section-title>
        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="max-w-xl">@include('profile.partials.update-password-form')</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="max-w-xl">@include('profile.partials.update-profile-information-form')</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-5">
            <livewire:passkeys />
        </div>
    </div>
</x-app-layout>
