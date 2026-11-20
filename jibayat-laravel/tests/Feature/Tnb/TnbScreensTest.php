<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Tnb\Models\BuildingPermit;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\ParcelCoOwner;
use App\Domain\Tnb\Models\ParcelDocument;
use App\Domain\Tnb\Models\TnbFile;
use App\Livewire\Tnb;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\Taxpayer;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $this->admin = userWithRole('administrateur');
    $rubric = Rubric::create(['code' => '10101001', 'label' => 'TNB', 'module' => 'TNB']);
    Tariff::create(['rubric_id' => $rubric->id, 'code' => 'TNB-B', 'label' => 'Zone B', 'value' => 4, 'unit' => 'DH/m²', 'start_date' => '2020-01-01']);
    $this->owner = Taxpayer::create(['number' => 'C1', 'last_name' => 'Bennani', 'first_name' => 'Ali', 'last_name_ar' => 'بناني', 'cin' => 'JA1']);
});

function addParcel(array $extra = []): Parcel
{
    Livewire::test(Tnb\Terrains::class)->call('createParcel')
        ->set('form.taxpayer_id', (string) test()->owner->id)->set('form.area', '250')->set('form.zone', 'B')
        ->set('form.subdivision', 'Al Amal')->set('form.acquired_on', '2025-01-10')
        ->tap(fn ($c) => collect($extra)->each(fn ($v, $k) => $c->set($k, $v)))
        ->call('saveParcel')->assertHasNoErrors();

    return Parcel::latest('id')->first();
}

