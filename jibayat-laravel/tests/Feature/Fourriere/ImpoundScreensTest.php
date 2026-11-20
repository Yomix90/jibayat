<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Fourriere\Models\AuctionGroup;
use App\Domain\Fourriere\Models\AuctionGroupVehicle;
use App\Domain\Fourriere\Models\AuctionSale;
use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Models\ImpoundSetting;
use App\Livewire\Fourriere;
use App\Models\Rubric;
use App\Models\Tariff;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $r = Rubric::create(['code' => '40102030', 'label' => 'Fourrière', 'module' => 'FOURRIERE']);
    Tariff::create(['rubric_id' => $r->id, 'label' => 'Voiture particulière / jour', 'value' => 25, 'unit' => 'DH/jour', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $r->id, 'label' => 'Moto / Scooter / jour', 'value' => 15, 'unit' => 'DH/jour', 'start_date' => '2020-01-01']);
    Tariff::create(['rubric_id' => $r->id, 'label' => 'Frais de remorquage', 'value' => 150, 'unit' => 'DH', 'start_date' => '2020-01-01']);
});

function impound(string $date = '2026-06-06', string $reg = '12-a-3'): ImpoundCase
{
    Livewire::test(Fourriere\Dossiers::class)->call('create')->assertSet('form.towing_fee', '150')
        ->set('form.vehicle_type', 'Voiture particulière / jour')->set('form.registration', $reg)->set('form.impounded_on', $date)
        ->set('form.depositor', 'Police')->call('save')->assertHasNoErrors();

    return ImpoundCase::latest('id')->first();
}

test('impounding freezes the daily rate; the list shows days and amount due', function () {
    $this->actingAs($this->admin);
    $c = impound();   // 10 jours × 25 + 150 = 400
    expect($c)->registration->toBe('12-A-3')->daily_rate->toBe('25.00')->towing_fee->toBe('150.00')->status->toBe('en_fourriere');
    Livewire::test(Fourriere\Dossiers::class)->assertSee('12-A-3')->assertSee('400,00 DH')->set('status', 'sortie')->assertDontSee('wire:key="c-', false);
    $this->get(route('fourriere.imprimer-liste'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('paying, validation by the régisseur, then release', function () {
    $this->actingAs($this->admin);
    $c = impound();
    $this->get(route('fourriere.encaisser'))->assertOk()->assertSee('12-A-3');
    Livewire::withQueryParams(['dossier' => $c->id])->test(Fourriere\Encaisser::class)
        ->assertSee('400,00')->set('owner_name', 'Rachid Amrani')->set('owner_cin', 'JX1')->set('slip_number', 'BV-77')
        ->call('pay')->assertHasNoErrors();
    expect(TaxDeclaration::first())->total->toBe('400.00')->number->toBe('FOU-DECL202600001')
        ->and($c->fresh())->owner_name->toBe('Rachid Amrani')->slip_number->toBe('BV-77');
    Livewire::withQueryParams(['dossier' => $c->id])->test(Fourriere\Encaisser::class)->assertSee('Encaissement impossible');

    Livewire::withQueryParams(['dossier' => $c->id])->test(Fourriere\Fiche::class)->assertDontSee('Valider la sortie');
    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-7', '2026-06-15', userWithRole('regisseur'));
    expect($c->fresh()->status)->toBe('en_attente_sortie');
    Livewire::test(Fourriere\Sortie::class)->assertSee('12-A-3')->call('release', $c->id);
    expect($c->fresh()->status)->toBe('sortie');
});

test('vehicles over 365 days go into an auction group, are printed and sold', function () {
    $this->actingAs($this->admin);
    Storage::fake('local');
    ImpoundSetting::create(['category' => 'etat_vehicule', 'code' => 'EPAVE', 'label' => 'Épave', 'sort_order' => 1]);
    $old = impound('2025-05-01', '99-B-9');
    Livewire::test(Fourriere\Groupes::class)->call('create')->set('form.label', 'Enchère 2026')->set('form.opening_price', '2000')->call('save')->assertHasNoErrors();
    $g = AuctionGroup::first();
    expect($g->number)->toBe('GE20260001');

    Livewire::test(Fourriere\Encheres::class)->assertSee('99-B-9')
        ->set("groupFor.{$old->id}", (string) $g->id)->set("conditionFor.{$old->id}", 'EPAVE')->call('addToGroup', $old->id)->assertHasNoErrors();
    expect(AuctionGroupVehicle::first())->condition->toBe('EPAVE');
    $this->get(route('fourriere.groupe-imprimer', $g))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    Livewire::test(Fourriere\Groupes::class)->call('openSale', $g->id, $old->id)
        ->set('sale.buyer_name', 'Ferraille SARL')->set('sale.hammer_price', '3500')->set('sale.receipt_number', 'Q-AV-1')
        ->set('report', UploadedFile::fake()->create('pv.pdf', 30, 'application/pdf'))->call('saveSale')->assertHasNoErrors();
    expect(AuctionSale::first())->hammer_price->toBe('3500.00')->and($old->fresh()->status)->toBe('sortie')->and($g->fresh()->status)->toBe('vendu');
    $this->get(route('fourriere.ventes'))->assertOk()->assertSee('Ferraille SARL');
    $this->get(route('fourriere.pv', AuctionSale::first()))->assertOk();
});

test('settings, dashboard, register, file and permissions', function () {
    $this->actingAs($this->admin);
    Livewire::test(Fourriere\Parametres::class)->set('newLabel.deposant', 'Gendarmerie royale')->call('add', 'deposant')->assertHasNoErrors();
    expect(ImpoundSetting::where('category', 'deposant')->value('code'))->toBe('GENDARMERIE_ROYALE');
    foreach (['tableau-de-bord', 'registre', 'fiche', 'sortie', 'encheres', 'groupes', 'parametres'] as $p) {
        $this->get(route("fourriere.{$p}"))->assertOk();
    }
    $c = impound();
    $this->get(route('fourriere.fiche', ['dossier' => $c->id]))->assertOk()->assertSee('10 jour(s)');

    $this->actingAs(userWithRole('consultation'));
    Livewire::test(Fourriere\Dossiers::class)->call('create')->assertForbidden();
});
