<?php

namespace App\Domain\Parametres\Services;

use App\Models\Rubric;
use App\Models\Tariff;
use App\Models\TaxOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Arrêtés fiscaux et tarifs — règles reprises de Flask modules/config.py.
 *
 * - Un seul arrêté « actif » : en créer un nouveau clôt le précédent (statut « remplace »,
 *   date de fin = date d'effet du nouveau) et désactive ses tarifs encore ouverts.
 * - Les tarifs peuvent être recopiés depuis les derniers tarifs connus de chaque rubrique,
 *   avec la date d'effet du nouvel arrêté.
 * - Code tarif = « {code rubrique}-{3 premières lettres des 2 premiers mots du libellé} ».
 */
class TaxOrderService
{
    /** Tarifs types de référence (Flask : charger_tarifs_defaut_arrete). */
    public const DEFAULT_TARIFFS = [
        'TNB' => [['Zone A — Bien équipée', 20, 'DH/m²'], ['Zone B — Moyennement équipée', 8, 'DH/m²'], ['Zone C — Peu équipée', 1, 'DH/m²']],
        'DEBITS_BOISSONS' => [['Café / Salon de thé', 6, '%'], ['Bar / Brasserie', 10, '%'], ['Restaurant', 5, '%'], ['Hôtel-Bar', 8, '%']],
        'STATIONNEMENT' => [['Grand Taxi', 300, 'DH/trimestre'], ['Petit Taxi', 200, 'DH/trimestre'], ['Autocar / Minibus', 500, 'DH/trimestre']],
        'TRANSPORT_VOYAGEURS' => [['Grand Taxi', 100, 'DH/trimestre'], ['Petit Taxi', 100, 'DH/trimestre'], ['Autocar / Minibus', 400, 'DH/trimestre']],
        'OCCUPATION_DOMAINE' => [['Terrasse / Étalage', 50, 'DH/m²/an'], ['Kiosque', 80, 'DH/m²/an'], ['Chantier', 30, 'DH/m²/mois']],
        'FOURRIERE' => [['Voiture particulière / jour', 25, 'DH/jour'], ['Moto / Scooter / jour', 15, 'DH/jour'], ['Camion / jour', 50, 'DH/jour'], ['Frais de remorquage', 150, 'DH']],
    ];

    public static function tariffCode(?string $rubricCode, string $label): string
    {
        $words = collect(preg_split('/\s+/u', trim($label)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 3)))->implode('');

        return $rubricCode ? "{$rubricCode}-{$words}" : $words;
    }

    public function nextNumber(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $n = TaxOrder::count() + 1;
        do {
            $number = sprintf('AF-%d-%03d', $year, $n++);
        } while (TaxOrder::where('number', $number)->exists());

        return $number;
    }

    /**
     * @param  array<int>|null  $rubricIds  rubriques à recopier (null = toutes les rubriques actives)
     */
    public function create(User $by, string $effectiveDate, ?string $title = null, ?string $notes = null,
        bool $copyTariffs = true, ?array $rubricIds = null): TaxOrder
    {
        $effective = CarbonImmutable::parse($effectiveDate)->toDateString();

        return DB::transaction(function () use ($by, $effective, $title, $notes, $copyTariffs, $rubricIds) {
            $previous = TaxOrder::where('status', 'actif')->lockForUpdate()->get();
            foreach ($previous as $old) {
                $old->update(['status' => 'remplace', 'end_date' => $effective]);
                Tariff::where('tax_order_id', $old->id)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', $effective))
                    ->update(['end_date' => $effective, 'is_active' => false]);
            }

            $order = TaxOrder::create([
                'number' => $this->nextNumber(),
                'title' => $title ?: 'Arrêté Fiscal '.now()->year,
                'effective_date' => $effective,
                'notes' => $notes,
                'status' => 'actif',
                'created_by' => $by->id,
            ]);

            if ($copyTariffs) {
                $rubrics = Rubric::where('is_active', true)
                    ->when($rubricIds, fn ($q) => $q->whereIn('id', $rubricIds))->get();
                foreach ($rubrics as $rubric) {
                    $this->copyLatestTariffs($order, $rubric, placeholderIfNone: true);
                }
            }

            return $order;
        });
    }

    /** Derniers tarifs connus d'une rubrique : pour chaque libellé, celui à la date de début la plus récente. */
    public function latestTariffs(Rubric $rubric, ?int $exceptOrderId = null): Collection
    {
        return Tariff::where('rubric_id', $rubric->id)
            ->when($exceptOrderId, fn ($q) => $q->where(fn ($w) => $w->whereNull('tax_order_id')->orWhere('tax_order_id', '!=', $exceptOrderId)))
            ->orderBy('start_date', 'desc')->orderBy('id')
            ->get()
            ->unique('label')
            ->sortBy('id')
            ->values();
    }

    public function copyLatestTariffs(TaxOrder $order, Rubric $rubric, bool $placeholderIfNone = true): int
    {
        $created = 0;
        foreach ($this->latestTariffs($rubric, $order->id) as $t) {
            $order->tariffs()->create([
                'rubric_id' => $rubric->id,
                'code' => self::tariffCode($rubric->code, $t->label),
                'label' => $t->label,
                'value' => $t->value,
                'unit' => $t->unit,
                'start_date' => $order->effective_date,
                'end_date' => null,
                'surface_min' => $t->surface_min ?? 0,
                'surface_max' => $t->surface_max,
                'is_active' => true,
            ]);
            $created++;
        }

        if ($created === 0 && $placeholderIfNone) {
            $this->createPlaceholder($order, $rubric);
        }

        return $created;
    }

    /** Ajoute une rubrique absente de l'arrêté (avec ses derniers tarifs, ou un tarif à 0 à définir). */
    public function addRubric(TaxOrder $order, Rubric $rubric, bool $copyTariffs = true): int
    {
        if ($order->tariffs()->where('rubric_id', $rubric->id)->exists()) {
            throw new InvalidArgumentException("La rubrique {$rubric->module} est déjà présente dans cet arrêté.");
        }

        return DB::transaction(function () use ($order, $rubric, $copyTariffs) {
            if ($copyTariffs && ($copied = $this->copyLatestTariffs($order, $rubric, placeholderIfNone: false)) > 0) {
                return $copied;
            }
            $this->createPlaceholder($order, $rubric);

            return 1;
        });
    }

    protected function createPlaceholder(TaxOrder $order, Rubric $rubric): Tariff
    {
        return $order->tariffs()->create([
            'rubric_id' => $rubric->id,
            'code' => self::tariffCode($rubric->code, $rubric->label),
            'label' => $rubric->label,
            'value' => 0,
            'unit' => 'DH',
            'start_date' => $order->effective_date,
            'surface_min' => 0,
            'is_active' => true,
        ]);
    }

    /** Ajoute les tarifs types de référence manquants (comparaison sur le libellé). */
    public function loadDefaults(TaxOrder $order): int
    {
        $added = 0;
        foreach (self::DEFAULT_TARIFFS as $module => $items) {
            $rubric = Rubric::where('module', $module)->where('is_active', true)->first();
            if (! $rubric) {
                continue;
            }
            foreach ($items as [$label, $value, $unit]) {
                if ($order->tariffs()->where('rubric_id', $rubric->id)->where('label', $label)->exists()) {
                    continue;
                }
                $order->tariffs()->create([
                    'rubric_id' => $rubric->id, 'code' => self::tariffCode($rubric->code, $label), 'label' => $label,
                    'value' => $value, 'unit' => $unit, 'start_date' => $order->effective_date, 'surface_min' => 0, 'is_active' => true,
                ]);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Nouveau tarif : le tarif ouvert de même libellé dans la rubrique est clôturé à la date de début.
     */
    public function addTariff(TaxOrder $order, Rubric $rubric, array $data): Tariff
    {
        $start = ($data['start_date'] ?? null) ?: $order->effective_date->toDateString();
        $label = trim($data['label']);

        return DB::transaction(function () use ($order, $rubric, $data, $start, $label) {
            Tariff::where('rubric_id', $rubric->id)->where('label', $label)
                ->whereNull('end_date')->where('is_active', true)
                ->update(['end_date' => $start, 'is_active' => false]);

            return $order->tariffs()->create([
                'rubric_id' => $rubric->id,
                'code' => self::tariffCode($rubric->code, $label),
                'label' => $label,
                'value' => $data['value'],
                'unit' => ($data['unit'] ?? null) ?: 'DH',
                'start_date' => $start,
                'end_date' => ($data['end_date'] ?? null) ?: null,
                'surface_min' => ($data['surface_min'] ?? null) ?: 0,
                'surface_max' => ($data['surface_max'] ?? null) ?: null,
                'is_active' => true,
            ]);
        });
    }

    /** Modification : un tarif dont la date de fin est passée devient inactif. */
    public function updateTariff(Tariff $tariff, array $data): Tariff
    {
        $end = ($data['end_date'] ?? null) ?: null;
        $tariff->update([
            'value' => $data['value'],
            'unit' => ($data['unit'] ?? null) ?: 'DH',
            'start_date' => ($data['start_date'] ?? null) ?: $tariff->start_date,
            'end_date' => $end,
            'is_active' => ! ($end && CarbonImmutable::parse($end)->isBefore(today())),
            'surface_min' => ($data['surface_min'] ?? null) ?: 0,
            'surface_max' => ($data['surface_max'] ?? null) ?: null,
        ]);

        return $tariff;
    }

    public function deleteTariffs(Collection|array $ids): int
    {
        $ids = collect($ids)->all();
        // Flask : _detacher_tarifs_location — un groupe de tarif de location lié retombe sur sa propre valeur.
        DB::table('lease_tariffs')->whereIn('tariff_id', $ids)->update(['tariff_id' => null]);
        $count = 0;
        foreach (Tariff::whereIn('id', $ids)->get() as $tariff) {
            $tariff->delete();
            $count++;
        }

        return $count;
    }

    public function removeModule(TaxOrder $order, string $module): int
    {
        return $this->deleteTariffs($order->tariffs()->whereHas('rubric', fn ($q) => $q->where('module', $module))->pluck('id'));
    }

    public function clearTariffs(TaxOrder $order): int
    {
        return $this->deleteTariffs($order->tariffs()->pluck('id'));
    }

    /**
     * Suppression d'un arrêté (motif obligatoire, journalisé). Si c'était l'arrêté actif,
     * l'arrêté le plus récent (par date d'effet) redevient actif.
     */
    public function delete(TaxOrder $order, User $by, string $reason): int
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Le motif de suppression est obligatoire.');
        }

        return DB::transaction(function () use ($order, $by, $reason) {
            $count = $this->clearTariffs($order);
            $wasActive = $order->status === 'actif';
            activity('parametres')->causedBy($by)
                ->withProperties(['numero' => $order->number, 'tarifs' => $count, 'motif' => $reason])
                ->log("Suppression de l'arrêté fiscal {$order->number}");
            $order->delete();
            if ($wasActive) {
                TaxOrder::orderByDesc('effective_date')->first()?->update(['status' => 'actif']);
            }

            return $count;
        });
    }
}
