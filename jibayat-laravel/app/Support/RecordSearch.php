<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recherche globale dans les données (contribuables, dossiers de chaque taxe, déclarations, bulletins et quittances),
 * limitée aux taxes que l'utilisateur peut consulter.
 */
class RecordSearch
{
    /** Source ➜ [permission, table, colonnes cherchées, colonnes affichées, route, paramètre, icône, libellé]. */
    protected const SOURCES = [
        ['contribuables.voir', 'taxpayers', ['number', 'last_name', 'first_name', 'company_name', 'last_name_ar', 'cin', 'ice', 'phone'], 'caisse.fiche-contribuable', 'ctb', 'id-card', 'Contribuable'],
        ['tnb.voir', 'parcels', ['number', 'land_title', 'parcel_number', 'subdivision'], 'tnb.fiche', 'terrain', 'map-pin', 'Terrain TNB'],
        ['boissons.voir', 'beverage_outlets', ['number', 'name', 'authorization_number'], 'boissons.fiche', 'etablissement', 'coffee', 'Débit de boissons'],
        ['transport.voir', 'vehicles', ['number', 'registration', 'authorization_number'], 'transport.fiche', 'vehicule', 'bus', 'Véhicule'],
        ['locations.voir', 'leases', ['number', 'premises_ref'], 'locations.fiche', 'bail', 'store', 'Bail'],
        ['souk.voir', 'souk_contracts', ['number', 'souk_name', 'pitch_number'], 'souk.fiche', 'contrat', 'shopping-basket', 'Contrat de souk'],
        ['domaine_public.voir', 'public_occupations', ['number', 'location', 'authorization_number'], 'domaine-public.fiche', 'occupation', 'tent', 'Occupation ODP'],
        ['fourriere.voir', 'impound_cases', ['number', 'registration', 'owner_name', 'owner_cin', 'deposit_number'], 'fourriere.fiche', 'dossier', 'car', 'Dossier fourrière'],
    ];

    public function search(User $user, string $query, int $perSource = 5): Collection
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return collect();
        }
        $out = collect();
        foreach (self::SOURCES as [$perm, $table, $cols, $route, $param, $icon, $label]) {
            if (! $user->can($perm)) {
                continue;
            }
            $sel = DB::table("$table as x")->leftJoin('taxpayers as t', 't.id', '=', $table === 'taxpayers' ? 'x.id' : 'x.taxpayer_id')
                ->where(fn ($w) => collect($cols)->each(fn ($c) => $w->orWhere("x.$c", 'like', "%$q%")))
                ->when($table !== 'taxpayers', fn ($w) => $w->orWhere(fn ($o) => $o->where('t.cin', 'like', "%$q%")->orWhere('t.last_name', 'like', "%$q%")->orWhere('t.company_name', 'like', "%$q%")))
                ->orderByDesc('x.id')->limit($perSource)
                ->get(array_merge(['x.id', 'x.number', 't.last_name', 't.first_name', 't.company_name', 't.cin'], array_map(fn ($c) => "x.$c as c_$c", array_slice($cols, 1, 2))));
            foreach ($sel as $r) {
                $who = trim(($r->last_name ?? '').' '.($r->first_name ?? '')) ?: ($r->company_name ?? '');
                $extra = collect(array_slice($cols, 1, 2))->map(fn ($c) => $r->{"c_$c"} ?? null)->filter()->implode(' · ');
                $out->push(['type' => $label, 'icon' => $icon, 'title' => $table === 'taxpayers' ? ($who ?: $r->number) : trim(($r->number ?? '').' '.($table === 'taxpayers' ? '' : $extra)),
                    'subtitle' => $table === 'taxpayers' ? trim($r->number.($r->cin ? ' · CIN '.$r->cin : '')) : ($who ?: '—'),
                    'url' => route($route, [$param => $r->id])]);
            }
        }
        if ($user->can('registres.voir') || $user->can('contribuables.voir')) {
            $docs = DB::table('payment_slips as s')->leftJoin('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')->leftJoin('taxpayers as t', 't.id', '=', 's.taxpayer_id')
                ->where(fn ($w) => $w->where('s.slip_number', 'like', "%$q%")->orWhere('s.receipt_number', 'like', "%$q%")->orWhere('d.number', 'like', "%$q%"))
                ->orderByDesc('s.id')->limit($perSource)->get(['s.slip_number', 's.receipt_number', 's.amount', 's.status', 'd.number as decl', 'd.module', 't.id as taxpayer_id', 't.last_name', 't.first_name', 't.company_name']);
            foreach ($docs as $d) {
                $who = trim(($d->last_name ?? '').' '.($d->first_name ?? '')) ?: ($d->company_name ?? '');
                $out->push(['type' => 'Pièce', 'icon' => 'receipt', 'title' => 'BV '.$d->slip_number.($d->receipt_number ? ' · quittance '.$d->receipt_number : ''),
                    'subtitle' => trim(($d->decl ?? '').' · '.Format::money($d->amount).' DH'.($who ? ' · '.$who : '')),
                    'url' => $d->taxpayer_id ? route('caisse.fiche-contribuable', ['ctb' => $d->taxpayer_id]) : route('documents.grand-livre', ['q' => $d->slip_number])]);
            }
        }

        return $out;
    }
}
