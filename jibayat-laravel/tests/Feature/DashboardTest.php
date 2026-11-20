<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Dashboard\DashboardStats;
use App\Models\AppModule;
use App\Models\Taxpayer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
| Tableau de bord : mêmes formules que Flask (app.py::index).
*/

beforeEach(function () {
    Cache::flush();
    $this->year = (int) now()->year;
    $ctb = Taxpayer::create(['number' => 'C1', 'last_name' => 'Alaoui', 'first_name' => 'Karim', 'is_active' => true]);
    DB::table('parcels')->insert(['number' => 'T1', 'taxpayer_id' => $ctb->id, 'zone' => 'A', 'is_active' => true, 'is_archived' => false, 'created_at' => now(), 'updated_at' => now()]);

    $decl = fn ($n, $module, $total, $status, $month) => TaxDeclaration::create([
        'number' => $n, 'module' => $module, 'taxpayer_id' => $ctb->id, 'year' => $this->year, 'total' => $total,
        'status' => $status, 'declared_on' => sprintf('%d-%02d-10', $this->year, $month),
    ]);
    $d1 = $decl('D1', 'TNB', 1000, 'paye', 1);
    $d2 = $decl('D2', 'TNB', 500, 'emis', 2);
    $d3 = $decl('D3', 'LOCATION_LOCAUX', 300, 'emis', 2);
    $decl('D4', 'TNB', 9999, 'annule', 3); // ignorée partout

    PaymentSlip::create(['slip_number' => 'BV1', 'tax_declaration_id' => $d1->id, 'taxpayer_id' => $ctb->id, 'amount' => 1000,
        'status' => 'paye', 'paid_on' => sprintf('%d-01-20', $this->year), 'receipt_number' => 'Q-1', 'receipt_date' => sprintf('%d-01-20', $this->year)]);
    PaymentSlip::create(['slip_number' => 'BV2', 'tax_declaration_id' => $d2->id, 'taxpayer_id' => $ctb->id, 'amount' => 500, 'status' => 'en_attente']);
    DB::table('non_payment_notices')->insert(['number' => 'AVS1', 'tax_declaration_id' => $d3->id, 'amount_due' => 300, 'status' => 'emis', 'created_at' => now(), 'updated_at' => now()]);
});

test('global totals follow the Flask formulas and ignore cancelled declarations', function () {
    $t = (new DashboardStats)->totals();

    expect($t)->toMatchArray([
        'emitted' => 1800.0, 'paid' => 1000.0, 'remaining' => 800.0, 'rate' => 55.6,
        'declarations' => 3, 'pending_count' => 1, 'pending_amount' => 500.0, 'notices' => 1, 'taxpayers' => 1, 'files' => 1,
    ]);
});

test('per-module figures and recovery rate', function () {
    $modules = (new DashboardStats)->modules(userWithRole('administrateur'));

    expect($modules['TNB'])->toMatchArray(['emitted' => 1500.0, 'paid' => 1000.0, 'remaining' => 500.0, 'rate' => 66.7, 'count' => 1, 'declarations' => 2])
        ->and($modules['LOCATION_LOCAUX'])->toMatchArray(['emitted' => 300.0, 'paid' => 0.0, 'rate' => 0.0]);
});

test('modules are hidden without permission or when switched off', function () {
    $consult = userWithRole('consultation');
    $consult->roles->first()->revokePermissionTo('locations.voir');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    AppModule::create(['code' => 'FOURRIERE', 'label' => 'Fourrière', 'is_active' => false]);
    Cache::forget('jibayat.inactive_modules');

    $keys = array_keys((new DashboardStats)->modules($consult->fresh()));

    expect($keys)->toContain('TNB')->not->toContain('LOCATION_LOCAUX')->not->toContain('FOURRIERE');
});

test('monthly series: emitted by declaration date, collected by payment date', function () {
    $m = (new DashboardStats)->monthly();

    expect($m['labels'][0])->toBe('Jan')
        ->and($m['emitted'][0])->toBe(1000.0)->and($m['emitted'][1])->toBe(800.0)->and($m['emitted'][2])->toBe(0.0)
        ->and($m['paid'][0])->toBe(1000.0)->and(array_sum($m['paid']))->toBe(1000.0);
});

test('the home page shows the dashboard with figures, charts and recent activity', function () {
    $html = $this->actingAs(userWithRole('administrateur'))->get('/')->assertOk()->getContent();

    foreach (['Plateforme fiscale &amp; recouvrement communal', '1 800,00', '55,6 %', 'data-chart="monthly"', 'data-chart="modules"', 'Q-1', 'BV2', 'D3'] as $needle) {
        expect(str_contains($html, $needle))->toBeTrue("absent : $needle");
    }
    expect($html)->not->toContain('>D4 ');
});

test('users without the dashboard right are sent to their first page', function () {
    $user = userWithRole('consultation');
    $user->roles->first()->revokePermissionTo('dashboard.voir');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())->get('/')->assertRedirect();
});
