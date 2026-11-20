<?php

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\Service;
use App\Domain\Documents\Models\StampValue;
use App\Livewire\Parametres;
use App\Models\AppModule;
use App\Models\CalcParameter;
use App\Models\Municipality;
use App\Models\Rubric;
use App\Models\Setting;
use App\Models\TaxOrder;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = userWithRole('administrateur');
    Cache::flush();
});

test('every settings screen opens for the administrator', function () {
    $order = TaxOrder::create(['number' => 'AF-2026-001', 'title' => 'T', 'effective_date' => '2026-01-01', 'status' => 'actif']);
    foreach (['rubriques', 'arretes', 'calcul', 'regie', 'utilisateurs', 'roles', 'journal', 'commune', 'modules', 'sessions', 'etat'] as $page) {
        $this->actingAs($this->admin)->get(route("parametres.$page"))->assertOk();
    }
    $this->actingAs($this->admin)->get(route('parametres.arretes.show', $order))->assertOk()->assertSee('AF-2026-001');
});

test('a régisseur cannot open the settings screens', function () {
    $this->actingAs(userWithRole('regisseur'))->get(route('parametres.rubriques'))->assertForbidden();
});

test('rubrics can be created, edited and switched off, and changes are journaled', function () {
    Livewire::actingAs($this->admin)->test(Parametres\Rubriques::class)
        ->call('create')
        ->set('module', 'TNB')->set('code', '30101014')->set('label', 'Taxe TNB')->set('label_ar', 'الرسم')
        ->call('save')->assertHasNoErrors()
        ->call('create')->set('module', 'TNB')->set('label', 'Doublon')->call('save')->assertHasErrors(['module' => 'unique']);

    $rubric = Rubric::where('module', 'TNB')->first();
    expect($rubric->label_ar)->toBe('الرسم');

    Livewire::actingAs($this->admin)->test(Parametres\Rubriques::class)
        ->call('edit', $rubric->id)->set('label', 'Terrains non bâtis')->call('save')
        ->call('toggle', $rubric->id);

    expect($rubric->fresh())->label->toBe('Terrains non bâtis')->is_active->toBeFalse();
    expect(Activity::where('log_name', 'parametres')->where('subject_id', $rubric->id)->count())->toBeGreaterThanOrEqual(3);
});

test('read-only users cannot write even by calling the component directly', function () {
    $consult = userWithRole('consultation');
    $consult->givePermissionTo('parametres.voir');

    Livewire::actingAs($consult)->test(Parametres\Rubriques::class)->call('create')->assertForbidden();
});

test('creating a tax order from the screen redirects to its detail', function () {
    Rubric::create(['code' => '1', 'label' => 'TNB', 'module' => 'TNB']);

    Livewire::actingAs($this->admin)->test(Parametres\TaxOrders::class)
        ->call('create')->set('effective_date', '2027-01-01')->set('title', 'Arrêté 2027')
        ->call('save')->assertRedirect(route('parametres.arretes.show', TaxOrder::first()));

    expect(TaxOrder::first()->tariffs()->count())->toBe(1);
});

test('tariffs are managed from the order detail screen', function () {
    $rubric = Rubric::create(['code' => '30101014', 'label' => 'TNB', 'module' => 'TNB']);
    $order = TaxOrder::create(['number' => 'AF-2026-001', 'title' => 'T', 'effective_date' => '2026-01-01', 'status' => 'actif']);

    $c = Livewire::actingAs($this->admin)->test(Parametres\TaxOrderShow::class, ['taxOrder' => $order])
        ->call('newTariff', $rubric->id)->set('label', 'Zone A')->set('value', '20')->set('unit', 'DH/m²')
        ->call('saveTariff')->assertHasNoErrors();
    $tariff = $order->tariffs()->first();
    expect($tariff->code)->toBe('30101014-ZONA');

    $c->call('editTariff', $tariff->id)->set('value', '22.5')->call('saveTariff');
    expect($tariff->fresh()->value)->toBe('22.5000');

    $c->set('delete_reason', 'non')->call('deleteOrder')->assertHasErrors('delete_reason');
    $c->set('delete_reason', 'Arrêté de test')->call('deleteOrder')->assertRedirect(route('parametres.arretes'));
    expect(TaxOrder::count())->toBe(0);
});

test('calculation parameters are saved and the legal values restored', function () {
    $p = CalcParameter::create(['module' => 'TNB', 'code' => 'AMENDE_NON_DECLARATION', 'label' => 'Amende', 'value' => '15', 'unit' => '%']);
    $q = CalcParameter::create(['module' => 'TNB', 'code' => 'DATE_LIMITE', 'label' => 'Date limite', 'value' => '31/03']);

    Livewire::actingAs($this->admin)->test(Parametres\CalcParameters::class)
        ->set("values.{$p->id}", '20')->set("values.{$q->id}", '28/02')->call('save');
    expect($p->fresh()->value)->toBe('20')->and($q->fresh()->value)->toBe('28/02');

    Livewire::actingAs($this->admin)->test(Parametres\CalcParameters::class)->call('resetLegal');
    expect($p->fresh()->value)->toBe('15')->and($q->fresh()->value)->toBe('31/03')
        ->and(CalcParameter::count())->toBe(2); // ne crée pas les paramètres absents (comme Flask)
});