test('the TNB list groups parcels by owner file, with KPIs and filters', function () {
    $this->actingAs($this->admin);
    $p = addParcel();
    expect($p)->number->toBe('1')->is_census->toBeFalse()->and(TnbFile::first()->number)->toBe(1);

    Livewire::test(Tnb\Terrains::class)
        ->assertSee('Bennani')->assertSee('Al Amal')->assertSee('2 an(s)')
        ->set('situation', 'ajour')->assertDontSee('T 1')
        ->set('situation', 'impaye')->assertSee('T 1')
        ->set('search', 'introuvable')->assertSee('Aucun dossier');

    $this->get(route('tnb.terrains'))->assertOk()->assertSee('Dossiers avec impayés');
    $this->get(route('tnb.imprimer-dossiers'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('a new owner can be created with the parcel; deleting is refused once a year is paid', function () {
    $this->actingAs($this->admin);
    Livewire::test(Tnb\Terrains::class)->call('createParcel')
        ->set('newTaxpayer', true)->set('taxpayerForm.last_name', 'Tazi')->set('taxpayerForm.last_name_ar', 'التازي')
        ->set('form.area', '80')->call('saveParcel')->assertHasNoErrors();
    $p = Parcel::first();
    expect($p->taxpayer->last_name)->toBe('Tazi')->and(TnbFile::where('taxpayer_id', $p->taxpayer_id)->exists())->toBeTrue();

    Livewire::test(Tnb\Terrains::class)->call('deleteParcel', $p->id);
    expect($p->fresh()->is_active)->toBeFalse();
});

test('collecting: years are computed at the declaration date, declared with one slip number, then pending', function () {
    $this->actingAs($this->admin);
    $p = addParcel();

    $this->get(route('tnb.encaisser'))->assertOk()->assertSee('Bennani');
    Livewire::withQueryParams(['terrain' => $p->id])->test(Tnb\Encaisser::class)
        ->assertSet('selected', [2025, 2026])
        ->assertSee('Calcul par année')
        ->set('selected', [2025])->set('slip_number', 'BV-42')
        ->call('declare')->assertHasNoErrors()
        ->assertSet('selected', [2026]);

    $d = TaxDeclaration::first();
    expect($d)->year->toBe(2025)->status->toBe('emis')->declared_on->toDateString()->toBe('2026-06-15')
        ->and(PaymentSlip::first())->slip_number->toBe('BV-42')->status->toBe('en_attente');

    // Bulletin rejeté ➜ l'année revient cochable (« bulletin rejeté »)
    app(\App\Domain\Caisse\Services\CashierService::class)->reject(PaymentSlip::first(), 'Illisible', userWithRole('regisseur'));
    Livewire::withQueryParams(['terrain' => $p->id])->test(Tnb\Encaisser::class)
        ->assertSee('bulletin rejeté')->set('selected', [2025])->set('slip_number', 'BV-43')->call('declare')->assertHasNoErrors();
    expect(TaxDeclaration::count())->toBe(1)->and(PaymentSlip::whereNull('cancelled_at')->value('slip_number'))->toBe('BV-43');
});

test('the parcel file manages permits, co-owners, transfers, documents and declaration cancellation', function () {
    $this->actingAs($this->admin);
    Storage::fake('local');
    $p = addParcel();
    $buyer = Taxpayer::create(['number' => 'C2', 'last_name' => 'Tazi']);
    $co = Taxpayer::create(['number' => 'C3', 'last_name' => 'Idrissi']);

    $c = Livewire::withQueryParams(['terrain' => $p->id])->test(Tnb\Fiche::class)->assertSee('Terrain n° 1');

    $c->call('addPermit')->set('permit.type', 'construction')->set('permit.authorized_on', '2026-04-01')->call('savePermit')->assertHasNoErrors();
    expect(BuildingPermit::first())->exemption_start_year->toBe(2026)->exemption_end_year->toBe(2028)
        ->and($p->fresh()->status)->toBe('en_construction');

    $c->call('editCoOwner')->set('coOwner.taxpayer_id', (string) $this->owner->id)->call('saveCoOwner')->assertHasErrors('coOwner.taxpayer_id');
    $c->set('coOwner.taxpayer_id', (string) $co->id)->set('coOwner.share_percent', '33.5')->set('coOwner.title_type', 'heritage')
        ->set('coOwner.notarial_deed', 'ACT-9')->call('saveCoOwner')->assertHasNoErrors();
    expect(ParcelCoOwner::first())->share_percent->toBe('33.5000')->title_type->toBe('heritage')->notarial_deed->toBe('ACT-9');

    $c->call('addDocument')->set('upload', UploadedFile::fake()->create('titre.pdf', 50, 'application/pdf'))->set('documentType', 'titre_foncier')
        ->call('saveDocument')->assertHasNoErrors();
    $doc = ParcelDocument::first();
    Storage::disk('local')->assertExists($doc->path);
    $this->get(route('tnb.document', $doc))->assertOk();

    \App\Domain\Tnb\Models\Parcel::find($p->id);
    app(\App\Domain\Tnb\Actions\DeclareTnbYears::class)->handle($p->fresh(), [2025], $this->admin, ['slip_number' => 'BV-1']);
    $decl = TaxDeclaration::first();
    $c->call('askCancel', $decl->id)->set('cancelReason', 'x')->call('confirmCancel')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Superficie erronée')->call('confirmCancel')->assertHasNoErrors();
    expect($decl->fresh()->cancellation_reason)->toBe('Superficie erronée');

    $c->call('startTransfer')->set('transfer.new_taxpayer_id', (string) $buyer->id)->set('transfer.reason', 'Vente')->call('saveTransfer')->assertHasNoErrors();
    expect($p->fresh()->taxpayer_id)->toBe($buyer->id)->and(TnbFile::where('taxpayer_id', $buyer->id)->exists())->toBeTrue();

    $this->get(route('tnb.fiche', ['terrain' => $p->id]))->assertOk()->assertSee('Idrissi')->assertSee('titre.pdf')->assertSee('Superficie erronée');
});

test('census parcels have no file until transferred to the main list', function () {
    $this->actingAs($this->admin);
    Livewire::test(Tnb\Recensement::class)->call('createParcel')
        ->set('form.taxpayer_id', (string) $this->owner->id)->set('form.area', '120')->call('saveParcel')->assertHasNoErrors();
    $p = Parcel::first();
    expect($p->is_census)->toBeTrue()->and(TnbFile::count())->toBe(0);
    Livewire::test(Tnb\Terrains::class)->assertDontSee('T '.$p->number);

    Livewire::test(Tnb\Recensement::class)->assertSee('Bennani')->call('promote', $p->id);
    expect($p->fresh()->is_census)->toBeFalse()->and(TnbFile::first()->taxpayer_id)->toBe($this->owner->id);
});

test('notices list debtor parcels and print individual and batch PDFs; dashboard and register open', function () {
    $this->actingAs($this->admin);
    $p = addParcel();

    Livewire::test(Tnb\Avis::class)->assertSee('Bennani')->set('minYears', 3)->assertDontSee('Bennani');
    Livewire::test(Tnb\AvisLot::class)->assertSet('selected', [$p->id])->assertSee('Imprimer la sélection (1)');

    $this->get(route('tnb.avis-imprimer', $p))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('tnb.avis-lot-imprimer', ['ids' => $p->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('tnb.tableau-de-bord'))->assertOk();
    $this->get(route('tnb.registre'))->assertOk();
    $this->get(route('tnb.avis-lot'))->assertOk();
    $this->get(route('tnb.recensement'))->assertOk();
});

test('a read-only user cannot add parcels or declare', function () {
    $viewer = userWithRole('consultation');
    $this->actingAs($viewer);
    $this->get(route('tnb.terrains'))->assertOk()->assertDontSee('wire:click="createParcel"', false);
    Livewire::test(Tnb\Terrains::class)->call('createParcel')->assertForbidden();
});
