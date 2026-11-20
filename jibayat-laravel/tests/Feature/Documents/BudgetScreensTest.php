<?php

use App\Domain\Documents\Models\BudgetLine;
use App\Livewire\Documents;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->actingAs(userWithRole('administrateur'));
});

test('budget screen: nomenclature, entry, import, add and remove a chapter, prints in both languages', function () {
    $page = Livewire::test(Documents\Budget::class)->assertSee('Administration Générale')->assertSee('10.10.10.11');
    expect(BudgetLine::where('year', 2026)->count())->toBe(48);

    $id = BudgetLine::where('year', 2026)->where('chapter', '10.10.10.11')->value('id');
    $page->set("values.$id.proposed", '1000')->set("values.$id.actual", '250')->call('save')->assertHasNoErrors()->assertSee('25,0 %');
    expect(BudgetLine::find($id))->proposed->toBe('1000.00')->actual->toBe('250.00');
    $page->set("values.$id.proposed", '-5')->call('save')->assertHasErrors("values.$id.proposed");

    $file = UploadedFile::fake()->createWithContent('budget.xlsx', file_get_contents(base_path('tests/Fixtures/parity/budget_import.xlsx')));
    $page->set('values', [])->set('file', $file)->call('import')->assertHasNoErrors();
    expect(BudgetLine::where('year', 2026)->where('chapter', '20.99.10.11')->first())->domain->toBe('SOCIAL');

    $page->call('openAdd')->set('line.chapter', '40.99.10.11')->set('line.label_fr', 'Nouvelle recette')->set('line.domain', 'ECONOMIQUE')->set('line.proposed', '1500')
        ->call('addLine')->assertHasNoErrors();
    $page->call('openAdd')->set('line.chapter', '40.99.10.11')->set('line.label_fr', 'Doublon')->call('addLine')->assertHasErrors('line.chapter');
    $page->call('deleteLine', BudgetLine::where('chapter', '40.99.10.11')->value('id'));
    expect(BudgetLine::where('chapter', '40.99.10.11')->exists())->toBeFalse();

    $page->call('sync')->assertHasNoErrors();
    $page->set('lang', 'ar')->assertSee('مجال الإدارة العامة');
    $this->get(route('documents.budget-imprimer', ['annee' => 2026, 'lang' => 'ar']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('documents.budget-export', ['annee' => 2026]))->assertOk();
});

test('a consultation user sees the budget without editing it', function () {
    $this->actingAs(userWithRole('consultation'));
    Livewire::test(Documents\Budget::class)->assertDontSee('Enregistrer le budget')->call('sync')->assertForbidden();
});
