<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Models\LeaseSector;
use App\Domain\Locations\Models\LeaseTariff;
use App\Domain\Locations\Models\Shop;
use App\Livewire\Caisse;
use App\Livewire\Locations;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\TaxOrder;
use App\Models\Taxpayer;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole('administrateur');
    Rubric::create(['code' => '40102026', 'label' => 'Location', 'module' => 'LOCATION_LOCAUX']);
    TaxOrder::create(['number' => 'AF-2026-001', 'title' => 'T', 'effective_date' => '2026-01-01', 'status' => 'actif']);
});

function sectorWithShop(): array
{
    Livewire::test(Locations\Secteurs::class)
        ->call('open', 'sector')->set('form.code', 'mar')->set('form.label', 'Marché central')->call('save')->assertHasNoErrors();
    $sector = LeaseSector::first();
    Livewire::test(Locations\Secteurs::class)
        ->call('open', 'tariff', null, $sector->id)->set('form.label', 'Boutique standard')->set('form.value', '300')->call('save');
    $tariff = LeaseTariff::first();
    Livewire::test(Locations\Secteurs::class)
        ->call('open', 'shop', null, $tariff->id)->set('form.numbers', 'B-01, B-02')->set('form.area', '12')->call('save');

    return [$sector, $tariff];
}

test('sectors, tariff groups and shops are created and the tariff is synchronised with the active order', function () {
    $this->actingAs($this->admin);
    [$sector, $tariff] = sectorWithShop();

    expect($sector->code)->toBe('MAR')
        ->and(Shop::pluck('number')->all())->toBe(['B-01', 'B-02'])
        ->and($tariff->tariff_id)->not->toBeNull();
    $fiscal = Tariff::find($tariff->tariff_id);
    expect($fiscal)->code->toBe("MAR-T{$tariff->id}")->label->toBe('Marché central — Boutique standard')->value->toBe('300.0000');

    Livewire::test(Locations\Secteurs::class)->call('open', 'tariff', $tariff->id)->set('form.value', '350')->call('save');
    expect(Tariff::find($tariff->tariff_id)->value)->toBe('350.0000');

    $this->get(route('locations.synchronisation'))->assertOk()->assertSee('Synchronisé');
});

test('a lease is created from a free shop (with a new taxpayer), and the shop becomes occupied', function () {
    $this->actingAs($this->admin);
    sectorWithShop();
    $shop = Shop::where('number', 'B-01')->first();

    Livewire::test(Locations\Locataires::class)
        ->call('create')
        ->set('newTaxpayer', true)->set('taxpayerForm.last_name', 'Alaoui')->set('taxpayerForm.cin', 'J1')
        ->set('form.shop_id', (string) $shop->id)
        ->set('form.starts_on', '2026-01-01')
        ->call('save')->assertHasNoErrors();

    $lease = Lease::first();
    expect($lease)->number->toBe('1')->area->toBe('12.00')->premises_ref->toBe('B-01')
        ->and($lease->taxpayer->last_name)->toBe('Alaoui')
        ->and($shop->fresh()->status)->toBe('exploitee');

    $this->get(route('locations.locataires'))->assertOk()->assertSee('Alaoui')->assertSee('300,00');
});

test('the full flow: collect rent, the régisseur issues the receipt, the lease is up to date', function () {
    $this->actingAs($this->admin);
    $ctb = Taxpayer::create(['number' => 'C1', 'last_name' => 'Bennani']);
    $lease = Lease::create(['number' => '9', 'taxpayer_id' => $ctb->id, 'monthly_rent' => 400,
        'starts_on' => now()->subMonths(2)->startOfMonth(), 'status' => 'actif', 'is_active' => true]);

    Livewire::test(Locations\Encaisser::class, ['leaseId' => $lease->id])
        ->assertSet('count', 3)
        ->set('count', 2)->assertCount('selected', 2)
        ->set('slip_number', 'BV-100')
        ->call('collect')->assertHasNoErrors();

    $slip = PaymentSlip::first();
    expect($slip)->amount->toBe('800.00')->status->toBe('en_attente');

    Livewire::test(Caisse\Bulletins::class)
        ->call('openValidate', $slip->id)->set('receipt_number', 'Q-900')->call('validateSlips')->assertHasNoErrors();
    expect($slip->fresh()->status)->toBe('paye')->and(TaxDeclaration::first()->status)->toBe('paye');

    $this->get(route('locations.fiche', ['bail' => $lease->id]))->assertOk()->assertSee('Q-900')->assertSee('1 mois');
    $this->get(route('locations.registre'))->assertOk()->assertSee('BV-100');
    $this->get(route('caisse.encaissements'))->assertOk()->assertSee('Q-900');
    $this->get(route('locations.tableau-de-bord'))->assertOk()->assertSee('800,00');
});

test('a collector cannot validate payments, and a consultation account cannot collect', function () {
    $ctb = Taxpayer::create(['number' => 'C1', 'last_name' => 'X']);
    $lease = Lease::create(['number' => '1', 'taxpayer_id' => $ctb->id, 'monthly_rent' => 100, 'starts_on' => now()->startOfMonth(), 'is_active' => true]);

    Livewire::actingAs(userWithRole('consultation'))->test(Locations\Encaisser::class, ['leaseId' => $lease->id])
        ->call('collect')->assertForbidden();
    Livewire::actingAs(userWithRole('collecteur'))->test(Caisse\Bulletins::class)
        ->call('openValidate', 1)->assertForbidden();
});

test('a lease with pieces is archived instead of deleted', function () {
    $this->actingAs($this->admin);
    $ctb = Taxpayer::create(['number' => 'C1', 'last_name' => 'X']);
    $lease = Lease::create(['number' => '1', 'taxpayer_id' => $ctb->id, 'monthly_rent' => 100, 'starts_on' => now()->startOfMonth(), 'is_active' => true]);
    Livewire::test(Locations\Encaisser::class, ['leaseId' => $lease->id])->call('collect');

    Livewire::test(Locations\Locataires::class)->call('delete', $lease->id);

    expect($lease->fresh())->is_active->toBeFalse()->status->toBe('resilie');
});

test('the lease register and the non-payment notice are printed as A4 PDF', function () {
    $this->actingAs($this->admin);
    $ctb = Taxpayer::create(['number' => 'C1', 'last_name' => 'Alaoui', 'last_name_ar' => 'العلوي']);
    $lease = Lease::create(['number' => '1', 'taxpayer_id' => $ctb->id, 'monthly_rent' => 100, 'starts_on' => now()->subMonths(3)->startOfMonth(), 'is_active' => true]);

    $this->get(route('locations.repertoire'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $pdf = $this->get(route('locations.avis-imprimer', $lease))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    expect(substr($pdf, 0, 4))->toBe('%PDF');
});
