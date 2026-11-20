<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Services\CashierService;
use App\Domain\Fourriere\Models\ImpoundCase;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Domain\Fourriere\Services\ImpoundService;
use App\Domain\Import\SqliteImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/*
| Parité Flask ↔ Laravel pour la fourrière. tests/Fixtures/parity/fou.{db,json} sont produits par
| tools/parity/fou_parity.py (vraies fonctions de modules/fourriere.py, y compris la route fou_payer) à date figée.
*/

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/fou.json')), true);
    $report = (new SqliteImporter(base_path('tests/Fixtures/parity/fou.db')))->run();
    expect($report['ok'])->toBeTrue('import de la base de parité fourrière');
    $this->travelTo(CarbonImmutable::parse($this->expected['today'].' 10:00'));
    $this->case = fn ($legacy) => ImpoundCase::where('legacy_id', $legacy)->firstOrFail();
});

test('days in custody, amount due and 265/365-day flags match Flask _enrichir_dossier', function () {
    foreach ($this->expected['nb_jours'] as $date => $days) {
        expect(ImpoundCalculator::days($date ?: null))->toBe($days, "jours depuis « {$date} »");
    }
    foreach ($this->expected['dossiers'] as $legacy => $e) {
        $r = ImpoundCalculator::enrich(($this->case)($legacy));
        expect($r['days'])->toBe($e['nb_jours'], "dossier {$legacy}")->and($r['amount'])->toEqual((float) $e['montant_du'])
            ->and($r['plus265'])->toBe($e['plus265'])->and($r['plus365'])->toBe($e['plus365']);
    }
});

test('daily rates by vehicle type and list indicators match Flask', function () {
    $calc = app(ImpoundCalculator::class);
    foreach ($this->expected['tarifs'] as $label => $rate) {
        expect($calc->dailyRate($label))->toEqual((float) $rate, $label);
    }
    $rows = ImpoundCase::where('is_active', true)->get()->map(fn ($c) => ['case' => $c] + ImpoundCalculator::enrich($c));
    expect(ImpoundCalculator::stats($rows))->toEqual(array_map(fn ($v) => is_float($v) ? $v : $v, $this->expected['stats']));
});

test('payment reproduces Flask fou_payer; the régisseur validation puts the vehicle « en attente de sortie », then release', function () {
    $agent = userWithRole('collecteur');
    foreach ($this->expected['scenarios'] as $sc) {
        DB::beginTransaction();
        $c = ($this->case)($sc['input']['id']);
        $d = app(ImpoundService::class)->pay($c, $agent, ['name' => $sc['input']['nom'], 'cin' => $sc['input']['cin'] ?: null, 'phone' => '0600'], $sc['input']['bv']);
        $e = $sc['declaration'];
        expect((float) $d->total)->toEqual((float) $e['montant_total'])->and((float) $d->base_amount)->toEqual((float) $e['base_calcul'])
            ->and($d->year)->toBe($e['annee'])->and($d->notes)->toBe($e['notes'])->and($d->status)->toBe('emis')
            ->and(PaymentSlip::where('tax_declaration_id', $d->id)->first())->slip_number->toBe($sc['bulletin']['numero_bulletin'])->status->toBe('en_attente')
            ->and($c->fresh())->owner_name->toBe($sc['dossier']['nom_proprietaire'])->slip_number->toBe($sc['dossier']['numero_bulletin']);
        DB::rollBack();
    }

    $c = ($this->case)('3');
    expect(fn () => app(ImpoundService::class)->release($c, $agent))->toThrow(\Illuminate\Validation\ValidationException::class);
    app(ImpoundService::class)->pay($c, $agent, ['name' => 'Haddad'], 'BV-9');
    expect(fn () => app(ImpoundService::class)->pay($c->fresh(), $agent, ['name' => 'X'], 'BV-10'))->toThrow(\Illuminate\Validation\ValidationException::class);
    app(CashierService::class)->validate(PaymentSlip::where('slip_number', 'BV-9')->first(), 'Q-9', '2026-06-15', userWithRole('regisseur'));
    expect($c->fresh()->status)->toBe('en_attente_sortie');
    app(ImpoundService::class)->release($c->fresh(), $agent);
    expect($c->fresh())->status->toBe('sortie')->released_on->toDateString()->toBe('2026-06-15');
});
