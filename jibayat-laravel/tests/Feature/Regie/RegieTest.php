<?php

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\StampValue;
use App\Domain\Regie\Models\CashEntry;
use App\Domain\Regie\Models\CashReport;
use App\Domain\Regie\Models\PrintedDocument;
use App\Domain\Regie\Models\TicketDischarge;
use App\Domain\Regie\Services\ControlService;
use App\Domain\Regie\Services\DocumentRegistry;
use App\Domain\Regie\Services\EntryService;
use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\MinutesService;
use App\Domain\Regie\Services\MonthLock;
use App\Domain\Regie\Services\RemittanceService;
use App\Domain\Regie\Services\StockService;
use App\Livewire\Regie;
use App\Models\Rubric;
use App\Models\Setting;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $this->actingAs($this->admin);
    Setting::put('regie.solde_initial', '1000');
    Setting::put('regie.solde_initial_date', '2026-01-01');
    $this->souk = Rubric::create(['code' => '40102029', 'label' => 'Droits de place au souk', 'module' => 'REGIE_SOUK', 'family' => 'domaine', 'manual_entry' => true]);
    $this->legal = Rubric::create(['code' => '10403011', 'label' => 'Droits de légalisation', 'module' => 'REGIE_LEGAL', 'family' => 'services', 'manual_entry' => true]);
    $this->entries = app(EntryService::class);
    $this->ledger = app(Ledger::class);
});

