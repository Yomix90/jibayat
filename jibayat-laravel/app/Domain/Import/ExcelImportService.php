<?php

namespace App\Domain\Import;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Documents\Services\SpreadsheetReader;
use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Domain\Tnb\Services\ParcelService;
use App\Domain\Transport\Models\Vehicle;
use App\Models\ImportLog;
use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Importation Excel multi-modules — portage de Flask modules/importation.py :
 * modèles téléchargeables, lecture des en-têtes (correspondance exacte puis par mots-clés), aperçu avec contrôles,
 * rapprochement des contribuables (CIN, ICE, nom + prénom) ou création, tarif de l'arrêté fiscal en vigueur, journal.
 */
class ExcelImportService
{
    public const MODULES = [
        'DEBITS_BOISSONS' => ['nom' => 'Débits de boissons', 'table' => 'Établissements de boissons', 'periodicite' => 'trimestrielle', 'colonnes' => [
            ['cin', 'CIN Propriétaire *', true, "N° CIN ou passeport du propriétaire / exploitant"], ['nom', 'Nom Propriétaire *', true, 'Nom de famille ou raison sociale'],
            ['prenom', 'Prénom Propriétaire', false, 'Prénom (si personne physique)'], ['telephone', 'Téléphone', false, 'Numéro de contact GSM'],
            ['numero', 'N° Dossier Établissement', false, 'Laisser vide pour attribution auto'], ['nom_etablissement', 'Nom Établissement *', true, 'Nom commercial (ex : Café Ennasr)'],
            ['type_etablissement', 'Type Établissement *', true, 'cafe / restaurant / bar / hotel / salon_the'], ['adresse', 'Adresse Établissement', false, 'Adresse exacte ou quartier'],
            ['superficie', 'Superficie (m²)', false, 'Superficie exploitée en m²'], ['numero_autorisation', 'N° Autorisation', false, 'N° de la décision administrative'],
            ['date_autorisation', 'Date Autorisation (AAAA-MM-JJ)', false, "Date d'octroi (ex : 2024-01-15)"],
            ['trimestre_debut_imposition', 'Trimestre Début * (1-4)', true, "Trimestre d'origine de la dette (1, 2, 3 ou 4)"],
            ['annee_debut_imposition', 'Année Début * (AAAA)', true, 'Année à partir de laquelle débute le calcul des impayés (ex : 2024)'],
        ], 'exemples' => [
            ['AB123456', 'BENNANI', 'Rachid', '0661234567', '', 'Café Al Andalus', 'cafe', 'Avenue Mohammed V N° 45', 85.0, 'AUT-2023-01', '2023-01-10', 1, 2023],
            ['CD789012', 'EL AMRANI', 'Fatima', '0663456789', '', 'Restaurant Marrakech', 'restaurant', 'Boulevard Hassan II N° 12', 140.0, 'AUT-2024-18', '2024-03-01', 2, 2024],
        ], 'valeurs' => ['type_etablissement' => ['cafe', 'salon_the', 'restaurant', 'bar', 'hotel']]],
        'TNB' => ['nom' => 'Terrains non bâtis (TNB)', 'table' => 'Terrains urbains non bâtis', 'periodicite' => 'annuelle', 'colonnes' => [
            ['cin', 'CIN Redevable *', true, 'N° CIN ou RC du redevable'], ['nom', 'Nom Redevable *', true, 'Nom de famille ou société'],
            ['prenom', 'Prénom Redevable', false, 'Prénom du contribuable'], ['telephone', 'Téléphone', false, 'Téléphone de contact'],
            ['numero_terrain', 'N° Dossier Terrain', false, 'Laisser vide pour auto-incrément'], ['titre_foncier', 'Titre Foncier', false, 'Ex : 12345/67 (ou R-990)'],
            ['num_parcelle', 'N° Parcelle / Lot', false, 'Ex : Lot N° 14'], ['superficie', 'Superficie m² *', true, 'Superficie taxable en m²'],
            ['zone', 'Zone Fiscale * (A/B/C/D)', true, 'Zone tarifaire (ex : A, B, C, D)'], ['adresse', 'Adresse / Localisation', false, 'Emplacement du terrain'],
            ['lotissement', 'Lotissement', false, 'Nom du lotissement'], ['quartier', 'Quartier', false, 'Nom du quartier ou secteur'],
            ['statut', 'Statut *', true, 'non_bati / en_construction / construit'], ['date_acquisition', 'Date Acquisition (AAAA-MM-JJ)', false, "Date d'acte ou d'achat"],
            ['annee_debut_imposition', 'Année Début Taxation * (AAAA)', true, 'Année de départ pour le calcul des dettes TNB (ex : 2022)'],
        ], 'exemples' => [
            ['BK456789', 'CHRAIBI', 'Karim', '0662112233', '', '88234/09', 'Lot 45', 450.0, 'B', 'Quartier Ennakhil, Rue 12', 'Lotissement Al Houda', 'Ennakhil', 'non_bati', '2022-04-15', 2022],
            ['T987654', 'TAZI', 'Amina', '0664998877', '', '99341/09', 'Lot 88', 320.0, 'A', 'Avenue des FAR', 'Zone Villa', 'Al Qods', 'non_bati', '2024-02-10', 2024],
        ], 'valeurs' => ['zone' => ['A', 'B', 'C', 'D'], 'statut' => ['non_bati', 'en_construction', 'construit']]],
        'STATIONNEMENT' => ['nom' => 'Stationnement & TPV', 'table' => 'Véhicules de transport & stationnement', 'periodicite' => 'trimestrielle', 'colonnes' => [
            ['cin', 'CIN Transporteur *', true, "N° CIN ou ICE de l'exploitant"], ['nom', 'Nom Transporteur *', true, 'Nom ou raison sociale'],
            ['prenom', 'Prénom', false, 'Prénom si personne physique'], ['telephone', 'Téléphone', false, 'Numéro de contact'],
            ['numero', 'N° Dossier Véhicule', false, 'Attribution automatique si vide'], ['immatriculation', 'Immatriculation *', true, 'Ex : 12345-A-26 ou 6789-B-1'],
            ['type_vehicule', 'Catégorie Véhicule *', true, 'petit_taxi / grand_taxi / minibus / autobus / transport_mixte / camion'],
            ['num_autorisation', 'N° Agrément / Autorisation', false, "N° d'agrément préfectoral"], ['date_autorisation', 'Date Agrément (AAAA-MM-JJ)', false, "Date d'octroi de la licence"],
            ['nombre_sieges', 'Nombre de Sièges', false, 'Nombre de places assises'],
            ['trimestre_debut_imposition', 'Trimestre Début * (1-4)', true, "Trimestre initial d'exigibilité (1-4)"],
            ['annee_debut_imposition', 'Année Début * (AAAA)', true, "Année d'origine de la redevance (ex : 2023)"],
        ], 'exemples' => [
            ['JA112233', 'MANSOURI', 'Hassan', '0661889900', '', '14523-A-26', 'petit_taxi', 'AGR-PT-142', '2021-06-01', 3, 1, 2023],
            ['JA445566', 'ZAHIR', 'Omar', '0662778899', '', '8892-B-26', 'grand_taxi', 'AGR-GT-088', '2022-01-15', 6, 2, 2024],
        ], 'valeurs' => ['type_vehicule' => ['petit_taxi', 'grand_taxi', 'minibus', 'autobus', 'transport_mixte', 'camion']]],
        'OCCUPATION_DOMAINE' => ['nom' => 'Occupation du domaine public (ODP)', 'table' => 'Autorisations ODP', 'periodicite' => 'trimestrielle', 'colonnes' => [
            ['cin', 'CIN Bénéficiaire *', true, 'N° CIN ou ICE'], ['nom', 'Nom Bénéficiaire *', true, 'Nom de famille ou société'],
            ['prenom', 'Prénom', false, 'Prénom si particulier'], ['telephone', 'Téléphone', false, 'Contact téléphonique'],
            ['numero', 'N° Dossier ODP', false, 'Généré automatiquement si vide'],
            ['type_occupation', 'Type Occupation *', true, 'terrasse_ouverte / terrasse_fermee / kiosque / etalage / panneau_pub / materiaux_chantier'],
            ['localisation', 'Localisation / Adresse *', true, "Emplacement de l'espace public occupé"], ['superficie', 'Superficie m² *', true, 'Surface occupée en m² ou m linéaires'],
            ['num_autorisation', 'N° Décision ODP', false, "N° de l'arrêté communal"], ['date_debut', 'Date Début Validité (AAAA-MM-JJ)', false, "Date d'effet"],
            ['date_fin', 'Date Fin Validité (AAAA-MM-JJ)', false, "Date d'échéance"], ['trimestre_debut_imposition', 'Trimestre Début * (1-4)', true, '1, 2, 3 ou 4'],
            ['annee_debut_imposition', 'Année Début * (AAAA)', true, 'Année à partir de laquelle le calcul débute (ex : 2024)'],
        ], 'exemples' => [
            ['AB123456', 'BENNANI', 'Rachid', '0661234567', '', 'terrasse_ouverte', 'Devant Café Al Andalus, Av Med V', 25.0, 'ODP-2023-45', '2023-01-01', '2025-12-31', 1, 2023],
            ['CD998877', 'OUKACHA', 'Brahim', '0665443322', '', 'etalage', 'Marché Central Magasin N° 5', 6.5, 'ODP-2024-12', '2024-01-01', '2024-12-31', 1, 2024],
        ], 'valeurs' => ['type_occupation' => ['terrasse_ouverte', 'terrasse_fermee', 'kiosque', 'etalage', 'materiaux_chantier', 'panneau_pub']]],
        'CONTRIBUABLES' => ['nom' => 'Contribuables & redevables', 'table' => 'Répertoire des contribuables', 'periodicite' => 'aucune', 'colonnes' => [
            ['numero', 'N° Contribuable', false, 'Attribution séquentielle si vide'], ['type_personne', 'Type Personne * (physique/morale)', true, 'physique ou morale'],
            ['nom', 'Nom / Raison Sociale *', true, 'Nom de famille ou raison sociale'], ['prenom', 'Prénom', false, 'Prénom pour personne physique'],
            ['cin', 'N° CIN / Passeport', false, 'Recommandé pour personne physique'], ['ice', 'N° ICE', false, 'Obligatoire pour les sociétés (15 chiffres)'],
            ['rc', 'N° RC', false, 'Registre du commerce'], ['identifiant_fiscal', 'Identifiant Fiscal (IF)', false, 'IF Direction générale des impôts'],
            ['telephone', 'Téléphone', false, 'GSM ou fixe'], ['email', 'Email', false, 'Courrier électronique'], ['adresse', 'Adresse', false, 'Adresse de domiciliation'],
        ], 'exemples' => [
            ['', 'physique', 'ALAMI', 'Hicham', 'AA123456', '', '', '', '0661122334', 'alami@gmail.com', 'Avenue Mohammed V N° 10, Centre Ville'],
            ['', 'morale', 'STE ATLAS TRAVAUX SARL', '', '', '001524389000045', '12456', '33445566', '0528223344', 'contact@atlastravaux.ma', 'Zone Industrielle Lot 58'],
        ], 'valeurs' => ['type_personne' => ['physique', 'morale']]],
    ];

