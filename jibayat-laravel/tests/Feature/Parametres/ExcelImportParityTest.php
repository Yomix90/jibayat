<?php

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Import\ExcelImportService;
use App\Domain\Import\SqliteImporter;
use App\Livewire\Parametres;
use App\Models\ImportLog;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/*
| Parité Flask ↔ Laravel pour l'importation Excel. tests/Fixtures/parity/imp.{db,json} et import_*.xlsx sont produits par
| tools/parity/imp_parity.py qui exécute les VRAIES vues Flask (importation.apercu et importation.executer).
| Écart volontaire : Flask importait aussi les lignes signalées en erreur à l'aperçu ; Laravel les ignore et les note au journal.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/imp.json')), true);
    expect((new SqliteImporter(base_path('tests/Fixtures/parity/imp.db')))->run()['ok'])->toBeTrue();
    $this->service = app(ExcelImportService::class);
    $this->user = userWithRole('administrateur');
    $this->file = fn (string $m) => base_path('tests/Fixtures/parity/import_'.strtolower($m).'.xlsx');
});

test('the preview (checks, taxpayer matching, applicable tariff) matches Flask importation.apercu', function () {
    foreach (['DEBITS_BOISSONS', 'CONTRIBUABLES'] as $m) {
        $p = $this->service->preview(($this->file)($m), $m);
        $e = $this->expected[$m]['apercu'];
        expect($p['total'])->toBe($e['total'], $m)->and($p['valides'])->toBe($e['valides']);
        foreach ($e['lignes'] as $i => $l) {
            $r = $p['rows'][$i];
            expect($r['_ligne_excel'])->toBe($l['ligne'])->and($r['_est_valide'])->toBe($l['valide'], "$m ligne {$l['ligne']}")
                ->and(count($r['_erreurs']))->toBe(count($l['erreurs']))->and(str_starts_with($r['_ctb_statut'], 'Existant'))->toBe($l['existant'], "$m ligne {$l['ligne']} existant")
                ->and($r['_tarif_trouve'])->toBe($l['tarif_trouve'], "$m ligne {$l['ligne']} tarif");
            if ($l['tarif_trouve'] || $m !== 'CONTRIBUABLES') {
                expect($r['_tarif_applicable'])->toStartWith($l['tarif']);
            }
        }
    }
});

test('execution creates the same establishments and taxpayers as Flask importation.executer for valid rows', function () {
    $r1 = $this->service->execute(($this->file)('DEBITS_BOISSONS'), 'DEBITS_BOISSONS', 'tdb.xlsx', $this->user);
    $r2 = $this->service->execute(($this->file)('CONTRIBUABLES'), 'CONTRIBUABLES', 'ctb.xlsx', $this->user);
    expect($r1)->success->toBe(3)->total->toBe(5)->and($r1['errors'])->toHaveCount(2)->and($r2['success'])->toBe(3);

    $valid = array_slice($this->expected['etablissements'], 0, 3);
    $outlets = BeverageOutlet::with(['taxpayer', 'tariff'])->whereNull('legacy_id')->orderBy('id')->get();
    expect($outlets)->toHaveCount(3);
    foreach ($valid as $i => $e) {
        $o = $outlets[$i];
        expect($o->number)->toBe($e['numero'])->and($o->name)->toBe($e['nom_etablissement'])->and((float) $o->area)->toEqual((float) $e['superficie'])
            ->and($o->tax_start_quarter)->toBe($e['trimestre_debut_imposition'])->and($o->tax_start_year)->toBe($e['annee_debut_imposition'])
            ->and($o->taxpayer->cin)->toBe($e['cin']);
        if (str_starts_with($e['ctb'], 'CTB')) {
            expect($o->taxpayer->number)->toBe($e['ctb']);
        }
    }
    expect($outlets->pluck('tariff.label')->all())->toBe(['Café / Salon de thé', 'Restaurant', 'Bar / Brasserie']);

    $created = Taxpayer::whereNull('legacy_id')->orderBy('id')->get();
    $expectedCreated = array_values(array_filter($this->expected['contribuables'], fn ($c) => ! in_array($c['cin'], ['ZZ9', 'YY8'], true)));
    expect($created->map(fn ($t) => [$t->displayName(), $t->cin, $t->ice, $t->person_type])->all())
        ->toEqual(array_map(fn ($c) => [trim($c['nom'].' '.$c['prenom']), $c['cin'] ?: null, $c['ice'] ?: null, $c['type_personne']], $expectedCreated));
    expect(ImportLog::whereNull('legacy_id')->orderBy('id')->get(['module', 'total_rows', 'success_count', 'error_count'])->toArray())
        ->toBe([['module' => 'DEBITS_BOISSONS', 'total_rows' => 5, 'success_count' => 3, 'error_count' => 2], ['module' => 'CONTRIBUABLES', 'total_rows' => 3, 'success_count' => 3, 'error_count' => 0]]);
});

test('import screens: template, preview, import, journal', function () {
    $this->actingAs($this->user);
    $this->get(route('parametres.importation-modele', 'TNB'))->assertOk();
    $file = UploadedFile::fake()->createWithContent('tdb.xlsx', file_get_contents(($this->file)('DEBITS_BOISSONS')));
    Livewire::test(Parametres\Importation::class)->assertSee('Café / Salon de thé')->set('file', $file)->call('analyse')->assertHasNoErrors()
        ->assertSee('Nom établissement manquant.')->call('import')->assertSet('preview', null)->assertSee('3</strong> / 5', false);
    Livewire::test(Parametres\Imports::class)->assertSee('tdb.xlsx')->assertSee('2 erreur(s)');
    $this->get(route('parametres.importation'))->assertOk();
    $this->get(route('parametres.imports'))->assertOk();

    $this->actingAs(userWithRole('consultation'));
    $this->get(route('parametres.importation'))->assertForbidden();
});
