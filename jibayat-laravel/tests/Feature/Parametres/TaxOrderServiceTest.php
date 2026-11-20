<?php

use App\Domain\Parametres\Services\TaxOrderService;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\TaxOrder;
use Illuminate\Support\Facades\DB;

/*
| Règles des arrêtés fiscaux reprises de Flask (modules/config.py).
*/

beforeEach(function () {
    $this->admin = userWithRole('administrateur');
    $this->service = app(TaxOrderService::class);
    $this->tnb = Rubric::create(['code' => '30101014', 'label' => 'Taxe TNB', 'module' => 'TNB']);
    $this->sta = Rubric::create(['code' => '40203033', 'label' => 'Droit de stationnement', 'module' => 'STATIONNEMENT']);
    $this->first = TaxOrder::create(['number' => 'AF-2020-001', 'title' => 'Initial', 'effective_date' => '2020-01-01', 'status' => 'actif']);
    $this->first->tariffs()->create(['rubric_id' => $this->tnb->id, 'label' => 'Zone A', 'value' => 20, 'unit' => 'DH/m²', 'start_date' => '2020-01-01']);
    $this->first->tariffs()->create(['rubric_id' => $this->tnb->id, 'label' => 'Zone B', 'value' => 8, 'unit' => 'DH/m²', 'start_date' => '2020-01-01', 'surface_min' => 0, 'surface_max' => 500]);
});

test('the tariff code takes the first three letters of the first two words (Flask)', function () {
    expect(TaxOrderService::tariffCode('30101014', 'Zone A — Bien équipée'))->toBe('30101014-ZONA')
        ->and(TaxOrderService::tariffCode('40203033', 'Grand Taxi'))->toBe('40203033-GRATAX')
        ->and(TaxOrderService::tariffCode('10403032', 'Écart de caisse'))->toBe('10403032-ÉCADE')
        ->and(TaxOrderService::tariffCode(null, 'Kiosque'))->toBe('KIO');
});

test('the number follows AF-{year}-{count+1} and never collides', function () {
    expect($this->service->nextNumber(2027))->toBe('AF-2027-002');
    TaxOrder::create(['number' => 'AF-2027-002', 'title' => 'x', 'effective_date' => '2027-01-01', 'status' => 'remplace']);
    expect($this->service->nextNumber(2027))->toBe('AF-2027-003'); // 2 arrêtés ➜ n = 3, libre
});

test('creating an order closes the active one and copies the latest tariffs at the new effective date', function () {
    $order = $this->service->create($this->admin, '2027-01-01', 'Arrêté 2027', null, true);

    $this->first->refresh();
    expect($this->first->status)->toBe('remplace')
        ->and($this->first->end_date->toDateString())->toBe('2027-01-01')
        ->and($this->first->tariffs()->where('is_active', true)->count())->toBe(0)
        ->and($this->first->tariffs()->first()->end_date->toDateString())->toBe('2027-01-01');

    expect($order->status)->toBe('actif')
        ->and($order->tariffs()->count())->toBe(3); // Zone A, Zone B + tarif à 0 pour le stationnement

    $zoneB = $order->tariffs()->where('label', 'Zone B')->first();
    expect($zoneB->value)->toBe('8.0000')
        ->and($zoneB->surface_max)->toBe('500.00')
        ->and($zoneB->start_date->toDateString())->toBe('2027-01-01')
        ->and($zoneB->code)->toBe('30101014-ZONB');

    $placeholder = $order->tariffs()->where('rubric_id', $this->sta->id)->first();
    expect($placeholder->value)->toBe('0.0000')->and($placeholder->unit)->toBe('DH');
});

test('only the selected rubrics are copied, and nothing when copy is disabled', function () {
    $only = $this->service->create($this->admin, '2027-01-01', null, null, true, [$this->sta->id]);
    expect($only->tariffs()->pluck('rubric_id')->unique()->all())->toBe([$this->sta->id]);

    $empty = $this->service->create($this->admin, '2028-01-01', null, null, false);
    expect($empty->tariffs()->count())->toBe(0);
});

test('adding a tariff closes the open tariff with the same label', function () {
    $new = $this->service->addTariff($this->first, $this->tnb, ['label' => 'Zone A', 'value' => 25, 'unit' => 'DH/m²', 'start_date' => '2026-01-01']);

    $old = Tariff::where('label', 'Zone A')->where('id', '!=', $new->id)->first();
    expect($old->is_active)->toBeFalse()
        ->and($old->end_date->toDateString())->toBe('2026-01-01')
        ->and($new->is_active)->toBeTrue()
        ->and($new->value)->toBe('25.0000');
});

test('a tariff whose end date is past becomes inactive when edited', function () {
    $t = $this->first->tariffs()->first();
    $this->service->updateTariff($t, ['value' => 21, 'unit' => 'DH/m²', 'start_date' => '2020-01-01', 'end_date' => '2021-12-31']);

    expect($t->fresh()->is_active)->toBeFalse()->and($t->fresh()->value)->toBe('21.0000');
});

test('adding a rubric copies its latest tariffs, refuses duplicates, and loads default tariffs once', function () {
    $order = $this->service->create($this->admin, '2027-01-01', null, null, false);

    expect($this->service->addRubric($order, $this->tnb))->toBe(2);
    expect(fn () => $this->service->addRubric($order, $this->tnb))->toThrow(InvalidArgumentException::class);

    $added = $this->service->loadDefaults($order);
    expect($added)->toBe(6) // 3 TNB + 3 stationnement (les rubriques boissons, ODP, fourrière n'existent pas ici)
        ->and($this->service->loadDefaults($order))->toBe(0);
});

test('deleting a tariff detaches linked lease tariffs instead of failing', function () {
    $t = $this->first->tariffs()->first();
    $sector = DB::table('lease_sectors')->insertGetId(['code' => 'S1', 'label' => 'Marché', 'created_at' => now(), 'updated_at' => now()]);
    $lt = DB::table('lease_tariffs')->insertGetId(['lease_sector_id' => $sector, 'label' => 'Boutique', 'value' => 300, 'tariff_id' => $t->id, 'created_at' => now(), 'updated_at' => now()]);

    $this->service->deleteTariffs([$t->id]);

    expect(Tariff::find($t->id))->toBeNull()
        ->and(DB::table('lease_tariffs')->where('id', $lt)->value('tariff_id'))->toBeNull();
});

test('deleting the active order requires a reason, is journaled and reactivates the latest order', function () {
    $new = $this->service->create($this->admin, '2027-01-01', null, null, true);

    expect(fn () => $this->service->delete($new, $this->admin, ' '))->toThrow(InvalidArgumentException::class);

    $count = $this->service->delete($new, $this->admin, 'Saisi par erreur');

    expect($count)->toBe(3)
        ->and(TaxOrder::find($new->id))->toBeNull()
        ->and($this->first->fresh()->status)->toBe('actif');
    $this->assertDatabaseHas('activity_log', ['description' => 'Suppression de l\'arrêté fiscal '.$new->number]);
});
