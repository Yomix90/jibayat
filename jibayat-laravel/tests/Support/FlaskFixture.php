<?php

namespace Tests\Support;

use PDO;

/**
 * Fabrique une base SQLite au schéma Flask réel (tests/Fixtures/flask_schema.sql)
 * remplie de données fictives couvrant tous les modules et les cas difficiles
 * (référence orpheline, date illisible, doublon Flask, déclaration annulée, mois en texte…).
 */
class FlaskFixture
{
    public static function create(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jibayat_flask_').'.db';
        $pdo = new PDO('sqlite:'.$path);
        $pdo->exec(file_get_contents(base_path('tests/Fixtures/flask_schema.sql')));

        $rows = [
            'communes' => [[1, 'Aït Amira', 'أيت عميرة', 'Président', 'الرئيس', 'Souss-Massa', 'سوس ماسة', 'Chtouka Aït Baha', 'اشتوكة آيت باها', 'img/logo.png', '0101', 1]],
            'roles' => [[1, 'super_admin', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1], [4, 'regisseur', 0, 0, 0, 1, 1, 0, 1, 0, 1, 1], [9, 'role_maison', 0, 0, 0, 1, 0, 0, 0, 0, 1, 1]],
            'utilisateurs' => [
                [1, 'Admin', 'Admin', 'Commune', 'admin@commune.ma', 'scrypt:x', 1, 1, 1, null, null, null, '2026-01-02 08:00:00', '2026-11-01 09:30:00', 0, '', 1, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0],
                [2, 'hassan', 'Ouali', 'Hassan', 'admin@commune.ma', 'scrypt:y', 4, 1, 1, null, null, '0600000000', '2026-02-03 10:00:00', null, 0, '', 1, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0],
                [3, 'stagiaire', 'Stag', null, null, 'scrypt:z', 9, 0, 1, null, null, null, 'pas une date', null, 1, 'TNB', 1, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0],
            ],
            'contribuables' => [
                [1, 'CTB-1', 'physique', 'Alaoui', 'Karim', 'العلوي', 'كريم', null, null, 'J123456', null, null, 'Douar 1', null, 'Aït Amira', null, '0611111111', null, 1, 1, '2024-01-10'],
                [2, 'CTB-2', 'morale', null, null, null, null, 'Café Atlas SARL', 'مقهى الأطلس', null, '001122', 'RC55', 'Centre', null, null, null, null, null, 1, 1, '2024-02-11 12:00:00'],
            ],
            'rubriques' => [[1, '30101014', 'TNB', 'الأراضي', 'TNB', null, 1, null], [2, '40102026', 'Location', 'كراء', 'LOCATION_LOCAUX', null, 1, null]],
            'arretes_fiscaux' => [[1, 'AF-2020-001', 'Initial', '2020-01-01', null, 'actif', null, 1, '2020-01-01']],
            'tarifs' => [
                [1, 1, 1, 'TNB-A', 'Zone A', 20.0, 'DH/m²', '2020-01-01', null, 1, 0.0, null],
                [2, 1, 99, 'TNB-B', 'Zone B', 8.5, 'DH/m²', '2020-01-01', null, 1, 0.0, 500.0],
            ],
            'parametres_calcul' => [[1, 'TNB', 'AMENDE_NON_DECLARATION', 'Amende', '15', '%', null, null]],
            'dossiers_tnb' => [[1, 1, 1, 0, '2024-01-10']],
            'terrains' => [[1, 'T-1', 1, 1, 'Lot 14', null, 'Centre', null, 450.5, 'A', 'TF-12/55', 'L14', 'non_bati', '2021-05-03', 2021, 1, '2024-01-10', 'Al Amal', 0, 0]],
            'permis' => [[1, 1, 'construction', 'PC-9', '2023-01-01', '2023-03-01', 'delivre', null, '2023-03-01', '2023-03-01', 2023, 2025]],
            'terrain_coproprietaires' => [[1, 1, 2, 33.3333, '2024-02-01']],
            'etablissements_boissons' => [[1, 'TDB-1', 2, 1, 'Café Atlas', 'cafe', 'Centre', 80, 'AUT-1', '2022-01-01', 2, 2022, 'actif', 1, '2022-01-01']],
            'vehicules' => [[1, 'V-1', 1, 1, '12345-A-26', 'Grand Taxi', 'AG-1', '2020-01-01', 6, 1, 2020, 'actif', 1, '2020-01-01']],
            'loc_secteurs' => [[1, 'S1', 'Marché', null, 'fixe', 300, 'DH/mois', 1, '2020-01-01', 1]],
            'loc_tarifs' => [[1, 1, 'Boutique standard', 'fixe', 300, 'DH/mois', 1, '2020-01-01', 1]],
            'loc_boutiques' => [[1, 1, 'B-01', 'Boutique 1', 12, 'louee', null, '2020-01-01']],
            'baux' => [[1, 'BAIL-1', 1, 1, 'B-01', 'Marché', 12, 300, '2025-01-01', null, 'actif', 1, '2025-01-01', 1, 1, 1]],
            'affermages' => [[1, 'AFF-1', 1, 1, 'Souk El Had', 'E-4', 'légumes', 6000, '2024-01-01', 'actif', 1, '2024-01-01', null, 1, 5.0, 500]],
            'dossiers_fourriere' => [[1, 'F-1', null, 1, '999-B-1', 'Voiture', '2026-01-01', 'Stationnement', 10, 150, 'en_fourriere', null, 1, '2026-01-01', 'D-1', null, 'POLICE', 'Inconnu', null, null, 25, null, null, null, null, null]],
            'fou_parametres' => [[1, 'capacite', 'CAPACITE_MAX', '250', 1, 1], [2, 'capacite', 'CAPACITE_MAX', '250', 1, 1]],
            'declarations' => [
                [1, 'DCL202600001', 'TNB', 1, 1, 1, 2025, 0, 450.5, 20, 9010, 901, 450.5, 1351.5, 11713, 'paye', '2026-01-15', '2025-02-28', '2026-01-20', 2, null, '2026-01-15'],
                [2, 'LOC-DECL202600001', 'LOCATION_LOCAUX', 1, 1, 1, 2025, 0, 300, 0, 900, 0, 0, 0, 900, 'emis', '2026-02-01', '2026-02-01', null, 2, '2025-01, 2025-02, 2025-03 | acompte', '2026-02-01'],
                [3, 'DCL-TDB202600003', 'DEBITS_BOISSONS', 1, 2, 1, 2025, 4, 40000, 6, 2400, 0, 0, 0, 2400, 'annule', '2026-02-02', '2026-01-31', null, 2, null, '2026-02-02'],
                [4, 'DCL-STA202600004', 'STATIONNEMENT', 77, 1, 1, 2025, 1, 75, 50, 125.33, 0, 0, 0, 125.33, 'sous_seuil', '2026-02-03', '2025-04-30', null, 2, null, '2026-02-03'],
            ],
            'bulletins' => [
                [1, 'BV-100', 1, 1, 1, 11713, 'especes', '2026-01-15', 'paye', 2, 1, null, '2026-01-15', 'Q-555', '2026-01-20', null, null, null],
                [2, 'BV-101', 2, 1, 1, 900, 'especes', '2026-02-01', 'en_attente', 2, null, null, '2026-02-01', null, null, null, null, null],
            ],
            'lettres_notification' => [[1, 'LET-1', 'LOT1', 'LOCATION_LOCAUX', 'relance', 'approuvee', '2026-03-01', null, 1, 1, 900, null, '2026-03-01']],
            'avis_non_paiement' => [[1, 'AVS202600001', 2, 1, 1, 900, '2026-03-01', 30, 'LOT1', 'emis', 1]],
            'lettres_details' => [[1, 1, 1, 2, 1, 900, 'inclus']],
            'regie_valeurs' => [[1, 'timbre', 'Timbre État Civil', 2, 500, 1]],
            'regie_services' => [[1, 'État Civil', 1]],
            'regie_employes' => [[1, 'M-1', 'Bennani', 'Sara', 1, 1]],
            'regie_paquets' => [[1, 1, 'P-1', '1000', '5999', 5000, '2026-01-05', 'ouvert', 1, '2026-01-05']],
            'regie_bordereaux' => [[1, 'BRD-1', '2026-02-01', 995.5, 1, '2026-02-01']],
            'regie_carnets' => [[1, 1, 1, 'P-1-C01', 1000, 1499, 'consomme', 1, 1, '2026-01-06', '2026-01-30', '2026-02-01', 1, 995.5, -4.5, 'manque 2 timbres']],
            'bordereaux_versement' => [[1, 1, 2026, 'janvier.xlsx', '2026-02-05 10:00:00', 12613]],
            'lignes_recettes' => [[1, 1, '1130101014', 'TNB', 11713, 'TNB'], [2, 1, '1140102026', 'Loyer', 900, 'Loyer']],
            'bordereaux_emission' => [[1, 1, 1, 'TNB', '1130101014', 'Taxe TNB', 11713, 0, 11713, 'BE.pdf', null, '2026-02-05 10:00:00']],
            'emission_config' => [[2026, 'auto', 0, 'manuel', 15000, null, '2026-02-05']],
            'regie_budget' => [[1, 2026, '30.10.10.14', '1130101014', 'TNB', 'TNB', 'الأراضي', 'TECHNIQUE', 'Technique', 'التقنية', 'TNB', 100000.5, 120000, 11713, null, 1, '2026-02-05']],
            'user_logs' => [[1, 1, 'connexion', 'Connexion réussie', '127.0.0.1', 'test', '2026-11-01 09:30:00']],
            'schema_meta' => [['version', '6']],
        ];

        foreach ($rows as $table => $data) {
            foreach ($data as $values) {
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $pdo->prepare("INSERT INTO {$table} VALUES ({$placeholders})")->execute($values);
            }
        }

        return $path;
    }
}
