"""
Parité TNB : exécute les VRAIES fonctions de Flask (modules/tnb.py et modules/helpers.py)
sur une base SQLite d'essai, à une date figée, et enregistre :
  - tests/Fixtures/parity/tnb.db   (importée telle quelle côté Laravel)
  - tests/Fixtures/parity/tnb.json (résultats attendus)

Fonctions comparées : _calcul_annees_redevables, _compute_tarifs_annee, _calcul_exoneration,
helpers.calculer_penalites, helpers.majore_centimes.
Le projet Flask n'est pas modifié (remplacement en mémoire de get_db, date et datetime).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\tnb_parity.py
"""
import datetime as _dt
import json
import os
import sqlite3
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
FLASK = os.path.join(ROOT, 'jibayat_app-main')
LARAVEL = os.path.join(ROOT, 'jibayat-laravel')
OUT_DIR = os.path.join(LARAVEL, 'tests', 'Fixtures', 'parity')
DB_PATH = os.path.join(OUT_DIR, 'tnb.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

import modules.helpers as helpers  # noqa: E402
import modules.tnb as tnb  # noqa: E402


class FrozenDate(_dt.date):
    @classmethod
    def today(cls):
        return TODAY


class FrozenDatetime(_dt.datetime):
    @classmethod
    def now(cls, tz=None):
        return _dt.datetime(TODAY.year, TODAY.month, TODAY.day, 10, 0, 0)


def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


for mod in (helpers, tnb):
    mod.get_db = connect
    mod.date = FrozenDate
    mod.datetime = FrozenDatetime


def build_db():
    os.makedirs(OUT_DIR, exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)

    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    for i in range(1, 9):
        ins('contribuables', (i, f'C{i}', 'physique', f'Proprietaire{i}', 'Test', None, None, None, None, f'J{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    ins('rubriques', (1, '30101014', 'TNB', 'الأراضي', 'TNB', None, 1, None))
    ins('arretes_fiscaux', (1, 'AF-2020-001', 'Initial', '2020-01-01', '2025-01-01', 'remplace', None, None, '2020-01-01'))
    ins('arretes_fiscaux', (2, 'AF-2025-002', '2025', '2025-01-01', None, 'actif', None, None, '2025-01-01'))
    # (id, rubrique, arrete, code, libelle, valeur, unite, debut, fin, actif, surface_min, surface_max)
    ins('tarifs', (1, 1, 1, '30101014-ZONA', 'Zone A — Bien équipée', 20, 'DH/m²', '2020-01-01', '2025-01-01', 0, 0, None))
    ins('tarifs', (2, 1, 1, '30101014-ZONB', 'Zone B — Moyennement équipée', 8, 'DH/m²', '2020-01-01', '2025-01-01', 0, 0, None))
    ins('tarifs', (3, 1, 1, '30101014-ZONC', 'Zone C — Peu équipée', 1, 'DH/m²', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (4, 1, 2, '30101014-ZONA', 'Zone A — Bien équipée', 25.5, 'DH/m²', '2025-01-01', None, 1, 0, None))
    ins('tarifs', (5, 1, 2, '30101014-ZONB', 'Zone B — jusqu à 500 m²', 10, 'DH/m²', '2025-01-01', None, 1, 0, 500))
    ins('tarifs', (6, 1, 2, '30101014-ZONB', 'Zone B — au-delà de 500 m²', 12.25, 'DH/m²', '2025-01-01', None, 1, 500.01, None))
    ins('tarifs', (7, 1, 2, '30101014-ZOND', 'Zone D — forfait', 750, 'DH', '2025-01-01', None, 1, 0, None))
    ins('parametres_calcul', (1, 'TNB', 'AMENDE_NON_DECLARATION', 'Amende', '15', '%', None, None))

    # terrains : (id, numero, ctb, commune, adresse, adresse_ar, quartier, arrondissement, superficie, zone, tf, parcelle,
    #             statut, date_acquisition, annee_debut, actif, creation, lotissement, archive, recensement)
    rows = [
        (1, '1', 1, 1, 'Lot 14', None, None, None, 450.5, 'A', 'TF-1', 'L14', 'non_bati', None, 2021, 1, '2024-01-01', 'Al Amal', 0, 0),
        (2, '2', 2, 1, 'Lot 2', None, None, None, 800, 'B', 'TF-2', 'L2', 'non_bati', '2019-05-03', None, 1, '2024-01-01', 'Al Amal', 0, 0),
        (3, '3', 3, 1, 'Lot 3', None, None, None, 1200.75, 'C', 'TF-3', 'L3', 'en_construction', '2022-02-01', None, 1, '2024-01-01', None, 0, 0),
        (4, '4', 4, 1, 'Lot 4', None, None, None, 300, 'D', 'TF-4', 'L4', 'non_bati', None, 2025, 1, '2024-01-01', None, 0, 0),
        (5, '5', 5, 1, 'Lot 5', None, None, None, 333.33, ' b ', 'TF-5', 'L5', 'non_bati', None, 2024, 1, '2024-01-01', None, 0, 0),
        (6, '6', 6, 1, 'Lot 6', None, None, None, 5, 'A', 'TF-6', 'L6', 'non_bati', None, 2026, 1, '2024-01-01', None, 0, 0),
        (7, '7', 7, 1, 'Lot 7', None, None, None, 120.4, 'A', 'TF-7', 'L7', 'non_bati', None, 2023, 1, '2024-01-01', None, 0, 0),
        (8, '8', 8, 1, 'Lot 8', None, None, None, 999.99, 'Z', 'TF-8', 'L8', 'non_bati', None, 2025, 1, '2024-01-01', None, 0, 0),
    ]
    for r in rows:
        ins('terrains', r)
    for i in range(1, 9):
        ins('dossiers_tnb', (i, i, i, 0, '2024-01-01'))
    # Permis de construire du terrain 3 : exonération 2023–2025 ; terrain 7 : permis sans fin d'exonération
    ins('permis', (1, 3, 'construction', 'PC-3', None, None, 'en_cours', None, '2023-01-01', '2023-03-01', 2023, 2025))
    ins('permis', (2, 7, 'habiter', 'PH-7', None, None, 'en_cours', None, '2024-01-01', '2024-01-01', None, None))
    # Terrain 1 : 2023 payé ; terrain 7 : 2024 émis (en attente) et 2025 annulé
    ins('declarations', (1, 'DCL202300001', 'TNB', 1, 1, 1, 2023, 0, 450.5, 20, 9010, 0, 0, 0, 9010, 'paye', '2023-02-01', '2023-02-28', '2023-02-05', None, None, '2023-02-01'))
    ins('declarations', (2, 'DCL202500002', 'TNB', 7, 7, 1, 2024, 0, 120.4, 20, 2408, 0, 0, 0, 2408, 'emis', '2025-05-01', '2024-02-28', None, None, None, '2025-05-01'))
    ins('declarations', (3, 'DCL202500003', 'TNB', 7, 7, 1, 2025, 0, 120.4, 25.5, 3070.2, 0, 0, 0, 3070.2, 'annule', '2025-05-01', '2025-02-28', None, None, None, '2025-05-01'))
    conn.commit()
    conn.close()


def main():
    build_db()
    conn = connect()
    terrains = [dict(r) for r in conn.execute('SELECT * FROM terrains ORDER BY id')]
    all_tarifs = conn.execute(
        """SELECT t.code_tarif, t.libelle, t.valeur, t.unite, t.surface_min, t.surface_max, t.date_debut, t.date_fin
           FROM tarifs t JOIN rubriques r ON t.rubrique_id=r.id WHERE r.module='TNB' ORDER BY t.date_debut DESC""").fetchall()
    amende_pct = helpers.get_param('TNB', 'AMENDE_NON_DECLARATION', 15)
    dates = ['2026-06-15', '2026-02-27', '2026-03-01', '2027-01-31']

    expected = {'today': TODAY.isoformat(), 'amende_pct': amende_pct, 'terrains': {}, 'penalites': [], 'majore': []}
    for t in terrains:
        permis = [dict(p) for p in conn.execute("SELECT * FROM permis WHERE terrain_id=? AND type_permis='construction'", (t['id'],))]
        annees = tnb._calcul_annees_redevables(t['id'], t['date_acquisition'], permis, conn)
        calc = {}
        for d in dates:
            calc[d] = {str(y): tnb._compute_tarifs_annee(all_tarifs, str(t['zone'] or 'A'), float(t['superficie'] or 0), y, amende_pct, d)
                       for y in range(2020, 2027)}
        expected['terrains'][str(t['id'])] = {
            'annees_redevables': annees,
            'exonerees': [y for y in range(2020, 2027) if tnb._calcul_exoneration(permis, y)],
            'calculs': calc,
        }
    conn.close()

    for montant, ech, pay, module in [
        (9010, '2025-02-28', '2026-06-15', 'TNB'), (1000, '2025-02-28', '2025-03-01', 'TNB'),
        (1000, '2025-02-28', '2025-02-28', 'TNB'), (1000, '2025-01-31', '2025-03-31', 'TNB'),
        (1000, '2025-01-31', '2025-02-28', 'TNB'), (2408, '2024-02-28', '2026-06-15', 'TNB'),
        (333.33, '2024-04-30', '2024-07-31', 'TRANSPORT_VOYAGEURS'), (777.77, '2023-10-31', '2026-01-15', 'DEBITS_BOISSONS'),
        (125.5, '2026-01-31', '2026-01-31', 'TNB'), (5000, '2020-02-28', '2026-06-15', 'TNB'),
    ]:
        pen, maj = helpers.calculer_penalites(montant, ech, pay, module)
        expected['penalites'].append({'montant': montant, 'echeance': ech, 'paiement': pay, 'module': module, 'penalite': pen, 'majoration': maj})
    for v in [945.721, 100.0, 0.001, 1351.5, 2.675, 450.5 * 25.5, 0, -3]:
        expected['majore'].append({'valeur': v, 'resultat': helpers.majore_centimes(v)})

    with open(os.path.join(OUT_DIR, 'tnb.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(terrains), 'terrains ->', os.path.join(OUT_DIR, 'tnb.json'))


if __name__ == '__main__':
    main()
