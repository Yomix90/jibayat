<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Tnb\Actions\DeclareTnbYears;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\ParcelTransfer;
use App\Domain\Tnb\Models\TnbFile;
use App\Domain\Tnb\Services\ParcelService;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->agent = userWithRole('collecteur');
    $this->cashier = userWithRole('regisseur');
    $rubric = Rubric::create(['code' => '10101001', 'label' => 'TNB', 'module' => 'TNB']);
    Tariff::create(['rubric_id' => $rubric->id, 'code' => 'TNB-B', 'label' => 'Zone B', 'value' => 4, 'unit' => 'DH/m²', 'start_date' => '2020-01-01']);
    $this->owner = Taxpayer::create(['number' => 'C1', 'last_name' => 'Bennani']);
    $this->parcel = app(ParcelService::class)->create(['taxpayer_id' => $this->owner->id, 'area' => 100, 'zone' => 'B', 'acquired_on' => '2024-05-01']);
    $this->declare = fn (array $years, array $opt = [], ?Parcel $p = null) => app(DeclareTnbYears::class)->handle($p ?? $this->parcel, $years, $this->agent, $opt);
    $this->calc = app(TnbCalculator::class);
});

test('a parcel gets its owner file and the next number; due years start at the acquisition year', function () {
    expect($this->parcel->number)->toBe('1')
        ->and(TnbFile::where('taxpayer_id', $this->owner->id)->value('number'))->toBe(1)
        ->and($this->calc->dueYears($this->parcel))->toBe([2024, 2025, 2026]);

    $second = app(ParcelService::class)->create(['taxpayer_id' => $this->owner->id, 'area' => 50, 'zone' => 'B']);
    expect($second->number)->toBe('2')->and(TnbFile::count())->toBe(1);
});

test('declaring several years creates one declaration per year and one pending slip each, under the same slip number', function () {
    $r = ($this->declare)([2026, 2024, 2025], ['slip_number' => 'BV-9', 'declared_on' => '2026-06-15']);

    expect($r['declarations'])->toHaveCount(3)->and($r['slips'])->toBe(3)->and($r['slip_number'])->toBe('BV-9');
    $decls = TaxDeclaration::orderBy('year')->get();
    expect($decls->pluck('year')->all())->toBe([2024, 2025, 2026])
        ->and($decls->pluck('number')->all())->toBe(['DCL202600001', 'DCL202600002', 'DCL202600003'])
        ->and($decls->pluck('status')->unique()->all())->toBe(['emis']);
    foreach ($decls as $d) {
        $e = $this->calc->computeYear('B', 100, $d->year, '2026-06-15');
        expect((float) $d->total)->toBe($e['total'])->and((float) $d->principal)->toBe(400.0)
            ->and((float) $d->non_filing_fine)->toBe($e['amende'])->and($d->due_on->toDateString())->toBe("{$d->year}-02-28");
    }
    expect(PaymentSlip::where('slip_number', 'BV-9')->where('status', 'en_attente')->count())->toBe(3)
        ->and(round((float) PaymentSlip::sum('amount'), 2))->toBe($r['total']);

    // Années déjà émises : ignorées ; n° de BV déjà utilisé : refusé.
    $again = ($this->declare)([2024, 2025, 2026]);
    expect($again['declarations'])->toBe([])->and($again['skipped'])->toBe([2024, 2025, 2026]);
    expect(fn () => ($this->declare)([2024], ['slip_number' => 'BV-9']))->toThrow(ValidationException::class);
});

test('below 200 DH the declaration is « sous seuil » without slip; an empty slip number is generated', function () {
    $small = app(ParcelService::class)->create(['taxpayer_id' => $this->owner->id, 'area' => 10, 'zone' => 'B', 'tax_start_year' => 2026]);
    $r = ($this->declare)([2026], ['declared_on' => '2026-02-01'], $small);
    expect($r['declarations'][0])->status->toBe('sous_seuil')->total->toBe('40.00')
        ->and($r['slips'])->toBe(0)->and(PaymentSlip::count())->toBe(0);

    $r = ($this->declare)([2026], ['declared_on' => '2026-02-01']);
    expect($r['slip_number'])->toBe('BUL202600001')->and((float) $r['total'])->toBe(400.0);
});

