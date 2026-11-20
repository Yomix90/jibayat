<?php

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Declarations\Models\AssessmentDeclaration;
use App\Domain\Declarations\Services\DeclarationService;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\Systeme\AlertService;
use App\Domain\Tnb\Models\Parcel;
use App\Livewire\Declarations;
use App\Livewire\DomainePublic\Renouveler;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $this->actingAs($this->admin);
    $this->owner = Taxpayer::create(['number' => 'CTB1', 'last_name' => 'Alaoui', 'first_name' => 'Ahmed', 'cin' => 'JB1', 'phone' => '0611223344', 'is_active' => true]);
    $this->outlet = BeverageOutlet::create(['number' => '7', 'name' => 'Café Atlas', 'taxpayer_id' => $this->owner->id, 'type' => 'Café', 'tax_start_quarter' => 1, 'tax_start_year' => 2025, 'status' => 'actif', 'is_active' => true]);
});

test('legal deadlines and late filing', function () {
    $t = DeclarationService::TYPES;
    expect(DeclarationService::deadline($t['TNB']['annuelle_terrains'], null, 2026)->toDateString())->toBe('2026-02-28')
        ->and(DeclarationService::deadline($t['DEBITS_BOISSONS']['recettes_annuelles'], null, 2025)->toDateString())->toBe('2026-03-31')
        ->and(DeclarationService::deadline($t['DEBITS_BOISSONS']['inactivite'], null, 2025)->toDateString())->toBe('2026-01-31')
        ->and(DeclarationService::deadline($t['DEBITS_BOISSONS']['cessation'], '2026-05-01', null)->toDateString())->toBe('2026-06-15')
        ->and(DeclarationService::deadline($t['DEBITS_BOISSONS']['creation'], '2026-05-01', null)->toDateString())->toBe('2026-05-31');

    $d = app(DeclarationService::class)->file('DEBITS_BOISSONS', 'recettes_annuelles', $this->outlet->id, ['fiscal_year' => 2025, 'amount' => 120000], $this->admin);
    expect($d)->is_late->toBeTrue()->number->toBe('DAS202600001');
});

