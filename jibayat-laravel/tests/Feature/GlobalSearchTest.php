<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Transport\Models\Vehicle;
use App\Livewire\PageSearch;
use App\Models\Taxpayer;
use Livewire\Livewire;

beforeEach(function () {
    $this->t = Taxpayer::create(['number' => 'CTB202600009', 'last_name' => 'Ouali', 'first_name' => 'Said', 'cin' => 'JK7788', 'is_active' => true]);
    Vehicle::create(['number' => '41', 'taxpayer_id' => $this->t->id, 'registration' => '7788-A-26', 'type' => 'grand_taxi', 'is_active' => true]);
    $d = TaxDeclaration::create(['number' => 'DCL-STA2026777', 'module' => 'STATIONNEMENT', 'year' => 2026, 'taxpayer_id' => $this->t->id, 'total' => 400, 'status' => 'paye']);
    PaymentSlip::create(['slip_number' => 'BUL202600555', 'tax_declaration_id' => $d->id, 'taxpayer_id' => $this->t->id, 'amount' => 400, 'status' => 'paye', 'receipt_number' => 'Q-9911']);
});

test('global search finds pages, taxpayers, files and receipts', function () {
    $this->actingAs(userWithRole('administrateur'));
    Livewire::test(PageSearch::class)->set('query', 'JK77')->assertSee('Ouali Said')->assertSee('7788-A-26')
        ->set('query', 'Q-9911')->assertSee('BUL202600555')->assertSee('quittance Q-9911')
        ->set('query', 'sauvegard')->assertSee('Sauvegardes')
        ->set('query', 'zzzqqq')->assertSee('Aucun résultat');
});

test('records of a tax the user cannot see are hidden', function () {
    (new Database\Seeders\RolesAndPermissionsSeeder)->run();
    $user = App\Models\User::factory()->create();
    $user->givePermissionTo('contribuables.voir');
    $this->actingAs($user);
    Livewire::test(PageSearch::class)->set('query', '7788-A')->assertDontSee('Véhicule ·')->set('query', 'Ouali')->assertSee('Contribuable ·');
});