    /** Mots-clés de rapprochement type ➜ tarif (Flask _get_tarif_for_row). */
    protected const KEYWORDS = [
        'DEBITS_BOISSONS' => ['cafe' => ['café', 'cafe', 'salon', 'thé', 'the', 'buvette'], 'salon_the' => ['salon', 'thé', 'the', 'glacier', 'café', 'cafe'],
            'restaurant' => ['restaurant', 'resto', 'snack', 'pizzeria'], 'bar' => ['bar', 'brasserie', 'alcool'], 'brasserie' => ['brasserie', 'bar', 'restaurant'], 'hotel' => ['hôtel', 'hotel']],
        'STATIONNEMENT' => ['grand_taxi' => ['grand taxi', 'grand', 'taxi 1'], 'petit_taxi' => ['petit taxi', 'petit', 'taxi 2'], 'minibus' => ['minibus', 'autocar', 'bus'],
            'autobus' => ['autobus', 'autocar', 'minibus', 'bus'], 'transport_mixte' => ['mixte', 'transport'], 'camion' => ['camion', 'poids lourd', 'engin']],
        'OCCUPATION_DOMAINE' => ['terrasse_ouverte' => ['terrasse', 'étalage', 'etalage', 'café'], 'terrasse_fermee' => ['terrasse', 'fermee', 'ferme', 'couverte'],
            'kiosque' => ['kiosque', 'installation fixe'], 'etalage' => ['étalage', 'etalage', 'devanture'], 'materiaux_chantier' => ['chantier', 'matériaux', 'materiaux', 'dépôt'],
            'panneau_pub' => ['panneau', 'publicitaire', 'enseigne', 'pub']],
    ];

