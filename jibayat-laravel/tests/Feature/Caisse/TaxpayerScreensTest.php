<?php

use App\Livewire\Caisse;
use App\Models\Taxpayer;
use App\Models\TaxpayerDocument;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->actingAs(userWithRole('administrateur'));
});

test('create, find, edit, document, notice, export and remove a taxpayer', function () {
    Livewire::test(Caisse\NouveauContribuable::class)->set('form.last_name', '')->call('save')->assertHasErrors('form.last_name');
    Livewire::test(Caisse\NouveauContribuable::class)->set('form.last_name', 'Alaoui')->set('form.first_name', 'Ahmed')->set('form.cin', 'jb1234')
        ->set('form.last_name_ar', 'العلوي')->call('save')->assertHasNoErrors()->assertRedirect();
    $t = Taxpayer::where('cin', 'JB1234')->firstOrFail();
    expect($t->number)->toBe('CTB202600001');

    // CIN déjà connu : pas de doublon
    Livewire::test(Caisse\NouveauContribuable::class)->set('form.last_name', 'Autre')->set('form.cin', 'JB1234')->call('save')->assertHasErrors('form.cin');
    Livewire::test(Caisse\NouveauContribuable::class)->set('form.person_type', 'morale')->set('form.company_name', 'Atlas SARL')->set('form.ice', '0015')
        ->call('save')->assertHasNoErrors();

    Livewire::test(Caisse\Contribuables::class)->assertSee('Alaoui Ahmed')->assertSee('Atlas SARL')->set('search', 'JB12')->assertSee('Alaoui')->assertDontSee('Atlas SARL')
        ->set('search', '')->set('module', 'AUCUN')->assertSee('Atlas SARL');

    $fiche = Livewire::test(Caisse\FicheContribuable::class)->assertSee('Choisissez un contribuable')->set('search', 'alao')->call('select', $t->id)
        ->assertSee('CTB202600001')->assertSee('العلوي');
    $fiche->call('edit')->set('form.phone', '0612345678')->call('update')->assertHasNoErrors();
    expect($t->fresh()->phone)->toBe('0612345678');
    $fiche->call('addDocument')->set('upload', UploadedFile::fake()->create('cin.pdf', 20, 'application/pdf'))->set('documentType', 'cin')->call('saveDocument')->assertHasNoErrors();
    $doc = TaxpayerDocument::firstOrFail();
    $this->get(route('caisse.contribuable-document', $doc))->assertOk();
    $fiche->call('deleteDocument', $doc->id);
    expect(TaxpayerDocument::count())->toBe(0);

    $this->get(route('caisse.contribuable-avis', $t))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('caisse.contribuables-imprimer'))->assertOk();
    $this->get(route('caisse.contribuables-export'))->assertOk();
    $this->get(route('caisse.contribuables'))->assertOk();

    Livewire::test(Caisse\Contribuables::class)->call('deactivate', $t->id)->assertDontSee('Alaoui Ahmed');
    expect($t->fresh()->is_active)->toBeFalse();
});

test('a consultation user cannot create or edit taxpayers', function () {
    $this->actingAs(userWithRole('consultation'));
    $t = Taxpayer::create(['number' => 'CTB-X', 'last_name' => 'Test', 'is_active' => true]);
    Livewire::test(Caisse\FicheContribuable::class, ['taxpayerId' => $t->id])->assertDontSeeHtml('wire:click="edit"')->call('edit')->assertForbidden();
    $this->get(route('caisse.nouveau-contribuable'))->assertForbidden();
});
