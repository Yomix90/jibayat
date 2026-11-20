<?php

use App\Domain\Caisse\Services\Penalties;
use App\Domain\Import\SqliteImporter;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Parité Flask ↔ Laravel pour la TNB.
| tests/Fixtures/parity/tnb.{db,json} sont produits par tools/parity/tnb_parity.py qui exécute les VRAIES
| fonctions de Flask (modules/tnb.py, modules/helpers.py) à date figée. La base est importée avec
| jibayat:import-sqlite puis chaque résultat est comparé.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/tnb.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/tnb.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité TNB');
    $this->today = CarbonImmutable::parse($this->expected['today']);
    $this->calc = app(TnbCalculator::class);
});

test('rounding up to the cent matches Flask majore_centimes', function () {
    foreach ($this->expected['majore'] as $case) {
        expect(PyMath::ceilCents((float) $case['valeur']))->toEqual((float) $case['resultat'], "majore({$case['valeur']})");
    }
});

test('late penalty and surcharge match Flask calculer_penalites', function () {
    $penalties = app(Penalties::class);
    foreach ($this->expected['penalites'] as $c) {
        [$pen, $maj] = $penalties->compute((float) $c['montant'], $c['echeance'], $c['paiement'], $c['module']);
        expect($pen)->toEqual((float) $c['penalite'], "pénalité {$c['montant']} {$c['echeance']}→{$c['paiement']}")
            ->and($maj)->toEqual((float) $c['majoration'], "majoration {$c['montant']} {$c['echeance']}→{$c['paiement']}");
    }
});

test('due years and exempt years match Flask _calcul_annees_redevables / _calcul_exoneration', function () {
    foreach ($this->expected['terrains'] as $legacyId => $exp) {
        $parcel = Parcel::where('legacy_id', $legacyId)->firstOrFail();
        expect($this->calc->dueYears($parcel, $this->today))->toBe($exp['annees_redevables'], "terrain {$legacyId}");

        $permits = $this->calc->constructionPermits($parcel);
        $exempt = array_values(array_filter(range(2020, 2026), fn ($y) => TnbCalculator::isExempt($permits, $y)));
        expect($exempt)->toBe($exp['exonerees'], "exonérations terrain {$legacyId}");
    }
});

test('yearly tax (rate, principal, penalty, surcharge, fine, total) matches Flask _compute_tarifs_annee at four dates', function () {
    $checked = 0;
    foreach ($this->expected['terrains'] as $legacyId => $exp) {
        $parcel = Parcel::where('legacy_id', $legacyId)->firstOrFail();
        foreach ($exp['calculs'] as $date => $years) {
            foreach ($years as $year => $e) {
                $r = $this->calc->computeYear((string) $parcel->zone, (float) $parcel->area, (int) $year, $date);
                foreach (['taux', 'principal', 'penalite', 'majoration', 'amende', 'total'] as $k) {
                    expect($r[$k])->toEqual((float) $e[$k], "terrain {$legacyId}, année {$year}, au {$date} : {$k}");
                }
                $checked++;
            }
        }
    }
    expect($checked)->toBe(8 * 4 * 7);
});
