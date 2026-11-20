<?php

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Livewire\Boissons;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $rubric = Rubric::create(['code' => '30101021', 'label' => 'Débits de boissons', 'module' => 'DEBITS_BOISSONS']);
    // Tarifs réels de Flask : sans code, « Bar / Brasserie » 10 % et « Hôtel-Bar » 8 %.
    $this->cafe = Tariff::create(['rubric_id' => $rubric->id, 'label' => 'Café / Salon de thé', 'value' => 6, 'unit' => '%', 'start_date' => '2020-01-01']);
    $this->bar = Tariff::create(['rubric_id' => $rubric->id, 'label' => 'Bar / Brasserie', 'value' => 10, 'unit' => '%', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $rubric->id, 'label' => 'Restaurant', 'value' => 5, 'unit' => '%', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $rubric->id, 'label' => 'Hôtel-Bar', 'value' => 8, 'unit' => '%', 'start_date' => '2020-01-01']);
    $this->owner = Taxpayer::create(['number' => 'C1', 'last_name' => 'Amrani', 'cin' => 'JB1']);
});

function addOutlet(array $extra = []): BeverageOutlet
{
    $c = Livewire::test(Boissons\Etablissements::class)->call('create')
        ->set('form.taxpayer_id', (string) test()->owner->id)->set('form.name', 'Café Atlas')
        ->set('form.tariff_id', (string) test()->bar->id)->set('form.tax_start_quarter', '4')->set('form.tax_start_year', '2025');
    foreach ($extra as $k => $v) {
        $c->set($k, $v);
    }
    $c->call('save')->assertHasNoErrors();

    return BeverageOutlet::latest('id')->first();
}

