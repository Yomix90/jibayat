<?php

use App\Domain\Contribuables\Services\TaxpayerService;
use App\Domain\Import\SqliteImporter;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Parité Flask ↔ Laravel pour les contribuables. tests/Fixtures/parity/ctb.{db,json} sont produits par
| tools/parity/ctb_parity.py qui exécute les VRAIES vues Flask (contribuables, ctb_detail, ctb_avis_non_paiement).
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/ctb.json')), true);
    expect((new SqliteImporter(base_path('tests/Fixtures/parity/ctb.db')))->run()['ok'])->toBeTrue();
    $this->service = app(TaxpayerService::class);
    $this->byLegacy = fn (int $id) => Taxpayer::where('legacy_id', $id)->firstOrFail();
});

test('the directory (search, tax filter, unpaid amounts, taxes) matches Flask contribuables()', function () {
    foreach ($this->expected['list'] as $key => $e) {
        [$q, $module] = explode('|', $key);
        $r = $this->service->list($q, $module);
        expect($r['total_unpaid'])->toEqual((float) $e['total'], $key)
            ->and(array_map(fn ($t) => ['numero' => $t->number, 'impaye' => $t->unpaid, 'modules' => array_map(fn ($m) => [$m['code'], $m['nb']], $t->modules)], $r['items']))
            ->toEqual(array_map(fn ($i) => ['numero' => $i['numero'], 'impaye' => (float) $i['impaye'], 'modules' => $i['modules']], $e['items']), $key);
    }
});

test('the taxpayer file and the global non-payment notice match Flask ctb_detail and ctb_avis_non_paiement', function () {
    foreach ($this->expected['detail'] as $id => $e) {
        $t = ($this->byLegacy)((int) $id);
        $d = $this->service->detail($t);
        expect(array_map(fn ($m) => [$m['code'], $m['nb']], $d['modules']))->toBe($e['modules'], "detail $id")
            ->and(array_map(fn ($u) => $u['montant'], $d['unpaid']))->toEqual(array_map('floatval', $e['impaye']))
            ->and($d['total_unpaid'])->toEqual((float) $e['total_impaye'])->and($d['total_collected'])->toEqual((float) $e['total_encaisse'])
            ->and($d['declarations']->pluck('number')->sort()->values()->all())->toBe(collect($e['declarations'])->sort()->values()->all())
            ->and($d['slips']->pluck('slip_number')->sort()->values()->all())->toBe(collect($e['bulletins'])->sort()->values()->all());

        $a = $this->service->notice($t);
        $ea = $this->expected['avis'][$id];
        expect($a['total'])->toEqual((float) $ea['total'], "avis $id")->and($a['periode']['fr'])->toBe($ea['periode_fr'])->and($a['periode']['ar'])->toBe($ea['periode_ar'])
            ->and($a['number'])->toBe($ea['numero'])
            ->and(array_map(fn ($g) => ['total' => $g['total'], 'periode_fr' => $g['periode']['fr'], 'n' => count($g['lignes'])], $a['groups']))
            ->toEqual(array_map(fn ($g) => ['total' => (float) $g['total'], 'periode_fr' => $g['periode_fr'], 'n' => $g['n']], $ea['groups']));
    }
});
