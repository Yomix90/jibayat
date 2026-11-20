<?php

use App\Domain\Documents\Models\IssueConfig;
use App\Domain\Documents\Services\AmountInWords;
use App\Domain\Documents\Services\GirStatementParser;
use App\Domain\Documents\Services\IssueSlipService;
use App\Domain\Documents\Services\TgrStatementParser;

/*
| Parité Flask ↔ Laravel pour le Trésor. tests/Fixtures/parity/tresor.json et les fichiers Excel d'essai sont produits par
| tools/parity/tresor_parity.py qui exécute les VRAIES fonctions Flask (parse_releve, chiffre_en_lettre,
| parse_bordereau_versement, regenerer_annee).
*/

beforeEach(function () {
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/tresor.json')), true);
    $this->dir = base_path('tests/Fixtures/parity');
});

test('the TGR statement parser reads the same operations and totals as Flask parse_releve', function () {
    $r = (new TgrStatementParser)->parse($this->dir.'/tgr_releve.xlsx');
    $e = $this->expected['tgr'];
    expect($r['operations'])->toHaveCount(count($e['operations']));
    foreach ($e['operations'] as $i => $op) {
        foreach ($op as $k => $v) {
            is_float($v) || is_int($v)
                ? expect((float) $r['operations'][$i][$k])->toEqual((float) $v, "$i.$k")
                : expect($r['operations'][$i][$k])->toBe($v, "$i.$k");
        }
    }
    foreach (['commune', 'periode_debut', 'periode_fin', 'total_principal', 'total_majoration', 'total_penalites', 'total_astreintes', 'total_general'] as $k) {
        expect($r['meta'][$k])->toEqual($e['meta'][$k], $k);
    }
    // Écart documenté : Flask lisait « None » pour le libellé « Régie » (valeur dans la cellule suivante) ; Laravel lit la valeur.
    expect($r['meta']['regie'])->toBe('Régie des recettes');
});

test('amounts in words match Flask chiffre_en_lettre', function () {
    foreach ($this->expected['lettres'] as $amount => $words) {
        expect(AmountInWords::dirhams((float) $amount))->toBe($words, (string) $amount);
    }
});

test('the GIR slip parser matches Flask parse_bordereau_versement', function () {
    foreach ($this->expected['gir'] as $m => $e) {
        $r = (new GirStatementParser)->parse(sprintf('%s/gir_bordereau_%02d.xlsx', $this->dir, $m));
        expect($r['mois'])->toBe($e['mois'])->and($r['annee'])->toBe($e['annee'])->and($r['total_general'])->toEqual((float) $e['total_general'])
            ->and(count($r['lignes']))->toBe(count($e['lignes']));
        foreach ($e['lignes'] as $i => $l) {
            expect($r['lignes'][$i]['code_budgetaire'])->toBe($l['code_budgetaire'])->and($r['lignes'][$i]['nature_recette'])->toBe($l['nature_recette'])
                ->and($r['lignes'][$i]['montant'])->toEqual((float) $l['montant']);
        }
    }
});

test('issue slips (sequential numbers, carry-forward per rubric, 1st and 2nd parts) match Flask regenerer_annee', function () {
    $user = userWithRole('administrateur');
    $service = app(IssueSlipService::class);
    IssueConfig::create(['year' => 2026, 'first_part_mode' => 'auto', 'second_part_mode' => 'auto']);
    foreach ([1, 2, 3] as $m) {
        $parsed = (new GirStatementParser)->parse(sprintf('%s/gir_bordereau_%02d.xlsx', $this->dir, $m));
        $service->import($parsed, $m, 2026, "gir_bordereau_0$m.xlsx", false, null, $user);
    }
    $slips = $service->regenerateYear(2026);
    expect($slips)->toHaveCount(count($this->expected['emission']));
    foreach ($this->expected['emission'] as $i => $e) {
        foreach ($e as $k => $v) {
            is_string($v) || is_null($v) ? expect($slips[$i][$k])->toBe($v, "$i.$k") : expect((float) $slips[$i][$k])->toEqual((float) $v, "$i.$k");
        }
    }
});
