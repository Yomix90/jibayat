<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Livewire\DomainePublic;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $r = Rubric::create(['code' => '40102011', 'label' => 'ODP', 'module' => 'OCCUPATION_DOMAINE']);
    Tariff::create(['rubric_id' => $r->id, 'label' => 'Terrasse / Étalage', 'value' => 50, 'unit' => 'DH/m²/an', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $r->id, 'label' => 'Kiosque', 'value' => 80, 'unit' => 'DH/m²/an', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $r->id, 'label' => 'Chantier', 'value' => 30, 'unit' => 'DH/m²/mois', 'start_date' => '2020-01-01']);
    $this->owner = Taxpayer::create(['number' => 'C1', 'last_name' => 'Alami', 'cin' => 'JD1']);
});

function addOccupation(string $type = 'Kiosque', string $area = '10'): PublicOccupation
{
    Livewire::test(DomainePublic\Occupations::class)->call('create')
        ->set('form.taxpayer_id', (string) test()->owner->id)->set('form.type', $type)->set('form.area', $area)
        ->set('form.location', 'Place centrale')->set('form.starts_on', '2025-01-01')->set('form.ends_on', '2026-12-31')
        ->set('form.tax_start_quarter', '1')->set('form.tax_start_year', '2025')
        ->call('save')->assertHasNoErrors();

    return PublicOccupation::latest('id')->first();
}

test('the list shows tariff × area per quarter and per year, and the quarters due', function () {
    $this->actingAs($this->admin);
    addOccupation();    // 10 m² × 80 DH/m²/an = 800/an, 200/trimestre ; dû : T1 2025 → T1 2026 = 5 trimestres
    Livewire::test(DomainePublic\Occupations::class)
        ->assertSee('Kiosque · 10,00 m²')->assertSee('200,00')->assertSee('800,00 DH/an')->assertSee('5 trim. · 1 000,00 DH');
    $this->get(route('domaine-public.occupations'))->assertOk();
    $this->get(route('domaine-public.imprimer-liste'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('quarters or a whole year are liquidated without penalty, under one slip number', function () {
    $this->actingAs($this->admin);
    $o = addOccupation();

    $c = Livewire::withQueryParams(['occupation' => $o->id])->test(DomainePublic\Encaisser::class)
        ->assertSee('Année entière · 800,00 DH')
        ->set('selected', ['2025_1', '2025_0'])->assertSet('selected', ['2025_0'])   // l'année remplace ses trimestres
        ->set('selected', ['2025_0', '2026_1'])->assertSee('1 000,00')
        ->set('slip_number', 'BV-3')->call('declare')->assertHasNoErrors();

    $d = TaxDeclaration::orderBy('year')->orderBy('quarter')->get();
    expect($d)->toHaveCount(2)
        ->and($d[0])->quarter->toBe(0)->total->toBe('800.00')->due_on->toDateString()->toBe('2025-12-31')->late_penalty->toBe('0.00')
        ->and($d[1])->quarter->toBe(1)->total->toBe('200.00')->number->toBe('DCL-ODP202600002')
        ->and(PaymentSlip::where('slip_number', 'BV-3')->count())->toBe(2);
    $c->assertSee('Rien à liquider');

    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-5', '2026-06-16', userWithRole('regisseur'));
    $this->get(route('domaine-public.fiche', ['occupation' => $o->id]))->assertOk()->assertSee('Payé · Q Q-5')->assertSee('Année 2025');
});

test('a small occupation below 100 DH is recorded « sous seuil » without slip; renewal extends the authorisation', function () {
    $this->actingAs($this->admin);
    $o = addOccupation('Terrasse / Étalage', '2');   // 2 × 50 = 100/an ➜ 25/trimestre
    $r = app(\App\Domain\DomainePublic\Actions\DeclareOccupationPeriods::class)->handle($o, ['2025_1'], $this->admin);
    expect($r['declarations'][0]->status)->toBe('sous_seuil')->and($r['slips'])->toBe(0);

    Livewire::withQueryParams(['occupation' => $o->id])->test(DomainePublic\Fiche::class)->call('renew', '2_trimestres');
    expect($o->fresh()->ends_on->toDateString())->toBe('2027-06-30');
    Livewire::test(DomainePublic\Renouveler::class)->set('withinDays', 300)->assertDontSee('Place centrale')
        ->set('withinDays', 800)->assertSee('Place centrale');
});

test('notices, dashboard, register and permissions', function () {
    $this->actingAs($this->admin);
    $o = addOccupation();
    Livewire::test(DomainePublic\Avis::class)->assertSee('Alami')->set('minQuarters', 9)->assertDontSee('Alami');
    $this->get(route('domaine-public.avis-imprimer', $o))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    foreach (['tableau-de-bord', 'registre', 'encaisser', 'fiche', 'renouveler'] as $p) {
        $this->get(route("domaine-public.{$p}"))->assertOk();
    }
    $this->actingAs(userWithRole('consultation'));
    Livewire::test(DomainePublic\Occupations::class)->call('create')->assertForbidden();
});