test('an establishment takes the chosen tariff rate (not the lowest one, as Flask did with code-less tariffs)', function () {
    $this->actingAs($this->admin);
    $o = addOutlet();
    expect($o)->number->toBe('1')->tariff_id->toBe($this->bar->id)->type->toBe('Bar / Brasserie')
        ->and(app(\App\Domain\Boissons\Services\BeverageCalculator::class)->rateForOutlet($o))->toBe(10.0);

    Livewire::test(Boissons\Etablissements::class)
        ->assertSee('Café Atlas')->assertSee('10 %')->assertSee('2 trim. à déclarer')
        ->set('situation', 'ajour')->assertDontSee('Café Atlas');
    $this->get(route('boissons.etablissements'))->assertOk();
    $this->get(route('boissons.imprimer-liste'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('collecting: turnover per quarter, live computation, one slip number, then the quarter is pending', function () {
    $this->actingAs($this->admin);
    $o = addOutlet();

    $c = Livewire::withQueryParams(['etablissement' => $o->id])->test(Boissons\Encaisser::class)
        ->assertSee('T4 (Oct–Déc)')->assertSee('T1 (Jan–Mar)')
        ->set('selected', ['2025_4', '2026_1'])->call('declare')->assertHasErrors(['bases.2025_4', 'bases.2026_1'])
        ->set('bases.2025_4', '20000')->set('bases.2026_1', '15000.50')
        ->assertSee('2 000,00')     // principal T4 = 20 000 × 10 %
        ->set('slip_number', 'BV-7')->call('declare')->assertHasNoErrors();

    $decls = TaxDeclaration::orderBy('year')->orderBy('quarter')->get();
    expect($decls)->toHaveCount(2)
        ->and($decls[0])->number->toBe('DCL-TDB202600001')->quarter->toBe(4)->due_on->toDateString()->toBe('2026-01-31')
        ->and((float) $decls[0]->principal)->toBe(2000.0)
        ->and((float) $decls[1]->principal)->toBe(1500.05)
        ->and(PaymentSlip::where('slip_number', 'BV-7')->count())->toBe(2);
    $c->assertSee('Aucun trimestre à déclarer');

    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-9', '2026-06-16', userWithRole('regisseur'));
    $this->get(route('boissons.fiche', ['etablissement' => $o->id]))->assertOk()->assertSee('Payée · Q Q-9');
});

test('annual return, forms, notices, cessation, dashboard and register', function () {
    $this->actingAs($this->admin);
    $o = addOutlet(['form.tax_start_year' => '2024', 'form.tax_start_quarter' => '1']);
    app(\App\Domain\Boissons\Actions\DeclareBeverageQuarters::class)->handle($o, ['2025_1' => 10000, '2025_2' => 12000], $this->admin, ['declared_on' => '2025-07-01']);

    Livewire::withQueryParams(['etablissement' => $o->id, 'annee' => 2025])->test(Boissons\DeclarationAnnuelle::class)
        ->assertSee('Déclaration annuelle 2025')->assertSee('10 000,00')->assertSee('Non déclaré');
    $this->get(route('boissons.annuelle-imprimer', ['outlet' => $o->id, 'annee' => 2025]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    foreach (['creation', 'cessation'] as $type) {
        $this->get(route('boissons.formulaire', ['type' => $type]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
    $this->get(route('boissons.formulaire', ['type' => 'ca', 'etablissement' => $o->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('boissons.formulaire', ['type' => 'ca']))->assertNotFound();
    $this->get(route('boissons.declarations'))->assertOk();

    Livewire::test(Boissons\Avis::class)->assertSee('Café Atlas')->set('minQuarters', 50)->assertDontSee('Café Atlas');
    $this->get(route('boissons.avis-imprimer', $o))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    Livewire::withQueryParams(['taxe' => 'DEBITS_BOISSONS', 'type' => 'cessation', 'objet' => $o->id])->test(\App\Livewire\Declarations\Nouvelle::class)
        ->set('form.reason', 'Travaux')->set('form.event_on', today()->toDateString())->call('save')->assertHasNoErrors();
    expect($o->fresh()->status)->toBe('cesse');
    Livewire::test(Boissons\Cessation::class)->assertDontSee('Café Atlas')->set('status', 'cesse')->assertSee('Café Atlas');

    $this->get(route('boissons.tableau-de-bord'))->assertOk();
    $this->get(route('boissons.registre'))->assertOk();
    $this->get(route('boissons.encaisser'))->assertOk();
});

test('deleting is refused when a quarter is paid; a read-only user cannot add', function () {
    $this->actingAs($this->admin);
    $o = addOutlet();
    app(\App\Domain\Boissons\Actions\DeclareBeverageQuarters::class)->handle($o, ['2025_4' => 5000], $this->admin, ['slip_number' => 'BV-1']);
    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-1', '2026-06-16', userWithRole('regisseur'));
    Livewire::test(Boissons\Etablissements::class)->call('delete', $o->id);
    expect($o->fresh()->is_active)->toBeTrue();

    $this->actingAs(userWithRole('consultation'));
    Livewire::test(Boissons\Etablissements::class)->call('create')->assertForbidden();
});

test('the rate comes from the tax order in force for each quarter, and T4 penalties start in February of the next year', function () {
    $this->actingAs($this->admin);
    $o = addOutlet();   // lié au tarif « Bar / Brasserie » 10 % (arrêté en vigueur depuis 2020)
    $order = \App\Models\TaxOrder::create(['number' => 'AF-2026-001', 'title' => '2026', 'effective_date' => '2026-01-01', 'status' => 'actif']);
    $this->bar->update(['end_date' => '2026-01-01']);
    Tariff::create(['rubric_id' => $this->bar->rubric_id, 'tax_order_id' => $order->id, 'label' => 'Bar / Brasserie', 'value' => 12, 'unit' => '%', 'start_date' => '2026-01-01']);

    $calc = app(\App\Domain\Boissons\Services\BeverageCalculator::class);
    expect($calc->rateForQuarter($o, 2025, 4))->toBe(10.0)->and($calc->rateForQuarter($o, 2026, 1))->toBe(12.0);

    $declare = fn ($date) => app(\App\Domain\Boissons\Actions\DeclareBeverageQuarters::class)
        ->handle($o, ['2025_4' => 10000, '2026_1' => 10000], $this->admin, ['declared_on' => $date])['declarations'];
    DB::beginTransaction();
    [$t4, $t1] = $declare('2026-01-31');
    expect((float) $t4->rate)->toBe(10.0)->and((float) $t4->principal)->toBe(1000.0)->and((float) $t4->late_penalty)->toBe(0.0)
        ->and((float) $t1->rate)->toBe(12.0)->and((float) $t1->principal)->toBe(1200.0);
    DB::rollBack();

    [$t4] = $declare('2026-02-01');
    expect((float) $t4->late_penalty)->toBe(100.0)->and((float) $t4->surcharge)->toBe(50.0)->and((float) $t4->total)->toBe(1150.0);
});
