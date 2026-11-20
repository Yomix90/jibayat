<?php

use App\Domain\Documents\Models\IssueSlip;
use App\Domain\Documents\Models\TreasuryStatement;
use App\Livewire\Documents;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->actingAs(userWithRole('administrateur'));
    $this->fixture = fn (string $name) => UploadedFile::fake()->createWithContent($name, file_get_contents(base_path("tests/Fixtures/parity/$name")));
});

test('TGR statement: upload, filters, totals in words, Excel export and print', function () {
    Livewire::test(Documents\RelevesGir::class)->set('file', ($this->fixture)('tgr_releve.xlsx'))->call('upload')->assertHasNoErrors()
        ->assertSee('BENNANI ALI')->assertSee('14 103,00 DH')->assertSee('quatorze mille cent trois Dirhams')
        ->set('rubric', 'Taxe sur les débits de boissons')->assertSee('SARL ATLAS')->assertDontSee('BENNANI ALI')->assertSee('mille cent cinquante Dirhams')
        ->set('rubric', '')->set('from', '2026-05-10')->assertSee('TAZI')->assertDontSee('SARL ATLAS');
    $this->get(route('documents.tgr-imprimer', ['debut' => '2026-05-10']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $res = $this->get(route('documents.tgr-export'));
    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('Activite_mensuelle_20260503.xlsx');
});

test('issue slips: import three months, edit, add and remove a line, configuration, delete a month, prints', function () {
    $page = Livewire::test(Documents\Emission::class, ['year' => 2026]);
    foreach ([1, 2, 3] as $m) {
        $page->call('openImport')->set('file', ($this->fixture)(sprintf('gir_bordereau_%02d.xlsx', $m)))->call('import')->assertHasNoErrors();
    }
    expect(TreasuryStatement::count())->toBe(3)->and(IssueSlip::count())->toBe(8);
    $page->assertSet('month', 3)->assertSee('3 500,25');

    // Ré-import sans « remplacer » refusé
    $page->call('openImport')->set('file', ($this->fixture)('gir_bordereau_01.xlsx'))->call('import')->assertHasErrors('file');

    // Modifier le loyer de janvier : reports de février et mars recalculés
    $page->set('month', 1)->call('startEdit', '1140102026', '1200')->set('editAmount', '1000')->call('saveAmount')->assertHasNoErrors();
    expect(IssueSlip::where('number', 7)->first())->carried_forward->toBe('1800.00')->total->toBe('3300.25');

    // Ajouter puis retirer une rubrique en février : numéros resynchronisés
    $page->set('month', 2)->set('addCode', '1110403032')->set('addAmount', '75')->call('addLine')->assertHasNoErrors();
    expect(IssueSlip::where('budget_code', '1110403032')->first())->number->toBe(7)->rubric->toBe('Fourrière');
    $page->set('addCode', '1110403032')->set('addAmount', '5')->call('addLine')->assertHasErrors('addCode');
    $page->call('deleteLine', '1110403032');
    expect(IssueSlip::count())->toBe(8);

    // Configuration : 1ère partie saisie, 2ème partie vide
    $page->call('openConfig')->set('config.first_part_mode', 'manuel')->set('config.first_part_value', '5000')->set('config.second_part_mode', 'vide')->call('saveConfig')->assertHasNoErrors();
    $slip = IssueSlip::where('number', 4)->first();
    $this->get(route('documents.emission-imprimer', $slip))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('documents.emission-imprimer-mois', [2026, 2]))->assertOk();

    $page->set('month', 1)->call('deleteMonth');
    expect(TreasuryStatement::count())->toBe(2)->and(IssueSlip::orderBy('number')->first())->number->toBe(1)->carried_forward->toBe('0.00');
    $this->get(route('documents.emission', ['annee' => 2026]))->assertOk()->assertSee('Février');
});