test('a rejected slip is replaced on re-issue (the old one is cancelled, not deleted); a paid year is no longer due', function () {
    ($this->declare)([2025], ['slip_number' => 'BV-1']);
    $decl = TaxDeclaration::first();
    $old = PaymentSlip::first();
    app(CashierService::class)->reject($old, 'Montant erroné', $this->cashier);

    $r = ($this->declare)([2025], ['slip_number' => 'BV-2']);
    expect($r['declarations'][0]->id)->toBe($decl->id)
        ->and($old->fresh())->cancelled_at->not->toBeNull()->cancellation_reason->toBe('Remplacé lors de la réémission de la déclaration')
        ->and(PaymentSlip::count())->toBe(2)
        ->and(PaymentSlip::whereNull('cancelled_at')->first())->slip_number->toBe('BV-2')->status->toBe('en_attente');

    app(CashierService::class)->validate(PaymentSlip::where('slip_number', 'BV-2')->first(), 'Q-77', '2026-06-16', $this->cashier);
    expect($decl->fresh()->status)->toBe('paye')->and($this->calc->dueYears($this->parcel))->toBe([2024, 2026]);
});

test('a construction permit exempts three years from its authorisation year; a habitation permit marks the parcel built', function () {
    $service = app(ParcelService::class);
    $permit = $service->addPermit($this->parcel, ['type' => 'construction', 'number' => 'PC-12', 'authorized_on' => '2025-03-10']);
    expect($permit)->exemption_start_year->toBe(2025)->exemption_end_year->toBe(2027)
        ->and($this->parcel->fresh()->status)->toBe('en_construction')
        ->and($this->calc->dueYears($this->parcel))->toBe([2024])
        ->and($this->calc->exemptYears($this->parcel))->toBe([2025, 2026]);

    $r = ($this->declare)([2024, 2025, 2026]);
    expect(collect($r['declarations'])->pluck('year')->all())->toBe([2024])->and($r['skipped'])->toBe([2025, 2026]);

    $service->addPermit($this->parcel, ['type' => 'habiter', 'authorized_on' => '2026-05-01']);
    expect($this->parcel->fresh()->status)->toBe('construit');
});

test('a transfer moves the parcel to the new owner file (created if missing)', function () {
    $buyer = Taxpayer::create(['number' => 'C2', 'last_name' => 'Tazi']);
    app(ParcelService::class)->transfer($this->parcel, $buyer->id, $this->agent, ['reason' => 'Vente', 'transferred_on' => '2026-06-01']);

    expect($this->parcel->fresh()->taxpayer_id)->toBe($buyer->id)
        ->and(TnbFile::where('taxpayer_id', $buyer->id)->value('number'))->toBe(2)
        ->and(ParcelTransfer::first())->from_taxpayer_id->toBe($this->owner->id)->reason->toBe('Vente');
    expect(fn () => app(ParcelService::class)->transfer($this->parcel->fresh(), $buyer->id, $this->agent))->toThrow(ValidationException::class);
});

test('cancelling an unpaid declaration keeps it with its reason and the year becomes due again; a paid one cannot be cancelled', function () {
    ($this->declare)([2024, 2025], ['slip_number' => 'BV-5']);
    $d2024 = TaxDeclaration::where('year', 2024)->first();
    app(ParcelService::class)->cancelDeclaration($d2024, $this->agent, 'Erreur de superficie');

    expect($d2024->fresh())->cancelled_at->not->toBeNull()->cancellation_reason->toBe('Erreur de superficie')
        ->and(PaymentSlip::where('tax_declaration_id', $d2024->id)->first()->cancelled_at)->not->toBeNull()
        ->and(array_column($this->calc->situation($this->parcel)['not_declared'], 'annee'))->toBe([2024, 2026])
        ->and(TaxDeclaration::count())->toBe(2);

    ($this->declare)([2024], ['slip_number' => 'BV-6']);
    expect(TaxDeclaration::where('year', 2024)->whereNull('cancelled_at')->count())->toBe(1);

    $d2025 = TaxDeclaration::where('year', 2025)->first();
    app(CashierService::class)->validate(PaymentSlip::where('tax_declaration_id', $d2025->id)->first(), 'Q-1', '2026-06-16', $this->cashier);
    expect(fn () => app(ParcelService::class)->cancelDeclaration($d2025->fresh(), $this->agent, 'test'))->toThrow(ValidationException::class);
});
