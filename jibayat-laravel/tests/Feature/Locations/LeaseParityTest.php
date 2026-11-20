<?php

use App\Domain\Import\SqliteImporter;
use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Services\LeaseCalculator;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Parité Flask ↔ Laravel pour les loyers.
| tests/Fixtures/parity/locations.{db,json} sont produits par tools/parity/locations_parity.py,
| qui exécute les VRAIES fonctions Python de Flask (modules/location.py) à la date figée du JSON.
| On importe la même base SQLite avec la commande d'import, puis on compare chaque résultat.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/locations.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/locations.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité');
    $this->today = CarbonImmutable::parse($this->expected['today']);
    $this->calc = app(LeaseCalculator::class);
});

test('Python rounding is reproduced exactly', function () {
    expect(PyMath::round(2.675, 2))->toBe(2.67)
        ->and(PyMath::round(0.125, 2))->toBe(0.12)
        ->and(PyMath::round(0.375, 2))->toBe(0.38)
        ->and(PyMath::round(123456.785, 2))->toBe(123456.79)
        ->and(PyMath::ceilCents(945.721))->toBe(945.73);
});

test('monthly rent matches Flask _calculer_loyer for every lease', function () {
    foreach ($this->expected['leases'] as $legacyId => $exp) {
        $lease = Lease::where('legacy_id', $legacyId)->firstOrFail();
        expect($this->calc->monthlyRent($lease))->toEqual((float) $exp['loyer_mensuel'], "bail {$legacyId}");
    }
});

test('unpaid months, tariff per month, source and pending flag match Flask _calculer_mois_non_payes', function () {
    foreach ($this->expected['leases'] as $legacyId => $exp) {
        $lease = Lease::where('legacy_id', $legacyId)->firstOrFail();
        $months = $this->calc->unpaidMonths($lease, $this->today);

        expect(count($months))->toBe(count($exp['mois_non_payes']), "nombre de mois, bail {$legacyId}");
        foreach ($exp['mois_non_payes'] as $i => $m) {
            expect($months[$i]['mois'])->toBe($m['mois'], "bail {$legacyId}")
                ->and($months[$i]['label'])->toBe($m['label'])
                ->and($months[$i]['tarif'])->toEqual((float) $m['tarif'], "tarif {$m['mois']}, bail {$legacyId}")
                ->and($months[$i]['tarif_source'])->toBe($m['tarif_source'], "source {$m['mois']}, bail {$legacyId}")
                ->and($months[$i]['est_emis'])->toBe($m['est_emis'], "en attente {$m['mois']}, bail {$legacyId}");
        }
        expect(LeaseCalculator::total($months))->toEqual((float) $exp['total_du'], "total dû, bail {$legacyId}");
    }
});

test('grouping by tariff matches Flask _grouper_par_tarif', function () {
    foreach ($this->expected['leases'] as $legacyId => $exp) {
        $lease = Lease::where('legacy_id', $legacyId)->firstOrFail();
        $groups = $this->calc->groupByTariff($this->calc->unpaidMonths($lease, $this->today));

        expect(count($groups))->toBe(count($exp['groupes']), "bail {$legacyId}");
        foreach ($exp['groupes'] as $i => $g) {
            expect($groups[$i])->toMatchArray([
                'tarif' => (float) $g['tarif'], 'nb_mois' => $g['nb_mois'], 'montant' => (float) $g['montant'],
                'mois_debut' => $g['mois_debut'], 'mois_fin' => $g['mois_fin'],
            ]);
        }
    }
});
