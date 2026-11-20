<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Models\SoukContractDocument;
use App\Domain\Souk\Services\ContractService;
use App\Livewire\Souk;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $this->owner = Taxpayer::create(['number' => 'C1', 'last_name' => 'Haddou', 'cin' => 'JS1']);
});

function addContract(array $extra = []): SoukContract
{
    $c = Livewire::test(Souk\Contrats::class)->call('create')
        ->set('form.taxpayer_id', (string) test()->owner->id)->set('form.souk_name', 'Souk Had')->set('form.pitch_number', 'E-12')
        ->set('form.monthly_fee', '1000')->set('form.starts_on', '2025-01-01')->set('form.duration_years', '1')->set('form.increase_rate', '10');
    foreach ($extra as $k => $v) {
        $c->set($k, $v);
    }
    $c->call('save')->assertHasNoErrors();

    return SoukContract::latest('id')->first();
}

test('a contract gets its default end date; the list shows the current fee and the months due', function () {
    $this->actingAs($this->admin);
    $c = addContract(['form.ends_on' => '2027-12-31']);
    expect($c)->number->toBe('1')->ends_on->toDateString()->toBe('2027-12-31')->annual_fee->toBe('12000.00');

    $d = addContract(['form.ends_on' => '']);
    expect($d->ends_on->toDateString())->toBe('2025-12-31');

    Livewire::test(Souk\Contrats::class)->assertSee('Souk Had')->assertSee('1 100,00')   // 2e période : 1 000 + 10 %
        ->assertSee('18 mois');
    $this->get(route('souk.contrats'))->assertOk();
    $this->get(route('souk.imprimer-liste'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('collecting takes the oldest months first, at the fee of their period', function () {
    $this->actingAs($this->admin);
    $c = addContract(['form.ends_on' => '2027-12-31']);

    Livewire::withQueryParams(['contrat' => $c->id])->test(Souk\Encaisser::class)
        ->assertSet('count', 18)
        ->set('count', 14)->assertSee('14 200,00')        // 12 × 1 000 + 2 × 1 100
        ->set('slip_number', 'BV-9')->set('transfer_number', 'V-1')->call('collect')->assertHasNoErrors()
        ->assertSet('count', 4);

    $d = TaxDeclaration::first();
    expect((float) $d->total)->toBe(14200.0)->and($d->number)->toBe('SOU-DECL202600001')
        ->and($d->periods()->orderBy('period')->pluck('period')->first())->toBe('2025-01')
        ->and(PaymentSlip::first())->slip_number->toBe('BV-9')->transfer_number->toBe('V-1');

    app(CashierService::class)->validate(PaymentSlip::first(), 'Q-1', '2026-06-16', userWithRole('regisseur'));
    $this->get(route('souk.fiche', ['contrat' => $c->id]))->assertOk()->assertSee('Payé · Q Q-1')->assertSee('Renouvellement 1');
});

test('renewing extends the end date by one period without losing past months; terminating stops future months', function () {
    $this->actingAs($this->admin);
    $c = addContract(['form.ends_on' => '']);   // fin 31/12/2025 ➜ mois dus jusqu'à 12/2025
    expect(app(\App\Domain\Souk\Services\SoukCalculator::class)->situation($c)['nb_mois_impaye'])->toBe(12);

    $page = Livewire::withQueryParams(['contrat' => $c->id])->test(Souk\Fiche::class)->call('openRenew')
        ->assertSet('renewal.date_debut', '2026-01-01')->assertSet('renewal.date_fin', '2026-12-31');
    $current = (float) $page->get('renewal.actuelle');
    $page->set('renewal.taux', '10')->assertSet('renewal.redevance', number_format(round($current * 1.10, 2), 2, '.', ''))
        ->set('renewal.notes', 'Accord du locataire')->call('renew')->assertHasNoErrors();
    $r = \App\Domain\Souk\Models\SoukContractRenewal::firstOrFail();
    expect($r)->renewal_no->toBe(1)->increase_rate->toBe('10.00')->and((float) $r->monthly_fee)->toEqual(round($current * 1.10, 2));
    $periods = app(\App\Domain\Souk\Services\SoukCalculator::class)->periods($c->fresh(), \Carbon\CarbonImmutable::parse('2026-06-15'));
    expect($periods[1]['redevance_mensuelle'])->toEqual(round($current * 1.10, 2));
    $c->refresh();
    expect($c->ends_on->toDateString())->toBe('2026-12-31')->and($c->starts_on->toDateString())->toBe('2025-01-01')
        ->and(app(\App\Domain\Souk\Services\SoukCalculator::class)->situation($c)['nb_mois_impaye'])->toBe(18);

    Livewire::withQueryParams(['contrat' => $c->id])->test(Souk\Fiche::class)
        ->call('openTerminate')->set('terminateOn', '2026-03-31')->set('terminateReason', 'Abandon')->call('terminate');
    $c->refresh();
    expect($c->status)->toBe('resilie')->and(app(\App\Domain\Souk\Services\SoukCalculator::class)->situation($c)['nb_mois_impaye'])->toBe(15);
});

test('documents, notices, dashboard, register and permissions', function () {
    $this->actingAs($this->admin);
    Storage::fake('local');
    $c = addContract();
    Livewire::withQueryParams(['contrat' => $c->id])->test(Souk\Fiche::class)
        ->call('openDocument')->set('upload', UploadedFile::fake()->create('contrat.pdf', 40, 'application/pdf'))->call('saveDocument')->assertHasNoErrors();
    $doc = SoukContractDocument::first();
    $this->get(route('souk.document', $doc))->assertOk();

    Livewire::test(Souk\Avis::class)->assertSee('Haddou')->set('minMonths', 99)->assertDontSee('Haddou');
    $this->get(route('souk.avis-imprimer', $c))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('souk.tableau-de-bord'))->assertOk();
    $this->get(route('souk.registre'))->assertOk();
    $this->get(route('souk.encaisser'))->assertOk()->assertSee('Haddou');
    $this->get(route('souk.fiche'))->assertOk();

    $this->actingAs(userWithRole('consultation'));
    Livewire::test(Souk\Contrats::class)->call('create')->assertForbidden();
});
