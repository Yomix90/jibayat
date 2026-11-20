<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\DomainePublic\Actions\DeclareOccupationPeriods;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\DomainePublic\Services\OccupationCalculator;
use App\Domain\Import\SqliteImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/*
| Parité Flask ↔ Laravel pour l'occupation du domaine public.
| tests/Fixtures/parity/odp.{db,json} sont produits par tools/parity/odp_parity.py (vraies fonctions de
| modules/occupation.py, y compris la route odp_declarer) à date figée.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/odp.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/odp.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité ODP');
    $this->travelTo(CarbonImmutable::parse($this->expected['today'].' 10:00'));
    $this->occ = fn ($legacy) => PublicOccupation::where('legacy_id', $legacy)->firstOrFail();
});

test('quarterly and yearly bases (area × tariff, by unit) match Flask _calculer_bases_tarif', function () {
    foreach ($this->expected['bases'] as $b) {
        [$q, $y] = OccupationCalculator::bases((float) $b['superficie'], (object) ['value' => $b['valeur'], 'unit' => $b['unite']]);
        expect($q)->toEqual((float) $b['trim'], "{$b['superficie']} m² × {$b['valeur']} {$b['unite']} : trimestre")
            ->and($y)->toEqual((float) $b['an'], "{$b['superficie']} m² × {$b['valeur']} {$b['unite']} : année");
    }
});

test('list figures match Flask _get_odp_items_data', function () {
    $calc = app(OccupationCalculator::class);
    foreach ($this->expected['liste'] as $legacy => $e) {
        $s = $calc->situation(($this->occ)($legacy));
        expect($s['trim_base'])->toEqual((float) $e['trim_base'], "occupation {$legacy}")->and($s['an_base'])->toEqual((float) $e['an_base'])
            ->and($s['est_expire'])->toBe($e['est_expire'])
            ->and($s['nb_non_declares'])->toBe($e['nb_non_declares'])->and($s['nb_emises'])->toBe($e['nb_emises'])
            ->and($s['montant_impaye'])->toEqual((float) $e['montant_impaye'])->and($s['montant_en_attente'])->toEqual((float) $e['montant_en_attente']);
    }
});

test('liquidation (quarters and whole year, no penalty) reproduces Flask odp_declarer', function () {
    $agent = userWithRole('collecteur');
    $checked = 0;
    foreach ($this->expected['scenarios'] as $sc) {
        DB::beginTransaction();
        $o = ($this->occ)($sc['input']['id']);
        app(DeclareOccupationPeriods::class)->handle($o, $sc['input']['trims'], $agent, ['declared_on' => $sc['input']['date'], 'slip_number' => $sc['input']['bv']]);
        $got = TaxDeclaration::where('taxable_type', 'public_occupation')->where('taxable_id', $o->id)->whereNull('legacy_id')->orderBy('year')->orderBy('quarter')->get();
        expect($got)->toHaveCount(count($sc['declarations']));
        foreach ($sc['declarations'] as $i => $e) {
            $d = $got[$i];
            $label = "occupation {$sc['input']['id']} {$e['annee']}_{$e['trimestre']}";
            expect([$d->year, $d->quarter])->toBe([$e['annee'], $e['trimestre']], $label)
                ->and((float) $d->base_amount)->toEqual((float) $e['base_calcul'])->and((float) $d->rate)->toEqual((float) $e['taux'])
                ->and((float) $d->total)->toEqual((float) $e['montant_total'], "{$label} : total")
                ->and((float) $d->late_penalty)->toBe(0.0)->and((float) $d->surcharge)->toBe(0.0)
                ->and($d->status)->toBe($e['statut'])->and($d->due_on->toDateString())->toBe($e['date_echeance']);
            if ($e['bulletin']) {
                expect(PaymentSlip::where('tax_declaration_id', $d->id)->value('slip_number'))->toBe($e['bulletin']['numero_bulletin']);
            }
            $checked++;
        }
        DB::rollBack();
    }
    expect($checked)->toBe(6);
});

test('every tariff is recognised (Flask only found the cheapest one), and an already declared period is refused', function () {
    $calc = app(OccupationCalculator::class);
    $o = ($this->occ)('1');
    $o->update(['type' => 'Kiosque']);
    expect($calc->tariffFor('Kiosque')->value)->toEqual(80)->and($calc->situation($o->fresh())['trim_base'])->toBe(250.0);   // 12,5 × 80 ÷ 4

    expect(fn () => app(DeclareOccupationPeriods::class)->handle($o, ['2025_2'], userWithRole('collecteur')))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($calc->payable($o)['years'])->toBe([2026]);   // 2025 a déjà des trimestres payés / émis
});
