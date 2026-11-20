<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Transport\Models\Vehicle;
use App\Livewire\Transport;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\TaxOrder;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $sta = Rubric::create(['code' => '30101031', 'label' => 'Stationnement', 'module' => 'STATIONNEMENT']);
    $tpv = Rubric::create(['code' => '30101032', 'label' => 'TPV', 'module' => 'TRANSPORT_VOYAGEURS']);
    Tariff::create(['rubric_id' => $sta->id, 'label' => 'Grand Taxi', 'value' => 300, 'unit' => 'DH/an', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $sta->id, 'label' => 'Petit Taxi', 'value' => 200, 'unit' => 'DH/an', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $tpv->id, 'label' => 'Grand Taxi', 'value' => 150, 'unit' => 'DH/trimestre', 'start_date' => '2020-01-01']);
    $this->owner = Taxpayer::create(['number' => 'C1', 'last_name' => 'Ouali', 'cin' => 'JT1']);
});

function addVehicle(): Vehicle
{
    Livewire::test(Transport\Vehicules::class)->call('create')
        ->set('form.taxpayer_id', (string) test()->owner->id)->set('form.registration', '12345-a-6')->set('form.type', 'Grand Taxi')
        ->set('form.tax_start_quarter', '4')->set('form.tax_start_year', '2025')
        ->call('save')->assertHasNoErrors();

    return Vehicle::latest('id')->first();
}

test('vehicles list shows the quarterly amount (stationnement + TPV from the tax order) and the quarters due', function () {
    $this->actingAs($this->admin);
    $v = addVehicle();
    expect($v)->registration->toBe('12345-A-6')->number->toBe('1');

    Livewire::test(Transport\Vehicules::class)
        ->assertSee('12345-A-6')->assertSee('450,00 DH')->assertSee('2 trim. · 900,00 DH')
        ->set('situation', 'ajour')->assertDontSee('wire:key="v-', false);
    $this->get(route('transport.vehicules'))->assertOk();
    $this->get(route('transport.imprimer-liste'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('paying quarters late adds no penalty; one slip number for all quarters; then pending and paid', function () {
    $this->actingAs($this->admin);
    $v = addVehicle();

    Livewire::withQueryParams(['vehicule' => $v->id])->test(Transport\Encaisser::class)
        ->assertSet('selected', ['2025_4', '2026_1'])->assertSee('900,00')
        ->set('slip_number', 'BV-55')->call('pay')->assertHasNoErrors()
        ->assertSee('Aucun trimestre à payer');

    $decls = TaxDeclaration::orderBy('year')->orderBy('quarter')->get();
    expect($decls)->toHaveCount(2)
        ->and($decls[0])->number->toBe('DCL-STA202600001')->due_on->toDateString()->toBe('2026-01-31')
        ->late_penalty->toBe('0.00')->surcharge->toBe('0.00')->total->toBe('450.00')
        ->and((float) $decls[0]->rate)->toBe(300.0)->and((float) $decls[0]->base_amount)->toBe(150.0)
        ->and(PaymentSlip::where('slip_number', 'BV-55')->count())->toBe(2);

    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-3', '2026-06-16', userWithRole('regisseur'));
    $this->get(route('transport.fiche', ['vehicule' => $v->id]))->assertOk()->assertSee('Payé · Q Q-3');
});

test('a new tax order applies to the quarters it covers', function () {
    $this->actingAs($this->admin);
    $v = addVehicle();
    $order = TaxOrder::create(['number' => 'AF-2026-001', 'title' => '2026', 'effective_date' => '2026-01-01', 'status' => 'actif']);
    Tariff::where('label', 'Grand Taxi')->whereHas('rubric', fn ($q) => $q->where('module', 'STATIONNEMENT'))->update(['end_date' => '2026-01-01']);
    Tariff::create(['rubric_id' => Rubric::where('module', 'STATIONNEMENT')->value('id'), 'tax_order_id' => $order->id, 'label' => 'Grand Taxi', 'value' => 350, 'unit' => 'DH/trimestre', 'start_date' => '2026-01-01']);

    $r = app(\App\Domain\Transport\Actions\PayVehicleQuarters::class)->handle($v, ['2025_4', '2026_1'], $this->admin);
    expect((float) $r['declarations'][0]->rate)->toBe(300.0)->and((float) $r['declarations'][1]->rate)->toBe(350.0)->and($r['total'])->toBe(950.0);
});

test('notices, dashboard, register and permissions', function () {
    $this->actingAs($this->admin);
    $v = addVehicle();
    Livewire::test(Transport\Avis::class)->assertSee('12345-A-6')->set('minQuarters', 9)->assertDontSee('12345-A-6');
    $this->get(route('transport.avis-imprimer', $v))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('transport.tableau-de-bord'))->assertOk();
    $this->get(route('transport.registre'))->assertOk();
    $this->get(route('transport.encaisser'))->assertOk()->assertSee('12345-A-6');
    $this->get(route('transport.fiche'))->assertOk();

    app(\App\Domain\Transport\Actions\PayVehicleQuarters::class)->handle($v, ['2025_4'], $this->admin, ['slip_number' => 'BV-1']);
    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-1', '2026-06-16', userWithRole('regisseur'));
    Livewire::test(Transport\Vehicules::class)->call('delete', $v->id);
    expect($v->fresh()->is_active)->toBeTrue();

    $this->actingAs(userWithRole('consultation'));
    Livewire::test(Transport\Vehicules::class)->call('create')->assertForbidden();
});
