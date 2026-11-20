<?php

namespace App\Domain\Tnb\Services;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Tnb\Models\BuildingPermit;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\ParcelTransfer;
use App\Domain\Tnb\Models\TnbFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des terrains TNB — Flask modules/tnb.py (ajout, dossier par redevable, permis, transfert,
 * recensement, annulation d'une déclaration).
 */
class ParcelService
{
    /** Dossier TNB du redevable (un seul par contribuable), créé au besoin : n° = max + 1. */
    public function fileFor(int $taxpayerId): TnbFile
    {
        return DB::transaction(function () use ($taxpayerId) {
            $file = TnbFile::where('taxpayer_id', $taxpayerId)->lockForUpdate()->first();
            if ($file) {
                return $file;
            }
            $next = (int) TnbFile::lockForUpdate()->max('number') + 1;

            return TnbFile::create(['number' => $next, 'taxpayer_id' => $taxpayerId, 'is_archived' => false]);
        });
    }

    /** Prochain n° de terrain : plus grand n° numérique + 1 (Flask get_next_seq_num). */
    public static function nextNumber(): string
    {
        $max = (int) Parcel::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : Parcel::count() + 1);
    }

    /** Ajout d'un terrain ; hors recensement, le dossier du redevable est créé si besoin. */
    public function create(array $data, bool $census = false): Parcel
    {
        return DB::transaction(function () use ($data, $census) {
            if (! $census) {
                $this->fileFor((int) $data['taxpayer_id']);
            }

            return Parcel::create($data + ['number' => self::nextNumber(), 'is_active' => true, 'is_archived' => false, 'is_census' => $census]);
        });
    }

    /**
     * Permis : construction ➜ exonération de l'année d'autorisation à +2 (3 ans inclus), terrain « en construction » ;
     * habiter ➜ terrain « construit ».
     */
    public function addPermit(Parcel $parcel, array $data): BuildingPermit
    {
        return DB::transaction(function () use ($parcel, $data) {
            $start = $end = null;
            if ($data['type'] === 'construction') {
                if (! empty($data['authorized_on'])) {
                    $start = (int) substr($data['authorized_on'], 0, 4);
                    $end = $start + 2;
                }
                $parcel->update(['status' => 'en_construction']);
            } elseif ($data['type'] === 'habiter') {
                $parcel->update(['status' => 'construit']);
            }

            return BuildingPermit::create([
                'parcel_id' => $parcel->id, 'type' => $data['type'], 'number' => $data['number'] ?? null,
                'authorized_on' => $data['authorized_on'] ?: null, 'status' => 'en_cours', 'description' => $data['description'] ?? null,
                'exemption_start_year' => $start, 'exemption_end_year' => $end,
            ]);
        });
    }

    /** Transfert : le terrain rejoint le dossier du nouveau propriétaire (créé si absent). */
    public function transfer(Parcel $parcel, int $newTaxpayerId, User $agent, array $data = []): ParcelTransfer
    {
        if ($newTaxpayerId === (int) $parcel->taxpayer_id) {
            throw ValidationException::withMessages(['new_taxpayer_id' => 'Ce contribuable est déjà le propriétaire.']);
        }

        return DB::transaction(function () use ($parcel, $newTaxpayerId, $agent, $data) {
            $this->fileFor($newTaxpayerId);
            $transfer = ParcelTransfer::create([
                'parcel_id' => $parcel->id, 'from_taxpayer_id' => $parcel->taxpayer_id, 'to_taxpayer_id' => $newTaxpayerId,
                'transferred_on' => ($data['transferred_on'] ?? null) ?: today(), 'reason' => $data['reason'] ?? null,
                'notarial_deed' => $data['notarial_deed'] ?? null, 'agent_id' => $agent->id,
            ]);
            $parcel->update(['taxpayer_id' => $newTaxpayerId]);
            activity('parametres')->causedBy($agent)->performedOn($parcel)
                ->withProperties(['de' => $transfer->from_taxpayer_id, 'vers' => $newTaxpayerId])->log("Transfert du terrain {$parcel->number}");

            return $transfer;
        });
    }

    /** Recensement ➜ liste principale (le dossier du redevable est créé si besoin). */
    public function promoteCensus(Parcel $parcel): TnbFile
    {
        return DB::transaction(function () use ($parcel) {
            $file = $this->fileFor((int) $parcel->taxpayer_id);
            $parcel->update(['is_census' => false]);

            return $file;
        });
    }

    /**
     * Annuler une déclaration non payée (Flask la supprimait) : déclaration et bulletins non payés annulés
     * avec motif ; l'année redevient à déclarer.
     */
    public function cancelDeclaration(TaxDeclaration $declaration, User $by, string $reason): void
    {
        if ($declaration->status === 'paye') {
            throw ValidationException::withMessages(['reason' => 'Impossible d’annuler une déclaration déjà payée.']);
        }
        DB::transaction(function () use ($declaration, $by, $reason) {
            foreach ($declaration->paymentSlips()->where('status', '!=', 'paye')->whereNull('cancelled_at')->get() as $slip) {
                $slip->cancel($by, $reason);
            }
            $declaration->cancel($by, $reason);
        });
    }
}
