<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Import\SqliteImporter;
use App\Domain\Souk\Actions\CollectSoukMonths;
use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Services\SoukCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/*
| Parité Flask ↔ Laravel pour l'affermage des souks.
| tests/Fixtures/parity/sou.{db,json} sont produits par tools/parity/sou_parity.py (vraies fonctions de modules/souks.py,
| y compris la route sou_payer) à date figée.
| Écart volontaire vérifié à part : le mois de début d'un contrat commencé en cours de mois (contrat 3, 2025-02) prend
| la redevance initiale, et non celle de la dernière période comme dans Flask.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/sou.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/sou.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité souk');
    $this->travelTo(CarbonImmutable::parse($this->expected['today'].' 10:00'));
    $this->contract = fn ($legacy) => SoukContract::where('legacy_id', $legacy)->firstOrFail();
    $this->flaskStartMonthBug = fn ($legacy, $mois) => (string) $legacy === '3' && $mois === '2025-02';
});

test('renewal periods (duration, revaluation, leap years) match Flask', function () {
    $calc = app(SoukCalculator::class);
    foreach ($this->expected['contrats'] as $legacy => $e) {
        $c = ($this->contract)($legacy);
        expect(SoukCalculator::monthlyFee($c))->toEqual((float) $e['rev_mens'], "redevance contrat {$legacy}");
        $periods = array_map(fn ($p) => [$p['renouvellement'], $p['date_debut'], $p['date_fin'], (float) $p['redevance_mensuelle']], $calc->periods($c));
        $exp = array_map(fn ($p) => [$p['renouvellement'], $p['date_debut'], $p['date_fin'], (float) $p['redevance_mensuelle']], $e['periodes']);
        expect($periods)->toEqual($exp, "périodes contrat {$legacy}");
    }
});

test('unpaid months, fees and grouping match Flask', function () {
    $calc = app(SoukCalculator::class);
    foreach ($this->expected['contrats'] as $legacy => $e) {
        $months = $calc->unpaidMonths(($this->contract)($legacy));
        expect(array_column($months, 'mois'))->toBe(array_column($e['mois'], 'mois'), "mois contrat {$legacy}");
        foreach ($e['mois'] as $i => $m) {
            if (($this->flaskStartMonthBug)($legacy, $m['mois'])) {
                expect($months[$i]['redevance'])->toBe(750.0);   // Flask : 787,5 (dernière période)
                continue;
            }
            expect($months[$i]['redevance'])->toEqual((float) $m['redevance'], "contrat {$legacy} mois {$m['mois']}")
                ->and($months[$i]['est_emis'])->toBe($m['est_emis']);
        }
        if ((string) $legacy !== '3') {
            $groups = array_map(fn ($g) => [$g['redevance_mensuelle'], $g['nb_mois'], $g['montant'], $g['mois_debut'], $g['mois_fin']], SoukCalculator::groups($months));
            expect($groups)->toEqual(array_map(fn ($g) => [(float) $g['redevance_mensuelle'], $g['nb_mois'], (float) $g['montant'], $g['mois_debut'], $g['mois_fin']], $e['groupes']));
        }
    }
});

test('list figures match Flask _get_sou_items_data', function () {
    $calc = app(SoukCalculator::class);
    foreach ($this->expected['liste'] as $legacy => $e) {
        if ((string) $legacy === '3') {
            continue;   // écart du mois de début (−37,50 DH)
        }
        $s = $calc->situation(($this->contract)($legacy));
        foreach (['total_impaye', 'total_non_declare', 'total_en_attente'] as $k) {
            expect($s[$k])->toEqual((float) $e[$k], "{$k} contrat {$legacy}");
        }
        expect($s['nb_non_declares'])->toBe($e['nb_non_declares'])->and($s['nb_emises'])->toBe($e['nb_emises'])->and($s['nb_mois_impaye'])->toBe($e['nb_mois_impaye']);
    }
    expect(app(SoukCalculator::class)->situation(($this->contract)('3'))['total_impaye'])->toBe(12900.0);   // Flask 12 937,50
});

test('collecting months reproduces Flask sou_payer (amount, year, months, slip)', function () {
    $agent = userWithRole('collecteur');
    foreach ($this->expected['scenarios'] as $sc) {
        DB::beginTransaction();
        $c = ($this->contract)($sc['input']['id']);
        $d = app(CollectSoukMonths::class)->handle($c, $sc['input']['mois'], $agent, ['slip_number' => $sc['input']['bv']]);
        $e = $sc['declaration'];
        $expectedTotal = (float) $e['montant_total'] - ($sc['input']['id'] === 3 ? 37.5 : 0);   // écart du mois de début
        expect((float) $d->total)->toEqual($expectedTotal, "contrat {$sc['input']['id']}")
            ->and($d->year)->toBe($e['annee'])->and((float) $d->base_amount)->toEqual((float) $e['base_calcul'])
            ->and($d->notes)->toBe($e['notes'])
            ->and($d->periods()->pluck('period')->all())->toBe($sc['input']['mois'])
            ->and(PaymentSlip::where('tax_declaration_id', $d->id)->first())->slip_number->toBe($e['bulletin']['numero_bulletin'])->status->toBe('en_attente');
        DB::rollBack();
    }
});

test('months must be paid in chronological order, and emitted months are not offered again', function () {
    $c = ($this->contract)('1');   // 2023-06 émis
    $agent = userWithRole('collecteur');
    expect(fn () => app(CollectSoukMonths::class)->handle($c, ['2023-09'], $agent))->toThrow(\Illuminate\Validation\ValidationException::class);
    $d = app(CollectSoukMonths::class)->handle($c, ['2023-07', '2023-08'], $agent);
    expect((float) $d->total)->toBe(2000.0)->and(TaxDeclaration::whereNull('legacy_id')->count())->toBe(1);
});