test('a declared stop of activity exempts the quarters in between; the resumption ends it', function () {
    $calc = app(BeverageCalculator::class);
    $before = collect($calc->unpaidQuarters($this->outlet))->map(fn ($q) => $q['annee'].'T'.$q['trimestre'])->all();
    expect($before)->toBe(['2025T1', '2025T2', '2025T3', '2025T4', '2026T1']);

    // Arrêt le 15/05/2025 (motif, reprise prévue) : T2 2025 (trimestre de l'arrêt) reste dû, T3 et T4 2025 exonérés.
    Livewire::withQueryParams(['taxe' => 'DEBITS_BOISSONS', 'type' => 'cessation', 'objet' => $this->outlet->id])->test(Declarations\Nouvelle::class)
        ->set('form.event_on', '2025-05-15')->set('form.reason', '')->call('save')->assertHasErrors('form.reason')
        ->set('form.reason', 'Travaux / rénovation')->set('form.resume_on', '2026-01-10')->set('form.signed', true)->call('save')->assertHasNoErrors()->assertRedirect();
    expect($this->outlet->fresh()->status)->toBe('cesse');
    $stop = AssessmentDeclaration::firstOrFail();
    expect($stop)->is_late->toBeTrue()->signed->toBeTrue()->reason->toBe('Travaux / rénovation');
    expect(collect($calc->unpaidQuarters($this->outlet->fresh()))->map(fn ($q) => $q['annee'].'T'.$q['trimestre'])->all())->toBe(['2025T1', '2025T2']);

    // Reprise le 10/01/2026 : T1 2026 redevient dû.
    app(DeclarationService::class)->file('DEBITS_BOISSONS', 'reprise', $this->outlet->id, ['event_on' => '2026-01-10'], $this->admin);
    expect($this->outlet->fresh()->status)->toBe('actif')
        ->and(collect($calc->unpaidQuarters($this->outlet->fresh()))->map(fn ($q) => $q['annee'].'T'.$q['trimestre'])->all())->toBe(['2025T1', '2025T2', '2026T1']);

    // Une seconde reprise est refusée ; annuler la reprise rétablit l'arrêt.
    expect(fn () => app(DeclarationService::class)->file('DEBITS_BOISSONS', 'reprise', $this->outlet->id, ['event_on' => '2026-02-01'], $this->admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    app(DeclarationService::class)->cancel(AssessmentDeclaration::where('type', 'reprise')->first(), $this->admin, 'Saisie erronée');
    expect($this->outlet->fresh()->status)->toBe('cesse');

    $this->get(route('declarations.imprimer', $stop))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('declarations.formulaire', ['DEBITS_BOISSONS', 'cessation']))->assertOk();
    $this->get(route('declarations.formulaire', ['TNB', 'xx']))->assertNotFound();
    $this->get(route('boissons.cessation', ['statut' => 'cesse']))->assertOk()->assertSee('Travaux / rénovation');
});

test('register, annual deadlines, forms and permissions', function () {
    Parcel::create(['number' => '12', 'taxpayer_id' => $this->owner->id, 'area' => 400, 'zone' => 'A', 'status' => 'non_bati', 'tax_start_year' => 2024, 'is_active' => true]);
    Livewire::test(Declarations\Echeances::class)->assertSee('Terrain n° 12')->assertSee('Café Atlas');
    app(DeclarationService::class)->file('TNB', 'annuelle_terrains', Parcel::first()->id, ['fiscal_year' => 2026, 'amount' => 400], $this->admin);
    Livewire::test(Declarations\Echeances::class)->assertDontSee('Terrain n° 12');

    Livewire::test(Declarations\Registre::class)->assertSee('DAS202600001')->set('status', 'late')->assertSee('DAS202600001')
        ->call('markSigned', AssessmentDeclaration::first()->id);
    expect(AssessmentDeclaration::first()->signed)->toBeTrue();
    foreach (['nouvelle', 'registre', 'echeances', 'formulaires'] as $p) {
        $this->get(route("declarations.$p"))->assertOk();
    }
    $this->get('/declarations')->assertRedirect(route('declarations.nouvelle'));

    $this->actingAs(userWithRole('consultation'));
    Livewire::withQueryParams(['taxe' => 'TNB', 'type' => 'annuelle_terrains', 'objet' => Parcel::first()->id])->test(Declarations\Nouvelle::class)
        ->set('form.amount', '400')->call('save')->assertForbidden();
});

test('public domain: expiring authorisations alert the agent until the holder is contacted; souk contracts near the end of their period', function () {
    $o = PublicOccupation::create(['number' => '3', 'taxpayer_id' => $this->owner->id, 'type' => 'Terrasse', 'location' => 'Av. 1', 'area' => 10,
        'ends_on' => '2026-06-30', 'tax_start_quarter' => 1, 'tax_start_year' => 2025, 'status' => 'actif', 'is_active' => true]);
    $keys = fn () => collect(app(AlertService::class)->collect())->pluck('key')->all();
    expect($keys())->toContain('odp_expiration');
    Livewire::test(Renouveler::class)->assertSee('0611223344')->assertSee('À contacter')
        ->call('openContact', $o->id)->set('contactNote', 'Passera lundi')->call('saveContact')->assertHasNoErrors()->assertSee('Passera lundi');
    expect($o->fresh()->renewal_contacted_at)->not->toBeNull()->and($keys())->not->toContain('odp_expiration');
});

test('surcharge of 15 % (min 500 DH) unless the annual declaration was filed in time', function () {
    $tnb = \App\Models\Rubric::create(['code' => '30101011', 'label' => 'TNB', 'module' => 'TNB']);
    \App\Models\Tariff::create(['rubric_id' => $tnb->id, 'label' => 'Zone A', 'code' => 'A', 'value' => 20, 'unit' => 'DH/m²', 'start_date' => '2020-01-01']);
    $parcel = Parcel::create(['number' => '12', 'taxpayer_id' => $this->owner->id, 'area' => 400, 'zone' => 'A', 'status' => 'non_bati', 'tax_start_year' => 2024, 'is_active' => true]);
    $calc = app(\App\Domain\Tnb\Services\TnbCalculator::class);

    // Pas de déclaration : majoration max(15 % × 8 000, 500) = 1 200 DH.
    expect($calc->computeYear('A', 400, 2026, '2026-06-15', $parcel->id)['amende'])->toBe(1200.0);
    // Déclaration déposée en retard (après le 1er mars) : toujours due.
    $this->travelTo(CarbonImmutable::parse('2026-03-05'));
    app(DeclarationService::class)->file('TNB', 'annuelle_terrains', $parcel->id, ['fiscal_year' => 2026, 'amount' => 400], $this->admin);
    expect($calc->computeYear('A', 400, 2026, '2026-06-15', $parcel->id)['amende'])->toBe(1200.0);
    // Déclaration 2025 déposée avant le 1er mars 2025 : pas de majoration (pénalités de retard de paiement inchangées).
    $this->travelTo(CarbonImmutable::parse('2025-02-10'));
    app(DeclarationService::class)->file('TNB', 'annuelle_terrains', $parcel->id, ['fiscal_year' => 2025, 'amount' => 400], $this->admin);
    $r = $calc->computeYear('A', 400, 2025, '2026-06-15', $parcel->id);
    expect($r['amende'])->toBe(0.0)->and($r['penalite'])->toBeGreaterThan(0);

    // Débits de boissons : T4 2025 payé après le 1er avril 2026.
    $bev = app(BeverageCalculator::class);
    expect($bev->computeQuarter(20000, 6, 2025, 4, '2026-06-15', $this->outlet->id)['amende'])->toBe(500.0);
    $this->travelTo(CarbonImmutable::parse('2026-03-20'));
    app(DeclarationService::class)->file('DEBITS_BOISSONS', 'recettes_annuelles', $this->outlet->id, ['fiscal_year' => 2025, 'amount' => 80000], $this->admin);
    expect($bev->computeQuarter(20000, 6, 2025, 4, '2026-06-15', $this->outlet->id)['amende'])->toBe(0.0);
});
