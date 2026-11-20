<?php

namespace App\Domain\Import;

/**
 * Correspondance tables / colonnes Flask (SQLite) ➜ Laravel (MySQL), dans l'ordre d'import
 * (les tables référencées passent avant celles qui les référencent).
 *
 * Colonne cible => [colonne source, type] ; types :
 *   str, text, int, bool, dec (montant 2 déc.), rate (4 déc.), date, datetime, json,
 *   ref:<table Flask>  (id Flask ➜ id Laravel via legacy_id), user (= ref:utilisateurs)
 * Un 3e élément facultatif donne la valeur par défaut si la source est vide.
 *
 * 'sums' : contrôles de sommes source/cible (colonne source => colonne cible) inclus dans le rapport.
 */
class LegacyTableMap
{
    /** Tables Flask volontairement non migrées, avec la raison affichée dans le rapport. */
    public const SKIPPED = [
        'schema_meta' => 'technique (version du schéma Flask)',
        '_sys_sec_vault' => 'technique (coffre de licence Flask)',
        'login_attempts' => 'technique (anti-bruteforce, remplacé par le throttle Laravel)',
        'remember_tokens' => 'technique (sessions Flask)',
        'roles' => 'remplacée par les rôles spatie (correspondance des noms appliquée aux utilisateurs)',
        'role_module_permissions' => 'droits redéfinis dans RolesAndPermissionsSeeder',
        'user_module_permissions' => 'droits sur mesure à reconfigurer dans Paramètres ➜ Rôles',
        'tnb_terrains' => 'table héritée inutilisée par Flask v3 (remplacée par terrains)',
        'tnb_declarations' => 'table héritée inutilisée (remplacée par declarations)',
        'tdb_etablissements' => 'table héritée inutilisée (remplacée par etablissements_boissons)',
        'tdb_declarations' => 'table héritée inutilisée (remplacée par declarations)',
        'sta_vehicules' => 'table héritée inutilisée (remplacée par vehicules)',
        'sta_declarations' => 'table héritée inutilisée (remplacée par declarations)',
        'odp_occupations' => 'table héritée inutilisée (remplacée par occupations)',
        'odp_declarations' => 'table héritée inutilisée (remplacée par declarations)',
        'fou_dossiers' => 'table héritée inutilisée (remplacée par dossiers_fourriere)',
        'fou_declarations' => 'table héritée inutilisée (remplacée par declarations)',
        'loc_locaux' => 'table héritée inutilisée (remplacée par baux)',
        'loc_paiements' => 'table héritée inutilisée (remplacée par declarations)',
        'sou_contrats' => 'table héritée inutilisée (remplacée par affermages)',
        'sou_paiements' => 'table héritée inutilisée (remplacée par declarations)',
        'paiements_bulletins' => 'table héritée inutilisée (remplacée par bulletins)',
        'avis_imposition' => 'table héritée inutilisée (remplacée par avis_non_paiement)',
    ];

    /** Module Flask ➜ (table Flask de l'objet taxé, alias morph Laravel). */
    public const TAXABLE_BY_MODULE = [
        'TNB' => ['terrains', 'parcel'],
        'DEBITS_BOISSONS' => ['etablissements_boissons', 'beverage_outlet'],
        'STATIONNEMENT' => ['vehicules', 'vehicle'],
        'OCCUPATION_DOMAINE' => ['occupations', 'public_occupation'],
        'FOURRIERE' => ['dossiers_fourriere', 'impound_case'],
        'LOCATION_LOCAUX' => ['baux', 'lease'],
        'AFFERMAGE_SOUKS' => ['affermages', 'souk_contract'],
    ];

    /** Rôle Flask ➜ rôle Laravel. */
    public const ROLE_MAP = [
        'super_admin' => 'administrateur',
        'admin' => 'administrateur',
        'administrateur' => 'administrateur',
        'regisseur' => 'regisseur',
        'agent_assiette' => 'collecteur',
        'consultant' => 'consultation',
    ];