test('services, employees and stamp values are managed, and used items cannot be deleted', function () {
    $c = Livewire::actingAs($this->admin)->test(Parametres\Intervenants::class)
        ->call('create')->set('form.name', 'État civil')->call('save');
    $service = Service::first();

    $c->call('setTab', 'employes')->call('create')
        ->set('form.last_name', 'Bennani')->set('form.first_name', 'Sara')->set('form.service_id', (string) $service->id)->call('save');
    expect(Employee::first()->service_id)->toBe($service->id);

    $c->call('setTab', 'valeurs')->call('create')
        ->set('form.label', 'Timbre 2 DH')->set('form.type', 'timbre')->set('form.unit_value', '2')->set('form.units_per_book', '500')->call('save');
    expect(StampValue::first())->unit_value->toBe('2.00')->units_per_book->toBe(500);

    $c->call('setTab', 'services')->call('delete', $service->id);
    expect(Service::count())->toBe(1); // utilisé par un employé : désactiver plutôt
});

test('users are created with a provisional password that must be changed, and are deactivated rather than deleted', function () {
    Livewire::actingAs($this->admin)->test(Parametres\Users::class)
        ->call('create')
        ->set('username', 'Karim.B')->set('name', 'Karim B')->set('role', 'collecteur')->set('password', 'faible')
        ->call('save')->assertHasErrors('password')
        ->set('password', 'Provisoire-2026')->call('save')->assertHasNoErrors();

    $karim = User::where('username', 'karim.b')->first();
    expect($karim->hasRole('collecteur'))->toBeTrue()->and($karim->must_change_password)->toBeTrue();

    Livewire::actingAs($this->admin)->test(Parametres\Users::class)->call('toggleActive', $karim->id);
    expect($karim->fresh()->is_active)->toBeFalse()->and(User::count())->toBe(2);

    Livewire::actingAs($this->admin)->test(Parametres\Users::class)->call('toggleActive', $this->admin->id);
    expect($this->admin->fresh()->is_active)->toBeTrue();

    expect(Activity::where('subject_type', (new User)->getMorphClass())->where('subject_id', $karim->id)->get()
        ->pluck('attribute_changes')->flatten()->implode(' '))->not->toContain('$2y$'); // jamais de hash dans le journal
});

test('a user flagged must_change_password is sent to the profile until the password is changed', function () {
    $user = userWithRole('regisseur');
    $user->forceFill(['must_change_password' => true, 'password' => 'Ancien-Mdp-2026'])->save();

    $this->actingAs($user)->get('/')->assertRedirect(route('profile.edit'));
    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Choisissez un nouveau mot de passe');

    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'Ancien-Mdp-2026', 'password' => 'Nouveau-Mdp-2026', 'password_confirmation' => 'Nouveau-Mdp-2026',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeFalse();
    $this->actingAs($user->fresh())->get('/')->assertOk();
});

test('role permissions can be edited from the matrix but the administrator role is locked', function () {
    Livewire::actingAs($this->admin)->test(Parametres\Roles::class)
        ->set('role', 'consultation')
        ->set('granted.tnb__exporter', true)
        ->set('granted.locations__voir', false)
        ->call('save');

    $role = Role::findByName('consultation');
    expect($role->hasPermissionTo('tnb.exporter'))->toBeTrue()->and($role->hasPermissionTo('locations.voir'))->toBeFalse();

    Livewire::actingAs($this->admin)->test(Parametres\Roles::class)->set('role', 'administrateur')->call('save')->assertForbidden();

    (new RolesAndPermissionsSeeder)->run(); // relancer le seeder ne réécrase pas la personnalisation
    expect(Role::findByName('consultation')->hasPermissionTo('tnb.exporter'))->toBeTrue();
});

test('the municipality identity is saved', function () {
    Livewire::actingAs($this->admin)->test(Parametres\Commune::class)
        ->set('form.name', 'Aït Amira')->set('form.name_ar', 'أيت عميرة')->set('form.province', 'Chtouka Aït Baha')->call('save');

    expect(Municipality::first())->name->toBe('Aït Amira')->name_ar->toBe('أيت عميرة');
});

test('a disabled module disappears from the navigation and its pages are blocked', function () {
    $tnb = AppModule::create(['code' => 'TNB', 'label' => 'TNB', 'is_active' => true]);

    Livewire::actingAs($this->admin)->test(Parametres\Modules::class)->call('toggle', $tnb->id);

    expect($tnb->fresh()->is_active)->toBeFalse()
        ->and(app(Navigation::class)->forUser($this->admin)->keys())->not->toContain('tnb');
    $this->actingAs($this->admin)->get(route('tnb.terrains'))->assertForbidden();

    Livewire::actingAs($this->admin)->test(Parametres\Modules::class)->call('toggle', $tnb->id);
    $this->actingAs($this->admin)->get(route('tnb.terrains'))->assertOk();
});

test('the auto-logout delay is saved and applied', function () {
    Livewire::actingAs($this->admin)->test(Parametres\Sessions::class)
        ->set('minutes', 0)->call('save')->assertHasErrors('minutes')
        ->set('minutes', 15)->call('save')->assertHasNoErrors();

    expect(Setting::get('auto_logout_minutes'))->toBe('15');
    $this->actingAs($this->admin)->get('/')->assertSee('Fermeture après 15 min sans activité');
});

test('the activity log can be filtered', function () {
    activity('auth')->causedBy($this->admin)->log('Connexion');
    activity('parametres')->causedBy($this->admin)->log('Modification du tarif test');

    Livewire::actingAs($this->admin)->test(Parametres\ActivityLog::class)
        ->set('log', 'auth')->assertSee('Connexion')->assertDontSee('Modification du tarif test')
        ->set('log', '')->set('search', 'tarif test')->assertSee('Modification du tarif test');
});

test('the system status shows versions and volumes', function () {
    $this->actingAs($this->admin)->get(route('parametres.etat'))
        ->assertSee('Laravel')->assertSee(PHP_VERSION)->assertSee('Contribuables');
    expect(DB::connection()->getDriverName())->toBe('mysql');
});