test('receipts are entered as drafts, validated, then appear in the cash book; ministry transfers stay out', function () {
    Livewire::test(Regie\Saisie::class)->set('date', '2026-06-10')
        ->set('lines.0.rubric_id', $this->souk->id)->set('lines.0.quantity', '40')->set('lines.0.unit_price', '5')->set('lines.0.reference', 'T-1')
        ->call('addLine')->set('lines.1.rubric_id', $this->legal->id)->set('lines.1.payment_mode', 'cheque')->set('lines.1.amount', '300')
        ->call('save')->assertHasErrors('lines.1.reference')
        ->set('lines.1.reference', 'CHQ 778')->call('save')->assertHasNoErrors();
    expect(CashEntry::where('status', 'brouillon')->count())->toBe(2)
        ->and(CashEntry::first())->amount->toEqual(200.0)->number->toBe('REC202600001');

    // Brouillons : pas encore au livre de caisse.
    expect($this->ledger->cashBook(2026, 6)['total_receipts'])->toEqual(0.0);
    Livewire::test(Regie\Brouillons::class)->assertSee('REC202600001')->call('validateAll');
    $book = $this->ledger->cashBook(2026, 6);
    expect($book['carried'])->toEqual(1000.0)->and($book['total_cash'])->toEqual(200.0)->and($book['total_other'])->toEqual(300.0)->and($book['closing'])->toEqual(1500.0);

    // Virement du Ministère : hors caisse et hors Modèle 10, mais au récapitulatif comptable.
    $v = $this->entries->createDrafts('2026-06-11', [['rubric_id' => $this->legal->id, 'payment_mode' => 'virement_ministere', 'amount' => 900, 'reference' => 'OV 12', 'payer' => 'Ministère de la Santé']], $this->admin);
    $this->entries->validate([$v[0]->id], $this->admin);
    $state = $this->ledger->monthlyState(2026, 6);
    expect($state['total_month'])->toEqual(500.0)->and($state['ministry_excluded'])->toEqual(900.0)
        ->and($this->ledger->balanceAt('2026-06-30'))->toEqual(1500.0)
        ->and($this->ledger->recap('2026-06-01', '2026-06-30')['total'])->toEqual(1400.0);
    $this->get(route('regie.imprimer.virement', $v[0]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    // Annulation motivée d'une recette validée.
    Livewire::test(Regie\Recettes::class)->call('askCancel', CashEntry::first()->id)->set('cancelReason', 'Erreur de rubrique')->call('confirmCancel');
    expect(CashEntry::first()->isCancelled())->toBeTrue()->and($this->ledger->cashBook(2026, 6)['closing'])->toEqual(1300.0);
    $this->get(route('documents.pieces-annulees', ['module' => 'REGIE']))->assertOk()->assertSee('REC202600001');
});

test('monthly closure freezes the Model 10 and locks the month, including cashier receipts', function () {
    $lock = app(MonthLock::class);
    $e = $this->entries->createDrafts('2026-05-20', [['rubric_id' => $this->souk->id, 'payment_mode' => 'especes', 'amount' => 250]], $this->admin);
    expect($lock->blockers(2026, 5))->toContain('1 recette(s) en brouillon à valider ou annuler.');
    $this->entries->validate([$e[0]->id], $this->admin);

    // Les mois précédents doivent être clôturés d'abord.
    expect(fn () => $lock->close(2026, 5, $this->admin))->toThrow(ValidationException::class);
    foreach ([1, 2, 3, 4] as $m) {
        $lock->close(2026, $m, $this->admin);
    }
    Livewire::test(Regie\Cloture::class)->call('close', 5);
    $closure = MonthLock::closure(2026, 5);
    expect($closure)->not->toBeNull()->closing_balance->toEqual(1250.0)
        ->and($closure->snapshot['total_month'])->toEqual(250.0);

    expect(fn () => $this->entries->createDrafts('2026-05-21', [['rubric_id' => $this->souk->id, 'payment_mode' => 'especes', 'amount' => 10]], $this->admin))
        ->toThrow(ValidationException::class);
    expect(fn () => app(\App\Domain\Caisse\Services\CashierService::class)->validateMany([1], 'Q1', '2026-05-30', $this->admin))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->entries->cancel($e[0]->fresh(), $this->admin, 'Erreur'))->toThrow(ValidationException::class);

    // Réouverture motivée (administration).
    Livewire::test(Regie\Cloture::class)->call('askReopen', $closure->id)->set('reopenReason', 'Erreur de rubrique à corriger')->call('confirmReopen');
    expect(MonthLock::isClosed('2026-05-10'))->toBeFalse();
    $this->get(route('regie.imprimer.etat-mensuel', ['annee' => 2026, 'mois' => 5]))->assertOk();
});

test('remittances to the accountant, acknowledgement and dispatch to the Treasury', function () {
    $e = $this->entries->createDrafts('2026-06-01', [['rubric_id' => $this->souk->id, 'payment_mode' => 'especes', 'amount' => 800]], $this->admin);
    $this->entries->validate([$e[0]->id], $this->admin);
    $service = app(RemittanceService::class);
    expect(fn () => $service->create(['remitted_on' => '2026-06-02', 'period_from' => '2026-06-01', 'period_to' => '2026-06-02', 'amount' => 5000, 'accountant' => 'Percepteur', 'payment_mode' => 'especes', 'bank_reference' => '', 'notes' => ''], $this->admin))
        ->toThrow(ValidationException::class);

    Livewire::test(Regie\Versements::class)->call('openForm')->assertSet('form.amount', '800')
        ->set('form.amount', '1500')->set('form.remitted_on', '2026-06-02')->call('save')->assertHasNoErrors();
    $r = \App\Domain\Regie\Models\CashRemittance::firstOrFail();
    expect($r)->number->toBe('BVC202600001')->lines->toHaveCount(1)
        ->and($this->ledger->balanceAt('2026-06-02'))->toEqual(300.0);
    Livewire::test(Regie\Versements::class)->call('askAck', $r->id)->set('ackRef', 'Q-TGR-55')->call('confirmAck');
    expect($r->fresh()->status)->toBe('accuse');
    expect($this->ledger->treasuryOperations(2026)['total_acknowledged'])->toEqual(1500.0);

    Livewire::test(Regie\Envois::class)->call('openForm')->set('chosen', ['versement:BVC202600001'])->set('extra.0.label', 'Souches de quittances')->call('save');
    $d = \App\Domain\Regie\Models\TreasuryDispatch::firstOrFail();
    expect($d->items)->toHaveCount(2);
    Livewire::test(Regie\Envois::class)->call('askAck', $d->id)->set('ackName', 'M. Receveur')->call('confirmAck');
    expect($d->fresh()->acknowledged_by_name)->toBe('M. Receveur')
        ->and(collect($service->dispatchCandidates())->pluck('ref'))->not->toContain('BVC202600001');
    foreach (['versement' => $r, 'envoi' => $d] as $name => $model) {
        $this->get(route("regie.imprimer.$name", $model))->assertOk();
    }
});

test('cash report minutes compute the gap between the book and the count', function () {
    $e = $this->entries->createDrafts('2026-05-31', [['rubric_id' => $this->souk->id, 'payment_mode' => 'especes', 'amount' => 230]], $this->admin);
    $this->entries->validate([$e[0]->id], $this->admin);
    Livewire::test(Regie\PvArrete::class)->call('openForm')->assertSet('date', '2026-05-31')
        ->set('bills.200', '6')->set('bills.10', '2')->set('bills.5', '1')->call('save');
    $pv = CashReport::firstOrFail();
    expect($pv)->number->toBe('PVA202600001')->period->toBe(5)
        ->theoretical_balance->toEqual(1230.0)->counted_balance->toEqual(1225.0)->difference->toEqual(-5.0);
    $this->get(route('regie.imprimer.pv', $pv))->assertOk();
    $this->get(route('regie.imprimer.pv-vierge', 'remise'))->assertOk();
    Livewire::test(Regie\PvVerification::class)->call('openForm')->call('save')->assertHasErrors('people.verifier');
    expect(app(MinutesService::class)->missing())->toContain('PV de vérification de caisse du T1 2026');
});

test('ticket stock: TGR reserve, withdrawal, discharge to an agent, settlement into receipts, correction', function () {
    $value = StampValue::create(['type' => 'ticket', 'label' => 'Ticket souk 5 DH', 'unit_value' => 5, 'units_per_book' => 100, 'is_active' => true, 'rubric_id' => $this->souk->id, 'alert_threshold' => 50]);
    $agent = Employee::create(['last_name' => 'Idrissi', 'first_name' => 'Said', 'is_active' => true]);
    $stock = app(StockService::class);
    $stock->move($value->id, 'dotation', 1000, '2026-01-05', 'Dotation 2026', null, $this->admin);
    expect(fn () => $stock->move($value->id, 'retrait', 2000, '2026-06-01', null, null, $this->admin))->toThrow(ValidationException::class);
    $stock->move($value->id, 'retrait', 300, '2026-06-01', 'BR-1', null, $this->admin);

    Livewire::test(Regie\SaisieTickets::class)->call('openForm')->set('form.stamp_value_id', $value->id)->set('form.employee_id', $agent->id)
        ->set('form.place', 'Souk Had')->set('form.first_number', '1001')->set('form.last_number', '1200')->call('save')->assertHasNoErrors();
    $d = TicketDischarge::firstOrFail();
    expect($d->quantity)->toBe(200)->and($stock->state()[$value->id])->tgr->toBe(700)->regie->toBe(100)->with_agents->toBe(200);

    Livewire::test(Regie\SaisieTickets::class)->call('askSettle', $d->id)->set('returned', '20')->call('confirmSettle');
    $entry = CashEntry::where('source', 'decharge')->firstOrFail();
    expect($entry)->amount->toEqual(900.0)->status->toBe('valide')
        ->and($stock->state()[$value->id])->regie->toBe(120)->consumed->toBe(180);

    // Modifier la décharge : 30 rendus au lieu de 20 ➜ recette refaite (850 DH), l'ancienne annulée.
    Livewire::test(Regie\SaisieTickets::class)->call('openEdit', $d->id)->set('edit.returned', '30')->set('editReason', 'Erreur de comptage au retour')->call('saveEdit')->assertHasNoErrors();
    expect($entry->fresh()->isCancelled())->toBeTrue()
        ->and(CashEntry::active()->where('source', 'decharge')->sum('amount'))->toEqual(850.0)
        ->and($d->fresh()->corrections)->toHaveCount(1);

    $intel = $stock->intelligence()['rows'][0];
    expect($intel['average'])->toEqual(170.0)->and($intel['level'])->toBe('bas')->and($intel['order'])->toBe(380);
    $this->get(route('regie.imprimer.decharge', $d))->assertOk();
    $this->get(route('regie.export.intelligence'))->assertOk();
    expect(collect(app(ControlService::class)->issues())->pluck('title')->implode('|'))->not->toContain('Rupture');
});

test('printed documents get a registry number and a QR; the scanner tells whether a sheet is authentic', function () {
    $this->get(route('regie.imprimer.livre', ['annee' => 2026, 'mois' => 6]))->assertOk();
    $this->get(route('regie.imprimer.livre', ['annee' => 2026, 'mois' => 6]))->assertOk();
    $doc = PrintedDocument::firstOrFail();
    expect(PrintedDocument::count())->toBe(1)->and($doc->print_count)->toBe(2)->and($doc->number)->toBe('DOC202600001');

    $registry = app(DocumentRegistry::class);
    expect($registry->verify($doc->code())['status'])->toBe('valid')
        ->and($registry->verify($doc->number.'-ZZZZZZ')['status'])->toBe('forged')
        ->and($registry->verify('DOC209900001-ABCDEF')['status'])->toBe('unknown')
        ->and($registry->verify(route('regie.scanner', ['code' => $doc->code()]))['status'])->toBe('valid');

    Livewire::test(Regie\Scanner::class, ['code' => $doc->code()])->assertSee('Pièce authentique')
        ->set('scan', UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf'))->call('attach');
    expect($doc->fresh()->scan_path)->not->toBeNull();

    Livewire::test(Regie\Boites::class)->call('openForm')->set('form.label', 'Livres de caisse 2026')->call('save');
    Livewire::test(Regie\Documents::class)->set('selected', [$doc->id])->set('targetBox', (string) \App\Domain\Regie\Models\ArchiveBox::first()->id)->call('fileSelected');
    expect($doc->fresh()->archive_box_id)->not->toBeNull()->and(app(DocumentRegistry::class)->control())->scanned->toBe(1)->filed->toBe(1);
});

test('every page of the regie space opens, prints work, and rights are enforced', function () {
    $e = $this->entries->createDrafts('2026-06-12', [['rubric_id' => $this->souk->id, 'payment_mode' => 'especes', 'amount' => 75]], $this->admin);
    $this->entries->validate([$e[0]->id], $this->admin);
    $nav = new Navigation(require base_path('config/navigation.php'));
    $pages = $nav->pages()->filter(fn ($p) => str_starts_with($p['route'], 'regie.'));
    expect($pages)->toHaveCount(34);
    foreach ($pages as $p) {
        $this->get(route($p['route']))->assertOk();
    }
    $this->get('/regie')->assertRedirect(route('regie.tableau-de-bord'));
    foreach (['situation' => ['date' => '2026-06-12'], 'operations' => ['annee' => 2026], 'rapprochement' => ['annee' => 2026, 'mois' => 6], 'recap' => [],
        'rapport' => [], 'tableau' => ['annee' => 2026], 'rapport-annuel' => ['annee' => 2026], 'etat-stock' => [], 'decharge-vierge' => []] as $name => $q) {
        $this->get(route("regie.imprimer.$name", $q))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
    $this->get(route('regie.export.tableau', ['annee' => 2026]))->assertOk();

    $viewer = userWithRole('consultation');
    $this->actingAs($viewer);
    $this->get(route('regie.livre-caisse'))->assertOk();
    $this->get(route('regie.saisie'))->assertForbidden();
    Livewire::test(Regie\Brouillons::class)->call('validateAll')->assertForbidden();

    $cashier = userWithRole('regisseur');
    $this->actingAs($cashier);
    $this->get(route('regie.saisie'))->assertOk();
    Livewire::test(Regie\Cloture::class)->call('askReopen', 1)->assertForbidden();
});
