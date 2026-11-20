<?php

use App\Domain\Boissons\Actions\DeclareBeverageQuarters;
use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Import\SqliteImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/*
| Parité Flask ↔ Laravel pour les débits de boissons.
| tests/Fixtures/parity/tdb.{db,json} sont produits par tools/parity/tdb_parity.py qui exécute les VRAIES fonctions
| de Flask (modules/tdb.py, y compris la route tdb_declarer) à date figée.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/tdb.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/tdb.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité TDB');
    $this->travelTo(CarbonImmutable::parse($this->expected['today'].' 10:00'));
    $this->outlet = fn ($legacy) => BeverageOutlet::where('legacy_id', $legacy)->firstOrFail();
});

test('legal deadlines, rates by establishment type, unpaid quarters and period labels match Flask', function () {
    foreach ($this->expected['echeances'] as $key => $date) {
        [$y, $q] = array_map('intval', explode('_', $key));
        expect(BeverageCalculator::deadline($y, $q))->toBe($date, "échéance {$key}");
    }
    $calc = app(BeverageCalculator::class);
    foreach ($this->expected['etablissements'] as $legacy => $e) {
        $outlet = ($this->outlet)($legacy);
        expect($calc->rateFor($outlet->type))->toEqual((float) $e['taux'], "taux établ. {$legacy}");
        $unpaid = array_map(fn ($q) => ['annee' => $q['annee'], 'trimestre' => $q['trimestre'], 'echeance' => $q['echeance']], $calc->unpaidQuarters($outlet));
        expect($unpaid)->toBe($e['non_payes'], "trimestres non payés établ. {$legacy}")
            ->and(BeverageCalculator::periodLabel($unpaid))->toBe($e['periode']['texte_fr'])
            ->and(BeverageCalculator::periodLabel($unpaid, true))->toBe($e['periode']['texte_ar']);
    }
});

test('list estimates (last declared turnover × rate) match Flask _get_tdb_items_data', function () {
    $calc = app(BeverageCalculator::class);
    foreach ($this->expected['liste'] as $legacy => $e) {
        $s = $calc->situation(($this->outlet)($legacy));
        expect($s['last_base'])->toEqual((float) $e['dernier_ca_declare'])
            ->and($s['nb_non_declares'])->toBe($e['nb_non_declares'])->and($s['nb_emises'])->toBe($e['nb_emises'])
            ->and($s['annees_non_payees'])->toBe($e['annees_non_payees'])
            ->and($s['montant_estime_retard'])->toEqual((float) $e['montant_estime_retard'], "retard établ. {$legacy}")
            ->and($s['montant_en_attente'])->toEqual((float) $e['montant_en_attente'])
            ->and($s['total_impaye'])->toEqual((float) $e['total_impaye']);
    }
});

test('declaring quarters reproduces Flask tdb_declarer exactly (Flask T4 deadline mode)', function () {
    config(['jibayat.boissons.t4_deadline_next_year' => false]);
    $agent = userWithRole('collecteur');
    $checked = 0;
    foreach ($this->expected['scenarios'] as $sc) {
        DB::beginTransaction();
        $outlet = ($this->outlet)($sc['input']['id']);
        app(DeclareBeverageQuarters::class)->handle($outlet, $sc['input']['bases'], $agent,
            ['declared_on' => $sc['input']['date'], 'slip_number' => $sc['input']['bv']]);
        $got = TaxDeclaration::where('taxable_type', 'beverage_outlet')->where('taxable_id', $outlet->id)->whereNull('legacy_id')
            ->orderBy('year')->orderBy('quarter')->get();
        expect($got)->toHaveCount(count($sc['declarations']), "scénario établ. {$sc['input']['id']} au {$sc['input']['date']}");
        foreach ($sc['declarations'] as $i => $e) {
            $d = $got[$i];
            $label = "établ. {$sc['input']['id']} T{$e['trimestre']} {$e['annee']} au {$sc['input']['date']}";
            expect([$d->year, $d->quarter])->toBe([$e['annee'], $e['trimestre']], $label)
                ->and((float) $d->base_amount)->toEqual((float) $e['base_calcul'], "{$label} : base")
                ->and((float) $d->rate)->toEqual((float) $e['taux'], "{$label} : taux")
                ->and((float) $d->principal)->toEqual((float) $e['montant_principal'], "{$label} : principal")
                ->and((float) $d->late_penalty)->toEqual((float) $e['penalite_retard'], "{$label} : pénalité")
                ->and((float) $d->surcharge)->toEqual((float) $e['majoration'], "{$label} : majoration")
                ->and((float) $d->non_filing_fine)->toEqual((float) $e['amende_non_declaration'], "{$label} : amende")
                ->and((float) $d->total)->toEqual((float) $e['montant_total'], "{$label} : total")
                ->and($d->status)->toBe($e['statut'], "{$label} : statut")
                ->and($d->due_on->toDateString())->toBe($e['date_echeance'], "{$label} : échéance");
            $slip = PaymentSlip::where('tax_declaration_id', $d->id)->first();
            if ($e['bulletin'] && $sc['input']['bv'] !== '') {
                expect($slip)->not->toBeNull()->and($slip->slip_number)->toBe($e['bulletin']['numero_bulletin'])
                    ->and((float) $slip->amount)->toEqual((float) $e['bulletin']['montant'])->and($slip->status)->toBe('en_attente');
            } elseif ($e['statut'] === 'sous_seuil') {
                expect($slip)->toBeNull();
            }
            $checked++;
        }
        DB::rollBack();
    }
    expect($checked)->toBe(15);
});

test('with the legal T4 deadline (default), a T4 declared before 31 January N+1 has no late penalty', function () {
    $outlet = ($this->outlet)(2);
    $r = app(DeclareBeverageQuarters::class)->handle($outlet, ['2025_4' => 9100.25], userWithRole('collecteur'), ['declared_on' => '2026-01-15']);
    expect($r['declarations'][0])->due_on->toDateString()->toBe('2026-01-31')
        ->late_penalty->toBe('0.00')->surcharge->toBe('0.00')->total->toBe('682.52');
});
