<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Import\SqliteImporter;
use App\Domain\Transport\Actions\PayVehicleQuarters;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\TransportCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/*
| Parité Flask ↔ Laravel pour le transport (stationnement + TPV).
| tests/Fixtures/parity/sta.{db,json} sont produits par tools/parity/sta_parity.py qui exécute les VRAIES fonctions
| de Flask (modules/stationnement.py, y compris la route sta_payer) à date figée. Tarifs d'essai en DH/trimestre.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/sta.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/sta.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité transport');
    $this->travelTo(CarbonImmutable::parse($this->expected['today'].' 10:00'));
    $this->vehicle = fn ($legacy) => Vehicle::where('legacy_id', $legacy)->firstOrFail();
});

test('tariffs by vehicle type and unpaid quarters match Flask', function () {
    $calc = app(TransportCalculator::class);
    foreach ($this->expected['vehicules'] as $legacy => $e) {
        $v = ($this->vehicle)($legacy);
        [$sta, $tpv] = $calc->amounts($v);
        expect($sta)->toEqual((float) $e['tarif_sta'], "stationnement véhicule {$legacy}")
            ->and($tpv)->toEqual((float) $e['tarif_tpv'], "TPV véhicule {$legacy}");
        $unpaid = array_map(fn ($q) => ['annee' => $q['annee'], 'trimestre' => $q['trimestre'], 'echeance' => $q['echeance']], $calc->unpaidQuarters($v));
        expect($unpaid)->toBe($e['non_payes'], "trimestres véhicule {$legacy}");
    }
});

test('list amounts match Flask _get_sta_items_data', function () {
    $calc = app(TransportCalculator::class);
    foreach ($this->expected['liste'] as $legacy => $e) {
        $s = $calc->situation(($this->vehicle)($legacy));
        expect($s['nb_non_declares'])->toBe($e['nb_non_declares'])->and($s['nb_emises'])->toBe($e['nb_emises'])
            ->and($s['annees_non_payees'])->toBe($e['annees_non_payees'])
            ->and($s['montant_impaye'])->toEqual((float) $e['montant_impaye'], "impayé véhicule {$legacy}")
            ->and($s['montant_en_attente'])->toEqual((float) $e['montant_en_attente']);
    }
});

test('quarterly payment reproduces Flask sta_payer, without late penalties (rule confirmed by the régisseur)', function () {
    $agent = userWithRole('collecteur');
    $checked = 0;
    foreach ($this->expected['scenarios'] as $sc) {
        DB::beginTransaction();
        $v = ($this->vehicle)($sc['input']['id']);
        app(PayVehicleQuarters::class)->handle($v, $sc['input']['trims'], $agent, ['paid_on' => $sc['input']['date'], 'slip_number' => $sc['input']['bv']]);
        $got = TaxDeclaration::where('taxable_type', 'vehicle')->where('taxable_id', $v->id)->whereNull('legacy_id')->orderBy('year')->orderBy('quarter')->get();
        expect($got)->toHaveCount(count($sc['declarations']));
        foreach ($sc['declarations'] as $i => $e) {
            $d = $got[$i];
            $label = "véhicule {$sc['input']['id']} T{$e['trimestre']} {$e['annee']} au {$sc['input']['date']}";
            expect([$d->year, $d->quarter])->toBe([$e['annee'], $e['trimestre']], $label)
                ->and((float) $d->base_amount)->toEqual((float) $e['base_calcul'], "{$label} : TPV")
                ->and((float) $d->rate)->toEqual((float) $e['taux'], "{$label} : stationnement")
                ->and((float) $d->principal)->toEqual((float) $e['montant_principal'], "{$label} : principal")
                // Écart voulu : Flask ajoutait pénalité + majoration sur la TPV ; ici aucune pénalité.
                ->and((float) $d->late_penalty)->toBe(0.0)->and((float) $d->surcharge)->toBe(0.0)
                ->and((float) $d->total)->toEqual((float) $e['montant_principal'], "{$label} : total = principal")
                ->and($d->status)->toBe((float) $e['montant_principal'] < 200 ? 'sous_seuil' : 'emis')
                ->and($d->due_on->toDateString())->toBe($e['date_echeance'], "{$label} : échéance");
            if ($e['bulletin']) {
                $slip = PaymentSlip::where('tax_declaration_id', $d->id)->first();
                expect($slip->slip_number)->toBe($e['bulletin']['numero_bulletin'])->and((float) $slip->amount)->toEqual((float) $e['montant_principal']);
            }
            $checked++;
        }
        DB::rollBack();
    }
    expect($checked)->toBe(11);
});

test('the tax order value is the quarterly amount, whatever unit label it carries', function () {
    DB::table('tariffs')->where('label', 'Grand Taxi')->update(['unit' => 'DH/an']);
    [$sta, $tpv] = app(TransportCalculator::class)->amounts(($this->vehicle)(1));
    expect($sta)->toBe(300.0)->and($tpv)->toBe(450.5);
});
