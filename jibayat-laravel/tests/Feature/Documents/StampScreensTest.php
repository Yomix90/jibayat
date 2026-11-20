<?php

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\Service;
use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Models\StampPack;
use App\Domain\Documents\Models\StampRemittance;
use App\Domain\Documents\Models\StampValue;
use App\Livewire\Documents;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->actingAs(userWithRole('administrateur'));
    $this->value = StampValue::create(['type' => 'timbre', 'label' => 'Timbre 20 DH', 'unit_value' => 20, 'units_per_book' => 50, 'is_active' => true]);
    $this->service = Service::create(['name' => 'Service des souks', 'is_active' => true]);
    $this->employee = Employee::create(['last_name' => 'Ait', 'first_name' => 'Brahim', 'service_id' => $this->service->id, 'is_active' => true]);
});

test('full stamp cycle: pack, opening, assignment, employee payment with difference, remittance, print', function () {
    Livewire::test(Documents\Paquets::class)->call('create')->set('form.number', 'P-10')->set('form.first_number', '1001')->set('form.last_number', '1500')
        ->call('save')->assertHasNoErrors();
    $pack = StampPack::first();
    expect($pack->quantity)->toBe(500);
    Livewire::test(Documents\Paquets::class)->call('open', $pack->id);
    expect(StampBook::count())->toBe(10)->and($pack->fresh()->status)->toBe('ouvert');
    Livewire::test(Documents\Paquets::class)->call('delete', $pack->id);
    expect(StampPack::count())->toBe(1);

    $book = StampBook::where('number', 'P-10-C01')->first();
    Livewire::test(Documents\Carnets::class)->call('openAssign', $book->id)->set('assignService', (string) $this->service->id)
        ->set('assignEmployee', (string) $this->employee->id)->call('assign')->assertHasNoErrors();
    expect($book->fresh()->status)->toBe('affecte');

    Livewire::test(Documents\Versements::class)->call('openPayment', $book->id)->assertSet('amount', '1000')
        ->set('amount', '990')->call('savePayment');
    expect($book->fresh())->status->toBe('consomme')->difference->toBe('-10.00');

    Livewire::test(Documents\Versements::class)->set('selected', [(string) $book->id])->set('remittanceNumber', 'BRD-REG-7')->call('createRemittance')->assertHasNoErrors();
    $r = StampRemittance::first();
    expect($r)->number->toBe('BRD-REG-7')->total_amount->toBe('990.00')->and($book->fresh()->stamp_remittance_id)->toBe($r->id);
    $this->get(route('documents.bordereau-imprimer', $r))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('documents.timbres'))->assertOk()->assertSee('Timbre 20 DH')->assertSee('990,00 DH');
});
