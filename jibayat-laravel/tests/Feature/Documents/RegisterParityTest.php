<?php

use App\Domain\Documents\Services\RegisterService;
use App\Domain\Import\SqliteImporter;
use App\Livewire\Documents;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
| Parité Flask ↔ Laravel pour les registres. tests/Fixtures/parity/reg.{db,json} sont produits par
| tools/parity/reg_parity.py qui exécute les VRAIES vues Flask (registre.index, registre_global, declarations_global).
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/reg.json')), true);
    expect((new SqliteImporter(base_path('tests/Fixtures/parity/reg.db')))->run()['ok'])->toBeTrue();
});

test('hub figures per tax (emitted, penalties, receipts, recovered, remaining, rate) match Flask registre.index', function () {
    $stats = app(RegisterService::class)->moduleStats();
    foreach ($this->expected['index']['modules'] as $key => $e) {
        $s = $stats[$key];
        expect($s['declarations'])->toBe($e['nb_declarations'], $key)->and($s['taxpayers'])->toBe($e['nb_redevables'])
            ->and($s['principal'])->toEqual((float) $e['principal_decl'])->and($s['penalties'])->toEqual((float) $e['penalites_decl'])
            ->and($s['emitted'])->toEqual((float) $e['montant_emis'])->and($s['receipts'])->toBe($e['nb_quittances'])
            ->and($s['paid'])->toEqual((float) $e['montant_paye'])->and($s['remaining'])->toEqual((float) $e['reste_du'])
            ->and($s['rate'])->toEqual((float) $e['taux_recouvrement']);
    }
});

test('the general ledger (filters by year, tax and status) matches Flask registre_global', function () {
    $service = app(RegisterService::class);
    foreach ($this->expected['global'] as $key => $e) {
        [$year, $module, $status] = explode('|', $key);
        $q = $service->paymentsQuery($year === 'all' ? null : $year, $module === 'all' ? null : $module, $status);
        $t = $service->paymentTotals($q);
        expect($t['n'])->toBe($e['n'], $key)->and($t['emitted'])->toEqual((float) $e['tot_emis'])->and($t['paid'])->toEqual((float) $e['tot_recouvre'])
            ->and($t['rate'])->toEqual((float) $e['taux'])
            ->and((clone $q)->orderByDesc('d.year')->orderByDesc('d.id')->pluck('d.number')->sort()->values()->all())->toBe(collect($e['numeros'])->sort()->values()->all());
    }
});

test('the declarations register totals match Flask declarations_global', function () {
    $service = app(RegisterService::class);
    foreach ($this->expected['declarations'] as $key => $e) {
        [$year, $module] = explode('|', $key);
        $t = $service->declarationTotals($service->declarationsQuery($year === 'all' ? null : $year, $module === 'all' ? null : $module));
        expect($t['n'])->toBe($e['n'], $key)->and($t['base'])->toEqual((float) $e['tot_base'])->and($t['principal'])->toEqual((float) $e['tot_principal'])
            ->and($t['penalties'])->toEqual((float) $e['tot_penalites'])->and($t['total'])->toEqual((float) $e['tot_total']);
    }
});

test('register pages, exports and prints open; cancelled pieces are listed with their reason', function () {
    $this->actingAs(userWithRole('administrateur'));
    $d = \App\Domain\Caisse\Models\TaxDeclaration::where('number', 'DCL202600002')->first();
    $d->cancel(auth()->user(), 'Erreur de superficie');

    $this->get(route('documents.registres'))->assertOk()->assertSee('Taxe sur les débits de boissons');
    Livewire::test(Documents\GrandLivre::class)->set('year', '')->assertSee('LOC-DECL2026005')->set('module', 'TNB')->assertDontSee('LOC-DECL2026005');
    Livewire::test(Documents\RegistreDeclarations::class)->assertSee('DCL-TDB2026003');
    Livewire::test(Documents\PiecesAnnulees::class)->assertSee('DCL202600002')->assertSee('Erreur de superficie')->assertSee('DCL-ODP2026006');

    $csv = $this->get(route('documents.export', ['type' => 'paiements']))->assertOk();
    expect($csv->streamedContent())->toContain('Taxe;Année')->toContain('LOC-DECL2026005');
    $this->get(route('documents.export', ['type' => 'declarations', 'annee' => 2025]))->assertOk();
    $this->get(route('documents.imprimer-grand-livre'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('documents.imprimer-pieces-annulees'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});