    public static function tables(): array
    {
        return [
            ['source' => 'communes', 'target' => 'municipalities', 'columns' => [
                'name' => ['nom', 'str', 'Commune'], 'name_ar' => ['nom_ar', 'str'],
                'president' => ['president_fr', 'str'], 'president_ar' => ['president_ar', 'str'],
                'region' => ['region', 'str'], 'region_ar' => ['region_ar', 'str'],
                'province' => ['province', 'str'], 'province_ar' => ['province_ar', 'str'],
                'logo' => ['logo', 'str'], 'code' => ['code', 'str'], 'is_active' => ['actif', 'bool', true],
            ]],
            ['source' => 'app_modules', 'target' => 'app_modules', 'columns' => [
                'code' => ['code', 'str'], 'label' => ['libelle', 'str'], 'description' => ['description', 'str'],
                'icon' => ['icone', 'str'], 'is_active' => ['actif', 'bool', true], 'sort_order' => ['ordre', 'int', 10],
            ]],
            ['source' => 'utilisateurs', 'target' => 'users', 'special' => 'users', 'columns' => [
                'username' => ['username', 'str'], 'email' => ['email', 'str'], 'phone' => ['telephone', 'str'],
                'is_active' => ['actif', 'bool', true], 'last_login_at' => ['derniere_connexion', 'datetime'],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'contribuables', 'target' => 'taxpayers', 'columns' => [
                'number' => ['numero', 'str'], 'person_type' => ['type_personne', 'str', 'physique'],
                'last_name' => ['nom', 'str'], 'first_name' => ['prenom', 'str'],
                'last_name_ar' => ['nom_ar', 'str'], 'first_name_ar' => ['prenom_ar', 'str'],
                'company_name' => ['raison_sociale', 'str'], 'company_name_ar' => ['raison_sociale_ar', 'str'],
                'cin' => ['cin', 'str'], 'ice' => ['ice', 'str'], 'rc' => ['rc', 'str'],
                'address' => ['adresse', 'str'], 'address_ar' => ['adresse_ar', 'str'], 'city' => ['ville', 'str'],
                'postal_code' => ['code_postal', 'str'], 'phone' => ['telephone', 'str'], 'email' => ['email', 'str'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'is_active' => ['actif', 'bool', true],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'ctb_documents', 'target' => 'taxpayer_documents', 'columns' => [
                'taxpayer_id' => ['contribuable_id', 'ref:contribuables'], 'type' => ['type_doc', 'str', 'autre'],
                'file_name' => ['nom_fichier', 'str'], 'path' => ['chemin', 'str'], 'size' => ['taille', 'int'],
                'uploaded_by' => ['agent_id', 'user'], 'notes' => ['notes', 'text'], 'created_at' => ['date_upload', 'datetime'],
            ]],
            ['source' => 'rubriques', 'target' => 'rubrics', 'columns' => [
                'code' => ['code', 'str'], 'label' => ['libelle', 'str', '—'], 'label_ar' => ['libelle_ar', 'str'],
                'module' => ['module', 'str'], 'municipality_id' => ['commune_id', 'ref:communes'],
                'is_active' => ['actif', 'bool', true], 'description' => ['description', 'text'],
            ]],
            ['source' => 'arretes_fiscaux', 'target' => 'tax_orders', 'columns' => [
                'number' => ['numero', 'str'], 'title' => ['titre', 'str'], 'effective_date' => ['date_effet', 'date', '2020-01-01'],
                'end_date' => ['date_fin', 'date'], 'status' => ['statut', 'str', 'actif'], 'notes' => ['notes', 'text'],
                'created_by' => ['agent_id', 'user'], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'tarifs', 'target' => 'tariffs', 'sums' => ['valeur' => 'value'], 'columns' => [
                'rubric_id' => ['rubrique_id', 'ref:rubriques'], 'tax_order_id' => ['arrete_id', 'ref:arretes_fiscaux'],
                'code' => ['code_tarif', 'str'], 'label' => ['libelle', 'str', '—'], 'value' => ['valeur', 'rate', 0],
                'unit' => ['unite', 'str', 'DH'], 'start_date' => ['date_debut', 'date', '2020-01-01'], 'end_date' => ['date_fin', 'date'],
                'is_active' => ['actif', 'bool', true], 'surface_min' => ['surface_min', 'dec', 0], 'surface_max' => ['surface_max', 'dec'],
            ]],
            ['source' => 'parametres_calcul', 'target' => 'calc_parameters', 'columns' => [
                'module' => ['module', 'str'], 'code' => ['code', 'str'], 'label' => ['libelle', 'str'], 'value' => ['valeur', 'str'],
                'unit' => ['unite', 'str'], 'description' => ['description', 'text'],
            ]],
            ['source' => 'imports_historique', 'target' => 'import_logs', 'columns' => [
                'module' => ['module', 'str', '?'], 'file_name' => ['nom_fichier', 'str'], 'total_rows' => ['nb_lignes_total', 'int', 0],
                'success_count' => ['nb_succes', 'int', 0], 'error_count' => ['nb_erreurs', 'int', 0], 'details' => ['details_json', 'json'],
                'agent_id' => ['agent_id', 'user'], 'created_at' => ['date_import', 'datetime'],
            ]],

            // ── TNB ───────────────────────────────────────────
            ['source' => 'dossiers_tnb', 'target' => 'tnb_files', 'columns' => [
                'number' => ['numero_dossier', 'int'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'is_archived' => ['archive', 'bool', false], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'terrains', 'target' => 'parcels', 'sums' => ['superficie' => 'area'], 'columns' => [
                'number' => ['numero_terrain', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'address' => ['adresse', 'str'], 'address_ar' => ['adresse_ar', 'str'],
                'district' => ['quartier', 'str'], 'borough' => ['arrondissement', 'str'], 'subdivision' => ['lotissement', 'str'],
                'area' => ['superficie', 'dec'], 'zone' => ['zone', 'str', 'B'], 'land_title' => ['titre_foncier', 'str'],
                'parcel_number' => ['num_parcelle', 'str'], 'status' => ['statut', 'str', 'non_bati'], 'acquired_on' => ['date_acquisition', 'date'],
                'tax_start_year' => ['annee_debut_imposition', 'int'], 'is_active' => ['actif', 'bool', true],
                'is_archived' => ['archive', 'bool', false], 'is_census' => ['recensement', 'bool', false], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'permis', 'target' => 'building_permits', 'columns' => [
                'parcel_id' => ['terrain_id', 'ref:terrains'], 'type' => ['type_permis', 'str'], 'number' => ['numero_permis', 'str'],
                'filed_on' => ['date_depot', 'date'], 'issued_on' => ['date_delivrance', 'date'], 'authorized_on' => ['date_autorisation', 'date'],
                'status' => ['statut', 'str', 'en_cours'], 'description' => ['description', 'text'],
                'exemption_start_year' => ['annee_debut_exoneration', 'int'], 'exemption_end_year' => ['annee_fin_exoneration', 'int'],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'transferts_terrain', 'target' => 'parcel_transfers', 'columns' => [
                'parcel_id' => ['terrain_id', 'ref:terrains'], 'from_taxpayer_id' => ['ancien_contribuable_id', 'ref:contribuables'],
                'to_taxpayer_id' => ['nouveau_contribuable_id', 'ref:contribuables'], 'transferred_on' => ['date_transfert', 'date'],
                'reason' => ['motif', 'str'], 'notarial_deed' => ['acte_notarie', 'str'], 'agent_id' => ['agent_id', 'user'],
            ]],
            ['source' => 'terrain_coproprietaires', 'target' => 'parcel_co_owners', 'columns' => [
                'parcel_id' => ['terrain_id', 'ref:terrains'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'share_percent' => ['part_indivision', 'rate', 0], 'created_at' => ['date_ajout', 'datetime'],
            ]],
            ['source' => 'tnb_documents', 'target' => 'parcel_documents', 'columns' => [
                'parcel_id' => ['terrain_id', 'ref:terrains'], 'file_name' => ['nom_fichier', 'str'], 'path' => ['chemin', 'str'],
                'type' => ['type_doc', 'str', 'autre'], 'size' => ['taille', 'int'], 'uploaded_by' => ['agent_id', 'user'],
                'created_at' => ['date_upload', 'datetime'],
            ]],

            // ── Débits de boissons, transport, domaine public ──
            ['source' => 'etablissements_boissons', 'target' => 'beverage_outlets', 'columns' => [
                'number' => ['numero', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'name' => ['nom_etablissement', 'str'],
                'type' => ['type_etablissement', 'str'], 'address' => ['adresse', 'str'], 'area' => ['superficie', 'dec'],
                'authorization_number' => ['numero_autorisation', 'str'], 'authorized_on' => ['date_autorisation', 'date'],
                'tax_start_quarter' => ['trimestre_debut_imposition', 'int', 1], 'tax_start_year' => ['annee_debut_imposition', 'int'],
                'status' => ['statut', 'str', 'actif'], 'is_active' => ['actif', 'bool', true], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'declarations_annuelles_tdb', 'target' => 'beverage_annual_returns', 'sums' => ['montant_du' => 'amount_due'], 'columns' => [
                'number' => ['numero', 'str'], 'beverage_outlet_id' => ['etablissement_id', 'ref:etablissements_boissons'],
                'taxpayer_id' => ['contribuable_id', 'ref:contribuables'], 'year' => ['annee', 'int', 0],
                'base_q1' => ['base_t1', 'dec', 0], 'base_q2' => ['base_t2', 'dec', 0], 'base_q3' => ['base_t3', 'dec', 0], 'base_q4' => ['base_t4', 'dec', 0],
                'total_base' => ['total_base', 'dec', 0], 'rate' => ['taux', 'rate', 10], 'amount_due' => ['montant_du', 'dec', 0],
                'declared_on' => ['date_declaration', 'date'], 'agent_id' => ['agent_id', 'user'], 'status' => ['statut', 'str', 'soumise'],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'vehicules', 'target' => 'vehicles', 'columns' => [
                'number' => ['numero', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'registration' => ['immatriculation', 'str'],
                'type' => ['type_vehicule', 'str'], 'authorization_number' => ['num_autorisation', 'str'],
                'authorized_on' => ['date_autorisation', 'date'], 'seats' => ['nombre_sieges', 'int', 0],
                'tax_start_quarter' => ['trimestre_debut_imposition', 'int', 1], 'tax_start_year' => ['annee_debut_imposition', 'int'],
                'status' => ['statut', 'str', 'actif'], 'is_active' => ['actif', 'bool', true], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'occupations', 'target' => 'public_occupations', 'columns' => [
                'number' => ['numero', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'type' => ['type_occupation', 'str'], 'location' => ['localisation', 'str'],
                'area' => ['superficie', 'dec'], 'authorization_number' => ['num_autorisation', 'str'], 'starts_on' => ['date_debut', 'date'],
                'ends_on' => ['date_fin', 'date'], 'tax_start_quarter' => ['trimestre_debut_imposition', 'int', 1],
                'tax_start_year' => ['annee_debut_imposition', 'int'], 'status' => ['statut', 'str', 'actif'],
                'is_active' => ['actif', 'bool', true], 'created_at' => ['date_creation', 'datetime'],
            ]],

            // ── Fourrière ─────────────────────────────────────
            ['source' => 'dossiers_fourriere', 'target' => 'impound_cases', 'columns' => [
                'number' => ['numero', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'registration' => ['immatriculation', 'str'],
                'registration_alt' => ['numero_immat', 'str'], 'vehicle_type' => ['type_vehicule', 'str'],
                'deposit_number' => ['num_depot', 'str'], 'depositor' => ['deposant', 'str'], 'impounded_on' => ['date_mise_fourriere', 'date'],
                'reason' => ['motif', 'str'], 'days' => ['nb_jours', 'int', 0], 'daily_rate' => ['tarif_journalier', 'dec', 0],
                'towing_fee' => ['frais_remorquage', 'dec', 0], 'owner_name' => ['nom_proprietaire', 'str'],
                'owner_cin' => ['cin_proprietaire', 'str'], 'owner_phone' => ['telephone_prop', 'str'],
                'slip_number' => ['numero_bulletin', 'str'], 'paid_on' => ['date_paiement', 'date'],
                'payment_agent_id' => ['agent_paiement_id', 'user'], 'status' => ['statut', 'str', 'en_fourriere'],
                'released_on' => ['date_restitution', 'date'], 'release_validated_on' => ['date_sortie_validee', 'date'],
                'notes' => ['notes', 'text'], 'is_active' => ['actif', 'bool', true], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'fou_parametres', 'target' => 'impound_settings', 'columns' => [
                'category' => ['categorie', 'str'], 'code' => ['code', 'str'], 'label' => ['libelle', 'str', '—'],
                'sort_order' => ['ordre', 'int', 0], 'is_active' => ['actif', 'bool', true],
            ]],
            ['source' => 'fou_types_vehicule', 'target' => 'impound_vehicle_types', 'columns' => [
                'code' => ['code', 'str'], 'label' => ['libelle', 'str', '—'], 'daily_rate' => ['tarif_journalier', 'dec', 0],
                'is_active' => ['actif', 'bool', true],
            ]],
            ['source' => 'fou_groupes_enchere', 'target' => 'auction_groups', 'columns' => [
                'number' => ['numero', 'str'], 'label' => ['libelle', 'str', '—'], 'auction_on' => ['date_enchere', 'date'],
                'place' => ['lieu', 'str'], 'opening_price' => ['prix_ouverture', 'dec', 0], 'vehicle_type' => ['type_vehicule', 'str'],
                'status' => ['statut', 'str', 'ouvert'], 'notes' => ['notes', 'text'], 'agent_id' => ['agent_id', 'user'],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'fou_vehicules_enchere', 'target' => 'auction_group_vehicles', 'columns' => [
                'auction_group_id' => ['groupe_id', 'ref:fou_groupes_enchere'], 'impound_case_id' => ['dossier_id', 'ref:dossiers_fourriere'],
                'condition' => ['etat_vehicule', 'str', 'moyen'], 'sort_order' => ['ordre', 'int', 0], 'notes' => ['notes', 'text'],
            ]],
            ['source' => 'fou_ventes', 'target' => 'auction_sales', 'sums' => ['prix_adjudication' => 'hammer_price'], 'columns' => [
                'auction_group_id' => ['groupe_id', 'ref:fou_groupes_enchere'], 'impound_case_id' => ['dossier_id', 'ref:dossiers_fourriere'],
                'buyer_name' => ['nom_acheteur', 'str'], 'buyer_cin' => ['cin_acheteur', 'str'], 'buyer_phone' => ['telephone_acheteur', 'str'],
                'hammer_price' => ['prix_adjudication', 'dec', 0], 'receipt_number' => ['numero_quittance', 'str'],
                'sold_on' => ['date_vente', 'date'], 'report_path' => ['pv_chemin', 'str'], 'report_name' => ['pv_nom', 'str'],
                'agent_id' => ['agent_id', 'user'], 'notes' => ['notes', 'text'],
            ]],

            // ── Locations ─────────────────────────────────────
            ['source' => 'loc_secteurs', 'target' => 'lease_sectors', 'columns' => [
                'code' => ['code', 'str'], 'label' => ['libelle', 'str', '—'], 'description' => ['description', 'text'],
                'tariff_type' => ['type_tarif', 'str', 'fixe'], 'monthly_rate' => ['tarif_mensuel', 'dec', 0],
                'unit' => ['unite', 'str', 'DH/mois'], 'is_active' => ['actif', 'bool', true], 'sort_order' => ['ordre', 'int', 0],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'loc_tarifs', 'target' => 'lease_tariffs', 'columns' => [
                'lease_sector_id' => ['secteur_id', 'ref:loc_secteurs'], 'label' => ['libelle', 'str', '—'],
                'tariff_type' => ['type_tarif', 'str', 'fixe'], 'value' => ['valeur', 'dec', 0], 'unit' => ['unite', 'str', 'DH/mois'],
                'is_active' => ['actif', 'bool', true], 'tariff_id' => ['tarif_fiscal_id', 'ref:tarifs'], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'loc_boutiques', 'target' => 'shops', 'columns' => [
                'lease_tariff_id' => ['tarif_id', 'ref:loc_tarifs'], 'number' => ['numero', 'str', '?'], 'label' => ['libelle', 'str'],
                'area' => ['superficie', 'dec', 0], 'status' => ['statut', 'str', 'disponible'], 'notes' => ['notes', 'text'],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'baux', 'target' => 'leases', 'sums' => ['loyer_mensuel' => 'monthly_rent'], 'columns' => [
                'number' => ['numero', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'premises_ref' => ['ref_local', 'str'], 'address' => ['adresse', 'str'],
                'area' => ['superficie', 'dec'], 'monthly_rent' => ['loyer_mensuel', 'dec'], 'starts_on' => ['date_debut', 'date'],
                'ends_on' => ['date_fin', 'date'], 'status' => ['statut', 'str', 'actif'], 'is_active' => ['actif', 'bool', true],
                'lease_sector_id' => ['secteur_id', 'ref:loc_secteurs'], 'shop_id' => ['boutique_id', 'ref:loc_boutiques'],
                'lease_tariff_id' => ['tarif_id', 'ref:loc_tarifs'], 'created_at' => ['date_creation', 'datetime'],
            ]],

            // ── Souk ──────────────────────────────────────────
            ['source' => 'affermages', 'target' => 'souk_contracts', 'sums' => ['redevance_mensuelle' => 'monthly_fee'], 'columns' => [
                'number' => ['numero', 'str'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'municipality_id' => ['commune_id', 'ref:communes'], 'souk_name' => ['nom_souk', 'str'],
                'pitch_number' => ['num_emplacement', 'str'], 'activity' => ['type_activite', 'str'],
                'annual_fee' => ['redevance_annuelle', 'dec'], 'monthly_fee' => ['redevance_mensuelle', 'dec', 0],
                'starts_on' => ['date_debut', 'date'], 'ends_on' => ['date_fin', 'date'], 'duration_years' => ['duree_contrat', 'int', 1],
                'increase_rate' => ['taux_augmentation', 'rate', 5], 'status' => ['statut', 'str', 'actif'],
                'is_active' => ['actif', 'bool', true], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'affermage_docs', 'target' => 'souk_contract_documents', 'columns' => [
                'souk_contract_id' => ['affermage_id', 'ref:affermages'], 'file_name' => ['nom_fichier', 'str'], 'path' => ['chemin', 'str'],
                'type' => ['type_doc', 'str'], 'uploaded_by' => ['agent_id', 'user'], 'created_at' => ['date_upload', 'datetime'],
            ]],

            // ── Pièces financières ────────────────────────────
            ['source' => 'declarations', 'target' => 'tax_declarations', 'special' => 'declarations',
                'sums' => ['montant_principal' => 'principal', 'penalite_retard' => 'late_penalty', 'majoration' => 'surcharge',
                    'amende_non_declaration' => 'non_filing_fine', 'montant_total' => 'total'],
                'columns' => [
                    'number' => ['numero', 'str'], 'module' => ['module', 'str', '?'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                    'municipality_id' => ['commune_id', 'ref:communes'], 'year' => ['annee', 'int'], 'quarter' => ['trimestre', 'int', 0],
                    'base_amount' => ['base_calcul', 'dec', 0], 'rate' => ['taux', 'rate', 0], 'principal' => ['montant_principal', 'dec', 0],
                    'late_penalty' => ['penalite_retard', 'dec', 0], 'surcharge' => ['majoration', 'dec', 0],
                    'non_filing_fine' => ['amende_non_declaration', 'dec', 0], 'total' => ['montant_total', 'dec', 0],
                    'status' => ['statut', 'str', 'emis'], 'declared_on' => ['date_declaration', 'date'], 'due_on' => ['date_echeance', 'date'],
                    'paid_on' => ['date_paiement', 'date'], 'agent_id' => ['agent_id', 'user'], 'notes' => ['notes', 'text'],
                    'created_at' => ['date_creation', 'datetime'],
                ]],
            ['source' => 'bulletins', 'target' => 'payment_slips', 'sums' => ['montant' => 'amount'], 'columns' => [
                'slip_number' => ['numero_bulletin', 'str'], 'tax_declaration_id' => ['declaration_id', 'ref:declarations'],
                'taxpayer_id' => ['contribuable_id', 'ref:contribuables'], 'municipality_id' => ['commune_id', 'ref:communes'],
                'amount' => ['montant', 'dec', 0], 'payment_mode' => ['mode_paiement', 'str', 'especes'], 'paid_on' => ['date_paiement', 'date'],
                'status' => ['statut', 'str', 'en_attente'], 'agent_id' => ['agent_id', 'user'], 'cashier_id' => ['regisseur_id', 'user'],
                'receipt_number' => ['numero_quittance', 'str'], 'receipt_date' => ['date_quittance', 'date'],
                'rejection_reason' => ['motif_rejet', 'str'], 'transfer_number' => ['numero_versement', 'str'],
                'collected_on' => ['date_encaissement', 'date'], 'notes' => ['notes', 'text'], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'lettres_notification', 'target' => 'reminder_letters', 'sums' => ['montant_total' => 'total_amount'], 'columns' => [
                'number' => ['numero_lettre', 'str'], 'batch_id' => ['lot_id', 'str'], 'module' => ['module', 'str'],
                'type' => ['type_lettre', 'str', 'relance'], 'status' => ['statut', 'str', 'brouillon'], 'generated_on' => ['date_generation', 'date'],
                'sent_on' => ['date_envoi', 'date'], 'agent_id' => ['agent_id', 'user'], 'taxpayer_count' => ['nb_redevables', 'int', 0],
                'total_amount' => ['montant_total', 'dec', 0], 'notes' => ['notes', 'text'], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'avis_non_paiement', 'target' => 'non_payment_notices', 'sums' => ['montant_du' => 'amount_due'], 'columns' => [
                'number' => ['numero_avis', 'str'], 'tax_declaration_id' => ['declaration_id', 'ref:declarations'],
                'taxpayer_id' => ['contribuable_id', 'ref:contribuables'], 'municipality_id' => ['commune_id', 'ref:communes'],
                'amount_due' => ['montant_du', 'dec', 0], 'issued_on' => ['date_emission', 'date'], 'delay_days' => ['delai_jours', 'int', 30],
                'batch_id' => ['lot_id', 'str'], 'status' => ['statut', 'str', 'emis'], 'reminder_letter_id' => ['lettre_id', 'ref:lettres_notification'],
            ]],
            ['source' => 'lettres_details', 'target' => 'reminder_letter_items', 'columns' => [
                'reminder_letter_id' => ['lettre_id', 'ref:lettres_notification'], 'non_payment_notice_id' => ['avis_id', 'ref:avis_non_paiement'],
                'tax_declaration_id' => ['declaration_id', 'ref:declarations'], 'taxpayer_id' => ['contribuable_id', 'ref:contribuables'],
                'amount_due' => ['montant_du', 'dec', 0], 'delivery_status' => ['statut_envoi', 'str', 'inclus'],
            ]],

            // ── Régie des timbres ─────────────────────────────
            ['source' => 'regie_valeurs', 'target' => 'stamp_values', 'columns' => [
                'type' => ['type_valeur', 'str', 'timbre'], 'label' => ['designation', 'str', '—'], 'unit_value' => ['valeur_unitaire', 'dec', 0],
                'units_per_book' => ['nb_unites_carnet', 'int', 0], 'is_active' => ['actif', 'bool', true],
            ]],
            ['source' => 'regie_services', 'target' => 'services', 'columns' => [
                'name' => ['nom', 'str', '—'], 'is_active' => ['actif', 'bool', true],
            ]],
            ['source' => 'regie_employes', 'target' => 'employees', 'columns' => [
                'registration_number' => ['matricule', 'str'], 'last_name' => ['nom', 'str', '—'], 'first_name' => ['prenom', 'str'],
                'service_id' => ['service_id', 'ref:regie_services'], 'is_active' => ['actif', 'bool', true],
            ]],
            ['source' => 'regie_paquets', 'target' => 'stamp_packs', 'columns' => [
                'stamp_value_id' => ['valeur_id', 'ref:regie_valeurs'], 'number' => ['numero_paquet', 'str'],
                'first_number' => ['num_premier', 'str'], 'last_number' => ['num_dernier', 'str'], 'quantity' => ['quantite_vignettes', 'int'],
                'received_on' => ['date_reception', 'date'], 'status' => ['statut', 'str', 'recu'], 'agent_id' => ['agent_id', 'user'],
                'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'regie_bordereaux', 'target' => 'stamp_remittances', 'sums' => ['montant_total' => 'total_amount'], 'columns' => [
                'number' => ['numero', 'str'], 'remitted_on' => ['date_versement', 'date'], 'total_amount' => ['montant_total', 'dec', 0],
                'agent_id' => ['agent_id', 'user'], 'created_at' => ['date_creation', 'datetime'],
            ]],
            ['source' => 'regie_carnets', 'target' => 'stamp_books', 'sums' => ['montant_verse' => 'amount_paid', 'ecart' => 'difference'], 'columns' => [
                'stamp_pack_id' => ['paquet_id', 'ref:regie_paquets'], 'stamp_value_id' => ['valeur_id', 'ref:regie_valeurs'],
                'number' => ['numero_carnet', 'str'], 'first_number' => ['num_premier', 'int'], 'last_number' => ['num_dernier', 'int'],
                'status' => ['statut', 'str', 'en_stock'], 'service_id' => ['service_id', 'ref:regie_services'],
                'employee_id' => ['employe_id', 'ref:regie_employes'], 'assigned_on' => ['date_affectation', 'date'],
                'employee_paid_on' => ['date_versement_employe', 'date'], 'remitted_on' => ['date_versement_percepteur', 'date'],
                'stamp_remittance_id' => ['bordereau_id', 'ref:regie_bordereaux'], 'amount_paid' => ['montant_verse', 'dec', 0],
                'difference' => ['ecart', 'dec', 0], 'observation' => ['observation', 'text'],
            ]],

            // ── Trésor : relevés GIR et bordereaux d'émission ─
            ['source' => 'bordereaux_versement', 'target' => 'treasury_statements', 'sums' => ['total_general' => 'grand_total'], 'columns' => [
                'month' => ['mois', 'int'], 'year' => ['annee', 'int'], 'source_file' => ['fichier_source', 'str'],
                'imported_at' => ['date_import', 'datetime'], 'grand_total' => ['total_general', 'dec'],
            ]],
            ['source' => 'lignes_recettes', 'target' => 'treasury_statement_lines', 'sums' => ['montant' => 'amount'], 'columns' => [
                'treasury_statement_id' => ['bordereau_id', 'ref:bordereaux_versement'], 'budget_code' => ['code_budgetaire', 'str'],
                'nature' => ['nature_recette', 'str'], 'amount' => ['montant', 'dec'], 'issue_sheet' => ['feuille_emission', 'str'],
            ]],
            ['source' => 'bordereaux_emission', 'target' => 'issue_slips', 'sums' => ['total' => 'total'], 'columns' => [
                'treasury_statement_id' => ['bordereau_id', 'ref:bordereaux_versement'], 'number' => ['numero_bordereau', 'int'],
                'rubric' => ['rubrique', 'str'], 'budget_code' => ['code_budgetaire', 'str'], 'title' => ['intitule', 'str'],
                'current_amount' => ['montant_present', 'dec'], 'carried_forward' => ['report_anterieurs', 'dec'], 'total' => ['total', 'dec'],
                'pdf_path' => ['chemin_pdf', 'str'], 'xlsx_path' => ['chemin_xlsx', 'str'], 'generated_at' => ['date_generation', 'datetime'],
            ]],
            ['source' => 'emission_config', 'target' => 'issue_configs', 'key' => 'annee', 'target_key' => 'year', 'columns' => [
                'year' => ['annee', 'int'], 'first_part_mode' => ['premiere_partie_mode', 'str', 'auto'],
                'first_part_value' => ['premiere_partie_valeur', 'dec', 0], 'second_part_mode' => ['deuxieme_partie_mode', 'str', 'vide'],
                'second_part_value' => ['deuxieme_partie_valeur', 'dec', 0], 'notes' => ['notes', 'text'],
                'updated_at' => ['date_modification', 'datetime'],
            ]],

            // ── Budget ────────────────────────────────────────
            ['source' => 'regie_budget', 'target' => 'budget_lines',
                'sums' => ['montant_realise_n1' => 'previous_actual', 'montant_propose' => 'proposed', 'montant_realise' => 'actual'],
                'columns' => [
                    'year' => ['annee', 'int'], 'chapter' => ['chapitre', 'str'], 'gir_code' => ['code_gir', 'str'], 'label' => ['libelle', 'str', '—'],
                    'label_fr' => ['libelle_fr', 'str'], 'label_ar' => ['libelle_ar', 'str'], 'domain' => ['domaine', 'str', 'ADMINISTRATION'],
                    'domain_fr' => ['domaine_fr', 'str'], 'domain_ar' => ['domaine_ar', 'str'], 'module' => ['module_associe', 'str'],
                    'previous_actual' => ['montant_realise_n1', 'dec', 0], 'proposed' => ['montant_propose', 'dec', 0],
                    'actual' => ['montant_realise', 'dec', 0], 'notes' => ['notes', 'text'], 'sort_order' => ['ordre', 'int', 0],
                    'updated_at' => ['date_modification', 'datetime'],
                ]],

            // ── Journal d'activité Flask ➜ activity_log ───────
            ['source' => 'user_logs', 'target' => 'activity_log', 'special' => 'user_logs', 'columns' => []],
        ];
    }
}
