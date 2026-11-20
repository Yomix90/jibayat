<?php

use App\Domain\Caisse\Models\NonPaymentNotice;
use App\Domain\Caisse\Models\ReminderLetter;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Livewire\Caisse;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->actingAs(userWithRole('administrateur'));
    $t = Taxpayer::create(['number' => 'CTB1', 'last_name' => 'Alaoui', 'first_name' => 'Ahmed', 'is_active' => true]);
    $mk = fn ($n, $m, $y, $total) => TaxDeclaration::create(['number' => $n, 'module' => $m, 'year' => $y, 'quarter' => 0, 'taxpayer_id' => $t->id,
        'principal' => $total, 'total' => $total, 'status' => 'emis', 'declared_on' => '2026-01-10']);
    $this->d1 = $mk('D1', 'TNB', 2025, 1000);
    $this->d2 = $mk('D2', 'TNB', 2026, 500);
    $this->d3 = $mk('D3', 'LOCATION_LOCAUX', 2026, 840);
});

test('notices: overview, single and batch generation, letter from selection, approval, cancellation, prints', function () {
    $page = Livewire::test(Caisse\Avis::class)->assertSee('Alaoui Ahmed')->assertSee('2 340,00 DH');
    $page->call('generateOne', $this->d1->id);
    expect(NonPaymentNotice::count())->toBe(1);
    $page->call('generateOne', $this->d1->id);
    expect(NonPaymentNotice::count())->toBe(1);
    $page->set('module', 'TNB')->call('generate');
    expect(NonPaymentNotice::count())->toBe(2)->and(NonPaymentNotice::pluck('number')->all())->toBe(['AVS202600001', 'AVS202600002']);

    $ids = NonPaymentNotice::pluck('id')->map(fn ($i) => (string) $i)->all();
    $page->set('selected', $ids)->call('createLetter')->assertRedirect();
    $letter = ReminderLetter::firstOrFail();
    expect($letter)->number->toBe('LTR202600001')->status->toBe('brouillon')->total_amount->toBe('1500.00');
    $this->get(route('caisse.lettre-imprimer', $letter))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $lettres = Livewire::test(Caisse\Lettres::class, ['letterId' => $letter->id])->assertSee('LTR202600001')->call('approve', $letter->id);
    expect($letter->fresh())->status->toBe('envoyee')->sent_on->not->toBeNull();

    $page->set('module', '')->call('generate');
    $lettres->set('module', 'LOCATION_LOCAUX')->call('create');
    $second = ReminderLetter::latest('id')->first();
    $lettres->call('askCancel', $second->id)->set('cancelReason', 'x')->call('confirmCancel')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Adresse erronée')->call('confirmCancel')->assertHasNoErrors();
    expect($second->fresh())->status->toBe('annulee')->cancellation_reason->toBe('Adresse erronée')
        ->and(NonPaymentNotice::where('reminder_letter_id', $second->id)->count())->toBe(0);

    $this->get(route('caisse.avis-export'))->assertOk();
    $this->get(route('caisse.avis'))->assertOk();
    $this->get(route('caisse.lettres'))->assertOk();
});

test('a consultation user can view notices but not generate them', function () {
    $this->actingAs(userWithRole('consultation'));
    Livewire::test(Caisse\Avis::class)->assertDontSeeHtml('wire:click="generate"')->call('generate')->assertForbidden();
});
