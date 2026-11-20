<?php

namespace App\Domain\Locations\Services;

use App\Domain\Locations\Models\LeaseTariff;
use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\TaxOrder;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisation d'un groupe de tarif de location avec l'arrêté fiscal actif —
 * Flask location._sync_tarif_fiscal.
 *
 * Code du tarif fiscal : « {code secteur}-T{id du groupe} », libellé « {secteur} — {groupe} ».
 * Déjà lié : le tarif fiscal est mis à jour. Sinon : l'ancien tarif actif de même code est clôturé
 * à aujourd'hui, un nouveau est créé dans l'arrêté actif (à défaut le plus récent) et lié.
 */
class LeaseTariffSync
{
    public function sync(LeaseTariff $lt): ?Tariff
    {
        $lt->loadMissing('leaseSector');
        $rubric = Rubric::where('module', 'LOCATION_LOCAUX')->first();
        $order = TaxOrder::where('status', 'actif')->orderByDesc('id')->first() ?? TaxOrder::orderByDesc('id')->first();
        if (! $rubric || ! $order || ! $lt->leaseSector) {
            return null;
        }

        $code = "{$lt->leaseSector->code}-T{$lt->id}";
        $label = "{$lt->leaseSector->label} — {$lt->label}";

        return DB::transaction(function () use ($lt, $rubric, $order, $code, $label) {
            if ($lt->tariff_id && ($existing = Tariff::find($lt->tariff_id))) {
                $existing->update(['label' => $label, 'value' => $lt->value, 'unit' => $lt->unit, 'code' => $code]);

                return $existing;
            }

            $today = today()->toDateString();
            Tariff::where('rubric_id', $rubric->id)->where('code', $code)->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => $today]);
            $tariff = Tariff::create([
                'rubric_id' => $rubric->id, 'tax_order_id' => $order->id, 'code' => $code, 'label' => $label,
                'value' => $lt->value, 'unit' => $lt->unit, 'start_date' => $today, 'is_active' => true, 'surface_min' => 0,
            ]);
            $lt->update(['tariff_id' => $tariff->id]);

            return $tariff;
        });
    }

    /** Synchronise tous les groupes de tarif sans tarif fiscal (Flask _sync_all_tarifs_fiscal). */
    public function syncAll(): int
    {
        return LeaseTariff::whereNull('tariff_id')->get()->filter(fn ($lt) => $this->sync($lt) !== null)->count();
    }

    /** État de synchronisation (Flask loc_secteurs) : valeur dans l'arrêté et écart avec la valeur du groupe. */
    public function status(LeaseTariff $lt): array
    {
        $fiscal = $lt->tariff_id ? Tariff::find($lt->tariff_id) : null;

        return [
            'fiscal_value' => $fiscal ? (float) $fiscal->value : null,
            'fiscal_start' => $fiscal?->start_date,
            'in_sync' => $fiscal !== null && abs((float) $fiscal->value - (float) $lt->value) < 0.01,
            'fiscal_active' => (bool) $fiscal?->is_active,
        ];
    }
}
