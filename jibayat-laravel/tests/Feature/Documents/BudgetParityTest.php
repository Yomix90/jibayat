<?php

use App\Domain\Documents\Services\BudgetService;
use App\Domain\Import\SqliteImporter;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Parité Flask ↔ Laravel pour le budget. tests/Fixtures/parity/budget.{db,json} et budget_import.xlsx sont produits par
| tools/parity/budget_parity.py qui exécute les VRAIES vues Flask (regie_budget, regie_budget_synchroniser,
| regie_budget_importer, regie_budget_ligne_ajouter).
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/budget.json')), true);
    expect((new SqliteImporter(base_path('tests/Fixtures/parity/budget.db')))->run()['ok'])->toBeTrue();
    $this->user = userWithRole('administrateur');
    $this->service = app(BudgetService::class);
});

function expectBudget(array $actual, array $expected, string $step): void
{
    foreach (['total_n1', 'total_propose', 'total_realise', 'total_ecart', 'total_taux'] as $k) {
        expect($actual['grand_total'][$k])->toEqualWithDelta((float) $expected['grand_total'][$k], 0.001, "$step total.$k");
    }
    expect(array_column($actual['domaines'], 'code'))->toBe(array_column($expected['domaines'], 'code'), $step);
    foreach ($expected['domaines'] as $i => $ed) {
        $ad = $actual['domaines'][$i];
        foreach (['total_n1', 'total_propose', 'total_realise', 'total_ecart', 'total_taux'] as $k) {
            expect($ad[$k])->toEqualWithDelta((float) $ed[$k], 0.001, "$step {$ed['code']}.$k");
        }
        expect(collect($ad['lignes'])->pluck('chapter')->all())->toBe(array_column($ed['lignes'], 'chapitre'), "$step {$ed['code']} ordre");
        foreach ($ed['lignes'] as $j => $el) {
            $al = $ad['lignes'][$j];
            $ctx = "$step {$el['chapitre']}";
            expect($al->gir_code ?: null)->toBe($el['code_gir'] ?: null, "$ctx gir")->and($al->label)->toBe($el['libelle'], "$ctx libelle")
                ->and($al->label_fr)->toBe($el['libelle_fr'], "$ctx fr")->and($al->label_ar)->toBe($el['libelle_ar'], "$ctx ar")
                ->and($al->domain)->toBe($el['domaine'])->and($al->module)->toBe($el['module_associe'], "$ctx module")
                ->and((float) $al->previous_actual)->toEqual((float) $el['montant_realise_n1'], "$ctx n1")
                ->and((float) $al->proposed)->toEqual((float) $el['montant_propose'], "$ctx propose")
                ->and((float) $al->actual)->toEqual((float) $el['montant_realise'], "$ctx realise")
                ->and($al->rate)->toEqual((float) $el['taux'], "$ctx taux")->and($al->gap)->toEqualWithDelta((float) $el['ecart'], 0.001, "$ctx ecart");
        }
    }
}

test('budget lifecycle (nomenclature, synchronisation, Excel import, new chapter, 2022 seed) matches Flask', function () {
    $this->service->ensureYear(2026);
    expectBudget($this->service->overview(2026), $this->expected['init_2026'], 'init');

    $this->service->sync(2026, $this->user);
    expectBudget($this->service->overview(2026), $this->expected['sync_2026'], 'sync');

    expect($this->service->import(base_path('tests/Fixtures/parity/budget_import.xlsx'), 2026, $this->user))->toBe(5);
    expectBudget($this->service->overview(2026), $this->expected['import_2026'], 'import');

    $this->service->addLine(2026, ['chapter' => '40.99.10.11', 'label_fr' => 'Nouvelle recette', 'label_ar' => 'مدخول جديد', 'domain' => 'ECONOMIQUE',
        'proposed' => '1500', 'actual' => '700', 'previous_actual' => '100', 'gir_code' => ''], $this->user);
    expectBudget($this->service->overview(2026), $this->expected['add_2026'], 'add');

    $this->service->ensureYear(2022);
    expectBudget($this->service->overview(2022), $this->expected['seed_2022'], 'seed 2022');
    expect($this->service->years(2026))->toBe($this->expected['seed_2022']['annees']);
});

test('chapter codes are normalised like Flask _normalize_budget_chapitre', function () {
    foreach (['10103031' => '10.10.30.31', '1010303' => '10.10.30.30', '10103031.0' => '10.10.30.31', '10,40,10,14' => '10.40.10.14', '10.4010.14' => '10.40.10.14',
        '10.10.10.12' => '10.10.30.31', '60.20.10' => '23.50.10.10.11', '50.10.10' => '50.10.10'] as $in => $out) {
        expect(BudgetService::normalizeChapter((string) $in))->toBe($out, (string) $in);
    }
});
