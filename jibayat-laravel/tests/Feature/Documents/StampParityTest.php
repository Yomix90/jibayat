<?php

use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Models\StampPack;
use App\Domain\Documents\Services\StampService;
use App\Domain\Import\SqliteImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Parité Flask ↔ Laravel pour la régie des timbres. tests/Fixtures/parity/rg.{db,json} sont produits par
| tools/parity/rg_parity.py qui exécute les VRAIES routes Flask (ouvrir_paquet, versement_employe, creer_bordereau, index).
| Écart vérifié à part : le paquet P-003 (7 vignettes) donnait chez Flask des carnets invalides (début > fin).
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/rg.json')), true);
    expect((new SqliteImporter(base_path('tests/Fixtures/parity/rg.db')))->run()['ok'])->toBeTrue();
    $this->travelTo(CarbonImmutable::parse($this->expected['today'].' 10:00'));
    $this->user = userWithRole('regisseur');
    $service = app(StampService::class);
    foreach (StampPack::orderBy('id')->get() as $p) {
        $service->openPack($p);
    }
    $serviceId = \App\Domain\Documents\Models\Service::where('legacy_id', 1)->value('id');
    $employeeId = \App\Domain\Documents\Models\Employee::where('legacy_id', 1)->value('id');
    foreach (['P-001-C01', 'P-001-C02', 'P-002-C01'] as $n) {
        StampBook::where('number', $n)->update(['status' => 'affecte', 'service_id' => $serviceId, 'employee_id' => $employeeId, 'assigned_on' => '2026-04-01']);
    }
    foreach ($this->expected['versements'] as [$n, $amount, $date]) {
        $service->employeePayment(StampBook::where('number', $n)->first(), (float) $amount, $date, 'test', $this->user);
    }
    $service->createRemittance(StampBook::whereIn('number', ['P-001-C01', 'P-001-C02'])->pluck('id')->all(), '2026-06-10', '', $this->user);
});

test('opening packs splits them into ten books exactly like Flask (P-001, P-002)', function () {
    foreach ($this->expected['carnets'] as $e) {
        if (str_starts_with($e['numero_carnet'], 'P-003')) {
            continue;
        }
        $b = StampBook::where('number', $e['numero_carnet'])->firstOrFail();
        expect([(int) $b->first_number, (int) $b->last_number, $b->status])->toBe([$e['num_premier'], $e['num_dernier'], $e['statut']], $e['numero_carnet'])
            ->and((float) $b->amount_paid)->toEqual((float) $e['montant_verse'])->and((float) $b->difference)->toEqual((float) $e['ecart'])
            ->and($b->stamp_remittance_id !== null)->toBe($e['bordereau_id'] !== null);
    }
    expect(StampPack::pluck('status')->all())->toBe(['ouvert', 'ouvert', 'ouvert']);
});

test('a pack with fewer than ten stamps gives one valid book per stamp (Flask produced invalid books)', function () {
    $books = StampBook::where('number', 'like', 'P-003-%')->orderBy('number')->get();
    expect($books)->toHaveCount(7)->and($books->every(fn ($b) => $b->first_number <= $b->last_number))->toBeTrue()
        ->and((int) $books->last()->last_number)->toBe(7);
});

test('remittance number and total, and the dashboard figures match Flask', function () {
    $r = \App\Domain\Documents\Models\StampRemittance::first();
    $e = $this->expected['bordereaux'][0];
    expect($r->number)->toBe($e['numero'])->and($r->remitted_on->toDateString())->toBe($e['date_versement'])->and((float) $r->total_amount)->toEqual((float) $e['montant_total']);

    $s = app(StampService::class)->stats(2026);
    $exp = $this->expected['stats'];
    expect($s['total_paquets'])->toBe($exp['total_paquets'])->and($s['affectes'])->toBe($exp['affectes'])->and($s['consommes'])->toBe($exp['consommes'])
        ->and($s['total_verse'])->toEqual((float) $exp['total_verse'])
        ->and($s['monthly'])->toEqual(array_map('floatval', $this->expected['mois_verse']))
        ->and($s['total_carnets'])->toBe($exp['total_carnets'] - 10 + 7)        // P-003 : 7 carnets au lieu de 10
        ->and($s['en_stock'])->toBe($exp['en_stock'] - 10 + 7);
});