    protected const TYPE_FIELD = ['TNB' => 'zone', 'DEBITS_BOISSONS' => 'type_etablissement', 'STATIONNEMENT' => 'type_vehicule', 'OCCUPATION_DOMAINE' => 'type_occupation'];

    protected const DEFAULT_UNIT = ['TNB' => 'DH/m²', 'DEBITS_BOISSONS' => '%', 'STATIONNEMENT' => 'DH/an', 'OCCUPATION_DOMAINE' => 'DH/m²/an'];

    /** Flask _clean_key. */
    public static function cleanKey(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[\*\(\)\?\:\.\,\;\/\\\\]/u', ' ', $s);
        $s = str_replace(['é', 'è', 'ê', 'à', 'ç'], ['e', 'e', 'e', 'a', 'c'], $s);

        return trim(preg_replace('/\s+/u', '_', $s), '_');
    }

    protected static function value(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return trim(SpreadsheetReader::str($v));
    }

    /** Flask _parse_uploaded_file : lignes normalisées {champ: valeur, _ligne_excel}. */
    public function parse(string $path, string $module): array
    {
        $raw = SpreadsheetReader::rows($path);
        if (! $raw) {
            throw new \RuntimeException('Le fichier Excel est vide.');
        }
        $headers = array_shift($raw);
        $cols = self::MODULES[$module]['colonnes'];
        $map = [];
        foreach ($headers as $i => $h) {
            if ($h === null) {
                continue;
            }
            $c = self::cleanKey((string) $h);
            foreach ($cols as [$field, $label]) {
                if ($c === $field || $c === self::cleanKey($label)) {
                    $map[$i] = $field;
                    break;
                }
            }
        }
        $mapped = array_flip($map);
        foreach ($headers as $i => $h) {
            if (isset($map[$i]) || $h === null) {
                continue;
            }
            $c = self::cleanKey((string) $h);
            foreach ($cols as [$field, $label]) {
                if (isset($mapped[$field])) {
                    continue;
                }
                $l = self::cleanKey($label);
                if (str_contains($c, $l) || str_contains($l, $c) || str_contains($c, $field)) {
                    $map[$i] = $field;
                    $mapped[$field] = $i;
                    break;
                }
            }
        }
        $rows = [];
        foreach ($raw as $n => $r) {
            if (! array_filter($r, fn ($v) => $v !== null && $v !== '' && $v !== 0 && $v !== 0.0)) {
                continue;
            }
            $row = ['_ligne_excel' => $n + 2];
            foreach ($r as $i => $v) {
                if (isset($map[$i])) {
                    $row[$map[$i]] = self::value($v);
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** Arrêté fiscal en vigueur et ses tarifs pour le module (Flask index / apercu). */
    public function activeTariffs(string $module): array
    {
        $order = DB::table('tax_orders')->where('status', 'actif')->orderByDesc('effective_date')->orderByDesc('id')->first()
            ?? DB::table('tax_orders')->orderByDesc('effective_date')->orderByDesc('id')->first();
        if (! $order) {
            return [null, collect()];
        }
        $today = today()->toDateString();
        $tariffs = DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')->where('t.tax_order_id', $order->id)
            ->where(fn ($q) => $q->where('r.module', $module)->orWhere('r.code', $module))
            ->where(fn ($q) => $q->where('t.is_active', true)->orWhereNull('t.end_date')->orWhere('t.end_date', '>=', $today))
            ->orderBy('t.label')->get(['t.id', 't.label', 't.code', 't.value', 't.unit']);

        return [$order, $tariffs];
    }

    protected static function tariffResult(object $t, bool $found, string $defaultUnit): array
    {
        $unit = $t->unit ?: $defaultUnit;
        $v = (float) $t->value;

        return ['id' => $t->id, 'libelle' => $t->label, 'valeur' => $v, 'unite' => $unit, 'trouve' => $found, 'code' => (string) $t->code,
            'texte' => $t->label.' ('.rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.').' '.$unit.')'];
    }

    /** Flask _get_tarif_for_row. */
    public static function tariffFor(string $module, array $row, Collection $tariffs): array
    {
        if ($tariffs->isEmpty() || ! isset(self::TYPE_FIELD[$module])) {
            return ['id' => null, 'libelle' => $tariffs->isEmpty() ? 'Tarif standard (non défini)' : 'Standard', 'valeur' => 0.0, 'unite' => '',
                'texte' => $tariffs->isEmpty() ? 'Non configuré dans l’arrêté' : 'Standard', 'trouve' => false, 'code' => ''];
        }
        $unit = self::DEFAULT_UNIT[$module];
        $value = trim((string) ($row[self::TYPE_FIELD[$module]] ?? ''));
        if ($module === 'TNB') {
            $zone = mb_strtoupper($value ?: 'A');
            foreach ($tariffs as $t) {
                [$lib, $code] = [mb_strtoupper((string) $t->label), mb_strtoupper((string) $t->code)];
                if ($lib === $zone || $code === $zone || $lib === "ZONE $zone" || str_contains($code, "-$zone")) {
                    return self::tariffResult($t, true, $unit);
                }
            }
            foreach ($tariffs as $t) {
                if (str_contains(mb_strtoupper((string) $t->label), $zone)) {
                    return self::tariffResult($t, true, $unit);
                }
            }

            return self::tariffResult($tariffs->first(), false, $unit);
        }
        $v = mb_strtolower($value);
        foreach ($tariffs as $t) {
            [$lib, $code] = [mb_strtolower((string) $t->label), mb_strtolower((string) $t->code)];
            if ($v !== '' && ($v === $lib || $v === $code || str_contains($lib, $v) || str_contains($code, $v))) {
                return self::tariffResult($t, true, $unit);
            }
        }
        $fallback = ['DEBITS_BOISSONS' => ['café'], 'STATIONNEMENT' => ['taxi'], 'OCCUPATION_DOMAINE' => ['terrasse']][$module];
        $keywords = $v !== '' ? (self::KEYWORDS[$module][$v] ?? [$v]) : $fallback;
        foreach ($tariffs as $t) {
            [$lib, $code] = [mb_strtolower((string) $t->label), mb_strtolower((string) $t->code)];
            foreach ($keywords as $k) {
                if (str_contains($lib, $k) || str_contains($code, $k)) {
                    return self::tariffResult($t, true, $unit);
                }
            }
        }

        return self::tariffResult($tariffs->first(), false, $unit);
    }

    /** Erreurs d'une ligne (Flask apercu). */
    public static function rowErrors(string $module, array $r): array
    {
        $e = [];
        $g = fn ($k) => trim((string) ($r[$k] ?? ''));
        if ($g('nom') === '' && $g('cin') === '' && $g('ice') === '') {
            $e[] = 'Nom ou CIN obligatoire.';
        }
        $check = function (string $field, string $missing, array $allowed, string $expected) use ($g, &$e) {
            $v = mb_strtolower($g($field));
            if ($v === '') {
                $e[] = $missing;
            } elseif (! in_array($v, $allowed, true)) {
                $e[] = "Type « $v » non reconnu (attendu : $expected).";
            }
        };
        match ($module) {
            'DEBITS_BOISSONS' => [$g('nom_etablissement') === '' ? $e[] = 'Nom établissement manquant.' : null,
                $check('type_etablissement', 'Type établissement manquant.', ['cafe', 'salon_the', 'restaurant', 'bar', 'hotel', 'brasserie'], 'cafe, restaurant, bar, hotel, salon_the')],
            'TNB' => (function () use ($g, &$e) {
                if ($g('superficie') === '' || (float) $g('superficie') == 0) {
                    $e[] = 'Superficie m² manquante.';
                }
                $z = mb_strtoupper($g('zone'));
                if ($z === '') {
                    $e[] = 'Zone fiscale manquante (attendu : A, B, C ou D).';
                } elseif (! in_array($z, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $e[] = "Zone « $z » non reconnue (attendu : A, B, C ou D).";
                }
            })(),
            'STATIONNEMENT' => [$g('immatriculation') === '' ? $e[] = 'Immatriculation requise.' : null,
                $check('type_vehicule', 'Catégorie véhicule manquante.', self::MODULES['STATIONNEMENT']['valeurs']['type_vehicule'], 'petit_taxi, grand_taxi, minibus…')],
            'OCCUPATION_DOMAINE' => [($g('superficie') === '' || (float) $g('superficie') == 0 || $g('localisation') === '') ? $e[] = 'Localisation ou superficie ODP manquante.' : null,
                $check('type_occupation', 'Type occupation manquant.', self::MODULES['OCCUPATION_DOMAINE']['valeurs']['type_occupation'], 'terrasse_ouverte, kiosque, etalage…')],
            default => null,
        };

        return $e;
    }

    protected function findTaxpayer(array $r): ?Taxpayer
    {
        $cin = mb_strtoupper(trim((string) ($r['cin'] ?? '')));
        $ice = trim((string) ($r['ice'] ?? ''));
        $nom = trim((string) ($r['nom'] ?? ''));
        $prenom = trim((string) ($r['prenom'] ?? ''));

        return ($cin !== '' ? Taxpayer::where('cin', $cin)->first() : null)
            ?? ($ice !== '' ? Taxpayer::where('ice', $ice)->first() : null)
            ?? ($nom !== '' && $prenom !== '' ? Taxpayer::where('last_name', $nom)->where('first_name', $prenom)->first() : null);
    }

    /** Aperçu (Flask apercu) : contrôles, rapprochement du contribuable, tarif applicable. */
    public function preview(string $path, string $module): array
    {
        $rows = $this->parse($path, $module);
        [$order, $tariffs] = $this->activeTariffs($module);
        $out = [];
        foreach ($rows as $r) {
            $t = $this->findTaxpayer(['nom' => '', 'prenom' => ''] + $r);
            $tariff = self::tariffFor($module, $r, $tariffs);
            $errors = self::rowErrors($module, $r);
            $out[] = $r + ['_erreurs' => $errors, '_est_valide' => ! $errors,
                '_ctb_statut' => $t ? "Existant : {$t->displayName()} ({$t->number})" : 'Nouveau (création automatique)',
                '_tarif_applicable' => $tariff['texte'], '_tarif_trouve' => $tariff['trouve']];
        }

        return ['rows' => $out, 'total' => count($out), 'valides' => count(array_filter($out, fn ($r) => $r['_est_valide'])),
            'arrete' => $order?->number ?? 'Initial'];
    }

    protected static function nextNumeric(string $table, string $column = 'number'): string
    {
        $max = (int) DB::table($table)->whereRaw("$column REGEXP '^[0-9]+$'")->max(DB::raw("CAST($column AS UNSIGNED)"));

        return (string) ($max > 0 ? $max + 1 : DB::table($table)->count() + 1);
    }

    protected static function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return \Carbon\CarbonImmutable::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Exécution (Flask executer) : chaque ligne dans son propre point de reprise ; journal des imports. */
    public function execute(string $path, string $module, string $fileName, User $user): array
    {
        $rows = $this->parse($path, $module);
        [, $tariffs] = $this->activeTariffs($module);
        $ok = $created = 0;
        $errors = [];
        $municipality = Municipality::value('id');
        $numbers = app(NumberGenerator::class);
        DB::transaction(function () use ($rows, $module, $tariffs, &$ok, &$created, &$errors, $municipality, $numbers) {
            foreach ($rows as $r) {
                $line = $r['_ligne_excel'];
                try {
                    DB::transaction(function () use ($r, $module, $tariffs, &$created, $municipality, $numbers) {
                        $g = fn ($k) => trim((string) ($r[$k] ?? ''));
                        if ($rowErrors = self::rowErrors($module, $r)) {
                            throw new \RuntimeException(implode(' ', $rowErrors));
                        }
                        $t = $this->findTaxpayer($r);
                        if (! $t) {
                            $name = $g('nom') ?: 'Contribuable '.mb_strtoupper($g('cin'));
                            $type = $module === 'CONTRIBUABLES' && in_array($g('type_personne'), ['physique', 'morale'], true) ? $g('type_personne')
                                : ((str_contains(mb_strtolower($name), 'ste') || str_contains(mb_strtolower($name), 'sarl') || $g('ice') !== '') ? 'morale' : 'physique');
                            $t = Taxpayer::create(['number' => $module === 'CONTRIBUABLES' && $g('numero') !== '' ? $g('numero') : $numbers->next('taxpayers', 'number', 'CTB'),
                                'person_type' => $type, 'last_name' => $type === 'morale' ? null : $name, 'company_name' => $type === 'morale' ? $name : null,
                                'first_name' => $g('prenom') ?: null, 'cin' => mb_strtoupper($g('cin')) ?: null, 'ice' => $g('ice') ?: null, 'rc' => $g('rc') ?: null,
                                'phone' => $g('telephone') ?: null, 'email' => $g('email') ?: null, 'address' => $g('adresse') ?: null, 'is_active' => true, 'municipality_id' => $municipality]);
                            $created++;
                        }
                        $year = (int) ((float) $g('annee_debut_imposition') ?: now()->year);
                        $quarter = (int) (float) $g('trimestre_debut_imposition');
                        $quarter = in_array($quarter, [1, 2, 3, 4], true) ? $quarter : 1;
                        $tariff = self::tariffFor($module, $r, $tariffs);
                        $base = ['taxpayer_id' => $t->id, 'municipality_id' => $municipality, 'tax_start_year' => $year, 'is_active' => true];
                        match ($module) {
                            'DEBITS_BOISSONS' => BeverageOutlet::create($base + ['number' => $g('numero') ?: self::nextNumeric('beverage_outlets'),
                                'name' => $g('nom_etablissement') ?: 'Établissement '.$g('nom'), 'type' => $tariff['id'] ? $tariff['libelle'] : mb_strtolower($g('type_etablissement')),
                                'tariff_id' => $tariff['id'], 'address' => $g('adresse') ?: null, 'area' => (float) $g('superficie'), 'authorization_number' => $g('numero_autorisation') ?: null,
                                'authorized_on' => self::date($g('date_autorisation')), 'tax_start_quarter' => $quarter, 'status' => 'actif']),
                            'TNB' => app(ParcelService::class)->create(array_diff_key($base, ['is_active' => 1]) + ['land_title' => $g('titre_foncier') ?: null,
                                'parcel_number' => $g('num_parcelle') ?: null, 'area' => (float) $g('superficie'), 'zone' => mb_strtoupper($g('zone')),
                                'address' => $g('adresse') ?: null, 'subdivision' => $g('lotissement') ?: null, 'district' => $g('quartier') ?: null,
                                'status' => in_array(mb_strtolower($g('statut')), ['non_bati', 'en_construction', 'construit'], true) ? mb_strtolower($g('statut')) : 'non_bati',
                                'acquired_on' => self::date($g('date_acquisition'))]),
                            'STATIONNEMENT' => Vehicle::create($base + ['number' => $g('numero') ?: self::nextNumeric('vehicles'), 'registration' => mb_strtoupper($g('immatriculation')),
                                'type' => mb_strtolower($g('type_vehicule')), 'authorization_number' => $g('num_autorisation') ?: null, 'authorized_on' => self::date($g('date_autorisation')),
                                'seats' => (int) (float) $g('nombre_sieges'), 'tax_start_quarter' => $quarter, 'status' => 'actif']),
                            'OCCUPATION_DOMAINE' => PublicOccupation::create($base + ['number' => $g('numero') ?: self::nextNumeric('public_occupations'),
                                'type' => $tariff['id'] ? $tariff['libelle'] : $g('type_occupation'), 'location' => $g('localisation'), 'area' => (float) $g('superficie'),
                                'authorization_number' => $g('num_autorisation') ?: null, 'starts_on' => self::date($g('date_debut')), 'ends_on' => self::date($g('date_fin')),
                                'tax_start_quarter' => $quarter, 'status' => 'actif']),
                            default => null,
                        };
                    });
                    $ok++;
                } catch (\Throwable $e) {
                    $errors[] = "Ligne $line : ".$e->getMessage();
                }
            }
        });
        $log = ImportLog::create(['module' => $module, 'file_name' => $fileName, 'total_rows' => count($rows), 'success_count' => $ok, 'error_count' => count($errors),
            'details' => array_slice($errors, 0, 50), 'agent_id' => $user->id]);
        activity('importation')->causedBy($user)->performedOn($log)->withProperties(['succes' => $ok, 'erreurs' => count($errors), 'contribuables_crees' => $created])
            ->log('Import Excel '.self::MODULES[$module]['nom']);

        return ['total' => count($rows), 'success' => $ok, 'created' => $created, 'errors' => $errors];
    }

    /** Modèle Excel (Flask telecharger_modele) : feuille de données avec listes déroulantes, feuille guide. */
    public function template(string $module): Spreadsheet
    {
        $cfg = self::MODULES[$module];
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Données à importer');
        $ws->fromArray(array_column($cfg['colonnes'], 1));
        foreach ($cfg['colonnes'] as $i => [$field, $label]) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $ws->getStyle($col.'1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => str_contains($label, 'Début') ? 'C9A13B' : '1E2A3A']], 'alignment' => ['wrapText' => true]]);
            $ws->getColumnDimension($col)->setWidth(max(15, mb_strlen($label) + 4));
            $list = $cfg['valeurs'][$field] ?? ($field === 'trimestre_debut_imposition' ? ['1', '2', '3', '4'] : null);
            if ($list) {
                $dv = $ws->getCell($col.'2')->getDataValidation();
                $dv->setType(DataValidation::TYPE_LIST)->setAllowBlank(false)->setShowDropDown(true)->setShowErrorMessage(true)
                    ->setErrorTitle('Valeur invalide')->setError('Choisissez une valeur de la liste.')->setFormula1('"'.implode(',', $list).'"');
                $ws->setDataValidation($col.'2:'.$col.'1000', $dv);
            }
        }
        $ws->fromArray($cfg['exemples'], null, 'A2');
        $ws->getStyle('A2:'.$ws->getHighestColumn().(count($cfg['exemples']) + 1))->getFont()->setItalic(true)->getColor()->setRGB('475569');
        $guide = $book->createSheet()->setTitle('Guide & règles');
        $guide->fromArray(['Colonne', 'Obligatoire', 'Description']);
        $guide->getStyle('A1:C1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E2A3A']]]);
        foreach ($cfg['colonnes'] as $i => [, $label, $required, $desc]) {
            $guide->fromArray([$label, $required ? 'OUI' : 'Optionnel', $desc], null, 'A'.($i + 2));
        }
        [$order, $tariffs] = $this->activeTariffs($module);
        if ($tariffs->isNotEmpty()) {
            $r = count($cfg['colonnes']) + 4;
            $guide->setCellValue("A$r", 'Tarifs de l’arrêté fiscal '.$order->number.' :');
            $guide->getStyle("A$r")->getFont()->setBold(true);
            foreach ($tariffs as $j => $t) {
                $guide->fromArray([$t->label, rtrim(rtrim(number_format((float) $t->value, 4, '.', ''), '0'), '.').' '.$t->unit, $t->code], null, 'A'.($r + 1 + $j));
            }
        }
        foreach (['A' => 34, 'B' => 16, 'C' => 70] as $c => $w) {
            $guide->getColumnDimension($c)->setWidth($w);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function stats(): array
    {
        return ['DEBITS_BOISSONS' => BeverageOutlet::where('is_active', true)->count(), 'TNB' => DB::table('parcels')->where('is_archived', false)->count(),
            'STATIONNEMENT' => Vehicle::where('is_active', true)->count(), 'OCCUPATION_DOMAINE' => PublicOccupation::where('is_active', true)->count(),
            'CONTRIBUABLES' => Taxpayer::where('is_active', true)->count()];
    }
}
