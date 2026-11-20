<?php

namespace App\Providers;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Locations\Models\Lease;
use App\Domain\Souk\Models\SoukContract;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Transport\Models\Vehicle;
use App\Models\Setting;
use App\Models\User;
use App\Rules\NotCommonPassword;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Navigation::class, fn () => new Navigation);
    }

    public function boot(): void
    {
        // Objets taxables des déclarations (colonne taxable_type) : alias stables, indépendants des classes.
        Relation::morphMap([
            'parcel' => Parcel::class,
            'beverage_outlet' => BeverageOutlet::class,
            'vehicle' => Vehicle::class,
            'public_occupation' => PublicOccupation::class,
            'impound_case' => ImpoundCase::class,
            'lease' => Lease::class,
            'souk_contract' => SoukContract::class,
        ]);

        // Traductions de l'interface (lang/app/ar.json), en plus de celles de Laravel (lang/ar.json).
        $this->app['translator']->addJsonPath(lang_path('app'));

        // Performances : signale les requêtes N+1 (chargement paresseux) en développement et dans les tests.
        // Hors tests, une violation est seulement écrite dans le journal (la page continue de fonctionner).
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! $this->app->isProduction());
        if (! $this->app->runningUnitTests()) {
            \Illuminate\Database\Eloquent\Model::handleLazyLoadingViolationUsing(fn ($model, $relation) => logger()->warning('Chargement N+1 : '.$model::class.'::'.$relation));
        }

        // Réglages modifiables depuis Paramètres (remplacent les valeurs de .env).
        $minutes = Setting::get('auto_logout_minutes');
        if (is_numeric($minutes)) {
            config(['jibayat.auto_logout_minutes' => max(1, (int) $minutes)]);
        }

        // Politique de mot de passe reprise de Flask (helpers.password_policy_error) :
        // 10 caractères minimum, majuscule, minuscule, chiffre, pas un mot de passe connu.
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers()
            ->rules([new NotCommonPassword]));

        // L'administrateur a tous les droits (Flask : super_admin / admin).
        Gate::before(fn (User $user) => $user->hasRole('administrateur') ? true : null);

        // Données communes aux vues de l'application (en-tête, menu bureau).
        View::composer(['layouts.app', 'components.desktop-nav'], function ($view) {
            $view->with('navGroups', app(Navigation::class)->groupedForUser(auth()->user()));
        });
    }
}
