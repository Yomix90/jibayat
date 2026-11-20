<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Locations\Actions\CollectRent;
use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->agent = userWithRole('collecteur');
    $this->cashier = userWithRole('regisseur');
    $this->today = CarbonImmutable::parse('2026-06-15');
    $ctb = Taxpayer::create(['number' => 'C1', 'last_name' => 'Alaoui']);
    $this->lease = Lease::create(['number' => '1', 'taxpayer_id' => $ctb->id, 'monthly_rent' => 420, 'starts_on' => '2026-03-01', 'status' => 'actif', 'is_active' => true]);
    $this->collect = fn (array $months, array $opt = []) => app(CollectRent::class)
        ->handle($this->lease, $months, $this->agent, $opt + ['today' => '2026-06-15']);
});

test('collecting months creates an issued declaration with its periods and a pending slip', function () {
    $decl = ($this->collect)(['2026-04', '2026-03'], ['slip_number' => 'BV-77', 'notes' => 'acompte']);

    expect($decl)->number->toBe('LOC-DECL'.now()->year.'00001')->status->toBe('emis')
        ->and((float) $decl->total)->toBe(840.0)->and((float) $decl->base_amount)->toBe(420.0)
        ->and($decl->year)->toBe(2026)->and($decl->notes)->toBe('2026-03, 2026-04 | acompte')
        ->and($decl->taxable->is($this->lease))->toBeTrue()
        ->and($decl->periods()->pluck('period')->all())->toBe(['2026-03', '2026-04']);

    $slip = PaymentSlip::first();
    expect($slip)->slip_number->toBe('BV-77')->status->toBe('en_attente')->amount->toBe('840.00');

    $months = app(LeaseCalculator::class)->unpaidMonths($this->lease, $this->today);
    expect(collect($months)->where('est_emis', true)->pluck('mois')->all())->toBe(['2026-03', '2026-04']);
});

test('slip numbers are generated when empty and must be unique when typed', function () {
    ($this->collect)(['2026-03']);
    expect(PaymentSlip::first()->slip_number)->toBe('BUL'.now()->year.'00001');

    ($this->collect)(['2026-04']);
    expect(PaymentSlip::latest('id')->first()->slip_number)->toBe('BUL'.now()->year.'00002')
        ->and(TaxDeclaration::latest('id')->first()->number)->toBe('LOC-DECL'.now()->year.'00002');

    expect(fn () => ($this->collect)(['2026-05'], ['slip_number' => 'BUL'.now()->year.'00001']))->toThrow(ValidationException::class);
});

test('already issued, paid or out-of-lease months are refused', function () {
    ($this->collect)(['2026-03']);

    expect(fn () => ($this->collect)(['2026-03']))->toThrow(ValidationException::class)
        ->and(fn () => ($this->collect)(['2026-02']))->toThrow(ValidationException::class)
        ->and(fn () => ($this->collect)(['2026-07']))->toThrow(ValidationException::class)
        ->and(fn () => ($this->collect)([]))->toThrow(ValidationException::class);
});

test('the régisseur validates with a receipt number: the slip, its twins and the declarations are paid', function () {
    ($this->collect)(['2026-03'], ['slip_number' => 'BV-1']);
    $other = ($this->collect)(['2026-04'], ['slip_number' => 'BV-2']);
    PaymentSlip::where('tax_declaration_id', $other->id)->update(['slip_number' => 'BV-1']); // même BV : validation en cascade

    $n = app(CashierService::class)->validate(PaymentSlip::first(), 'Q-555', '2026-06-16', $this->cashier);

    expect($n)->toBe(2)
        ->and(PaymentSlip::where('status', 'paye')->count())->toBe(2)
        ->and(PaymentSlip::first()->receipt_number)->toBe('Q-555')
        ->and(TaxDeclaration::where('status', 'paye')->count())->toBe(2)
        ->and(TaxDeclaration::first()->paid_on->toDateString())->toBe('2026-06-16');

    $months = app(LeaseCalculator::class)->unpaidMonths($this->lease, $this->today);
    expect(collect($months)->pluck('mois')->all())->toBe(['2026-05', '2026-06']);
});

test('a receipt number cannot be reused for another slip, and a receipt is required', function () {
    ($this->collect)(['2026-03'], ['slip_number' => 'BV-1']);
    ($this->collect)(['2026-04'], ['slip_number' => 'BV-2']);
    $cashier = app(CashierService::class);
    $cashier->validate(PaymentSlip::where('slip_number', 'BV-1')->first(), 'Q-1', '2026-06-16', $this->cashier);

    expect(fn () => $cashier->validate(PaymentSlip::where('slip_number', 'BV-2')->first(), 'Q-1', '2026-06-16', $this->cashier))
        ->toThrow(ValidationException::class)
        ->and(fn () => $cashier->validate(PaymentSlip::where('slip_number', 'BV-2')->first(), ' ', '2026-06-16', $this->cashier))
        ->toThrow(ValidationException::class);
});

test('rejecting puts the declaration back to issued; correcting resubmits the slip', function () {
    ($this->collect)(['2026-03'], ['slip_number' => 'BV-1']);
    $slip = PaymentSlip::first();
    $cashier = app(CashierService::class);

    $cashier->reject($slip, 'Montant illisible', $this->cashier);
    expect($slip->fresh())->status->toBe('rejete')->rejection_reason->toBe('Montant illisible')
        ->and(TaxDeclaration::first()->status)->toBe('emis');

    $cashier->correct($slip->fresh(), 'BV-1-bis', 'virement', 'corrigé', $this->agent);
    expect($slip->fresh())->status->toBe('en_attente')->slip_number->toBe('BV-1-bis')->rejection_reason->toBeNull();
});

test('cancelling a slip and its declaration frees the months, nothing is deleted', function () {
    ($this->collect)(['2026-03', '2026-04'], ['slip_number' => 'BV-1']);

    app(CashierService::class)->cancel(PaymentSlip::first(), 'Erreur de bail', $this->cashier, cancelDeclaration: true);

    expect(PaymentSlip::count())->toBe(1)->and(PaymentSlip::first()->isCancelled())->toBeTrue()
        ->and(TaxDeclaration::first())->status->toBe('annule')->cancellation_reason->toBe('Erreur de bail');
    $months = app(LeaseCalculator::class)->unpaidMonths($this->lease, $this->today);
    expect(collect($months)->where('est_emis', true))->toBeEmpty()->and(count($months))->toBe(4);
});
