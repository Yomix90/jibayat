"""
Parité Débits de boissons (TDB) : exécute les VRAIES fonctions de Flask (modules/tdb.py, modules/helpers.py)
sur une base SQLite d'essai, à une date figée, et enregistre :
  - tests/Fixtures/parity/tdb.db   (importée telle quelle côté Laravel)
  - tests/Fixtures/parity/tdb.json (résultats attendus)

Comparé : trimestres_non_payes, _get_taux_etablissement, _get_tdb_items_data (estimations de la liste),
format_periode_non_payee (trimestres) et la route tdb_declarer elle-même (appelée sans ses décorateurs,
dans un contexte de requête, sur une copie de la base) pour chaque scénario de déclaration.
Le projet Flask n'est pas modifié (remplacements en mémoire uniquement).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\tdb_parity.py
"""
import datetime as _dt
import inspect
import json
import os
import shutil
import sqlite3
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
FLASK = os.path.join(ROOT, 'jibayat_app-main')
LARAVEL = os.path.join(ROOT, 'jibayat-laravel')
OUT_DIR = os.path.join(LARAVEL, 'tests', 'Fixtures', 'parity')
DB_PATH = os.path.join(OUT_DIR, 'tdb.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
import modules.helpers as helpers  # noqa: E402
import modules.tdb as tdb  # noqa: E402

CURRENT_DB = [DB_PATH]


class FrozenDate(_dt.date):
    @classmethod
    def today(cls):
        return TODAY


class FrozenDatetime(_dt.datetime):
    @classmethod
    def now(cls, tz=None):
        return _dt.datetime(TODAY.year, TODAY.month, TODAY.day, 10, 0, 0)


def connect():
    conn = sqlite3.connect(CURRENT_DB[0])
    conn.row_factory = sqlite3.Row
    return conn


for mod in (helpers, tdb):
    mod.get_db = connect
    mod.date = FrozenDate
    mod.datetime = FrozenDatetime
tdb.get_tarifs_module = lambda module: [dict(r) for r in connect().execute('''
        SELECT t.*, r.code as rubrique_code, r.libelle as rubrique_libelle FROM tarifs t
        JOIN rubriques r ON t.rubrique_id = r.id LEFT JOIN arretes_fiscaux a ON t.arrete_id = a.id
        WHERE r.module = ? AND t.actif = 1 AND (a.statut = 'actif' OR a.statut IS NULL OR t.arrete_id IS NULL)
        ORDER BY t.valeur ASC''', (module,)).fetchall()]
helpers.get_tarifs_module = tdb.get_tarifs_module
tdb.get_current_user = lambda: {'id': 1}
tdb.flash = lambda *a, **k: None
tdb.redirect = lambda *a, **k: None
tdb.url_for = lambda *a, **k: ''
declarer = inspect.unwrap(tdb.tdb_declarer)
app = Flask(__name__)


def build_db():
    os.makedirs(OUT_DIR, exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)

    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    for i in range(1, 7):
        ins('contribuables', (i, f'C{i}', 'physique', f'Exploitant{i}', 'Test', None, None, None, None, f'K{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    ins('rubriques', (1, '30101021', 'Débits de boissons', 'محال بيع المشروبات', 'DEBITS_BOISSONS', None, 1, None))
    ins('arretes_fiscaux', (1, 'AF-2020-001', 'Initial', '2020-01-01', '2025-01-01', 'remplace', None, None, '2020-01-01'))
    ins('arretes_fiscaux', (2, 'AF-2025-002', '2025', '2025-01-01', None, 'actif', None, None, '2025-01-01'))
    # (id, rubrique, arrete, code, libelle, valeur, unite, debut, fin, actif, surface_min, surface_max)
    ins('tarifs', (1, 1, 2, 'TDB-CAF', 'Cafés et salons de thé', 5, '%', '2025-01-01', None, 1, 0, None))
    ins('tarifs', (2, 1, 2, 'TDB-RES', 'Restaurants et snacks', 7.5, '%', '2025-01-01', None, 1, 0, None))
    ins('tarifs', (3, 1, 2, 'TDB-HOT', 'Hôtels et auberges', 10, '%', '2025-01-01', None, 1, 0, None))
    ins('tarifs', (4, 1, 1, 'TDB-OLD', 'Ancien taux bar', 2, '%', '2020-01-01', '2025-01-01', 1, 0, None))   # arrêté remplacé : exclu
    ins('tarifs', (5, 1, None, 'TDB-BAR', 'Bars et débits', 12, '%', '2025-01-01', None, 1, 0, None))
    ins('tarifs', (6, 1, 2, 'TDB-INA', 'Inactif', 1, '%', '2025-01-01', None, 0, 0, None))
    ins('parametres_calcul', (1, 'DEBITS_BOISSONS', 'AMENDE_NON_DECLARATION', 'Amende', '10', '%', None, None))

    # (id, numero, ctb, commune, nom, type, adresse, superficie, autorisation, date_aut, trim_debut, annee_debut, statut, actif, creation)
    rows = [
        (1, '1', 1, 1, 'Café Atlas', 'cafe', 'Centre', 80, 'AUT-1', '2023-01-01', 3, 2024, 'actif', 1, '2024-01-01'),
        (2, '2', 2, 1, 'Snack Al Baraka', 'Restaurant / snack', 'Souk', 40, 'AUT-2', '2024-01-01', 1, 2025, 'actif', 1, '2024-01-01'),
        (3, '3', 3, 1, 'Hôtel Tafraout', 'hôtel', 'Route', 500, 'AUT-3', '2022-01-01', 2, 2025, 'actif', 1, '2024-01-01'),
        (4, '4', 4, 1, 'Pub Ocean', 'bar', 'Plage', 60, None, None, 1, 2026, 'actif', 1, '2024-01-01'),
        (5, '5', 5, 1, 'Épicerie fine', 'commerce', 'Douar', 20, None, None, 4, 2025, 'cesse', 1, '2024-01-01'),
        (6, '6', 6, 1, 'Ancien café', 'cafe', 'Douar', 20, None, None, 1, None, 'actif', 1, '2024-01-01'),
    ]
    for r in rows:
        ins('etablissements_boissons', r)
    # Déclarations existantes : établ. 1 — T3 2024 payé, T4 2024 émis ; établ. 2 — T1 2025 annulé ; établ. 3 — T2 2025 émis
    d = lambda i, num, ref, y, t, base, taux, total, statut: ins('declarations', (
        i, num, 'DEBITS_BOISSONS', ref, ref, 1, y, t, base, taux, round(base * taux / 100, 2), 0, 0, 0, total, statut,
        '2025-01-15', '2024-10-31', None, 1, None, '2025-01-15'))
    d(1, 'DCL-TDB202500001', 1, 2024, 3, 20000, 5, 1000, 'paye')
    d(2, 'DCL-TDB202500002', 1, 2024, 4, 24000.5, 5, 1200.03, 'emis')
    d(3, 'DCL-TDB202500003', 2, 2025, 1, 9000, 7.5, 675, 'annule')
    d(4, 'DCL-TDB202500004', 3, 2025, 2, 150000, 10, 15000, 'emis')
    conn.commit()
    conn.close()


SCENARIOS = [
    # Café Atlas : 5 trimestres en retard au 15/06/2026, un BV commun ; T4 2024 déjà émis (doublon ignoré)
    {'id': 1, 'date': '2026-06-15', 'bv': 'BV-100', 'bases': {'2024_4': 1000, '2025_1': 12345.67, '2025_2': 15000, '2025_3': 0, '2025_4': 20000.55, '2026_1': 18000}},
    # Snack : T1 2025 à l'heure (échéance 30/04), T4 2025 déclaré le 15/01/2026 (échéance légale 31/01/2026), pas de BV
    {'id': 2, 'date': '2026-01-15', 'bv': '', 'bases': {'2025_1': 8000, '2025_4': 9100.25, '2025_2': 1500}},
    # Hôtel : T4 2025 déclaré le 01/04/2026 ➜ amende annuelle ; T3 2025 petit montant sous seuil
    {'id': 3, 'date': '2026-04-01', 'bv': 'BV-300', 'bases': {'2025_3': 1999.99, '2025_4': 250000, '2026_1': 3333.33}},
    # Bar : T1 2026 déclaré le 30/04/2026 (dernier jour) puis T1 2026 le 1er mai sur une autre copie
    {'id': 4, 'date': '2026-04-30', 'bv': 'BV-400', 'bases': {'2026_1': 7777.77}},
    {'id': 4, 'date': '2026-05-01', 'bv': 'BV-401', 'bases': {'2026_1': 7777.77}},
    # Épicerie (type sans mot-clé ➜ taux le plus bas) : T4 2025 au 31/03/2026 (pas d'amende) et au 02/04/2026
    {'id': 5, 'date': '2026-03-31', 'bv': 'BV-500', 'bases': {'2025_4': 30000}},
    {'id': 5, 'date': '2026-04-02', 'bv': 'BV-501', 'bases': {'2025_4': 30000}},
]


def run_scenario(sc):
    tmp = os.path.join(tempfile.mkdtemp(prefix='tdb_sc_'), 'sc.db')
    shutil.copy(DB_PATH, tmp)
    CURRENT_DB[0] = tmp
    form = {'date_declaration': sc['date'], 'numero_bulletin': sc['bv']}
    for k, v in sc['bases'].items():
        form[f'base_{k}'] = str(v)
    data = [(k, v) for k, v in form.items()] + [('trims', k) for k in sc['bases']]
    from werkzeug.datastructures import MultiDict
    with app.test_request_context(f"/debits-boissons/{sc['id']}/declarer", method='POST', data=MultiDict(data)):
        declarer(sc['id'])
    conn = connect()
    decls = [dict(r) for r in conn.execute(
        "SELECT * FROM declarations WHERE module='DEBITS_BOISSONS' AND reference_id=? AND id > 4 ORDER BY annee, trimestre", (sc['id'],))]
    out = []
    for r in decls:
        b = conn.execute('SELECT numero_bulletin, montant, statut, mode_paiement FROM bulletins WHERE declaration_id=?', (r['id'],)).fetchone()
        out.append({k: r[k] for k in ('annee', 'trimestre', 'base_calcul', 'taux', 'montant_principal', 'penalite_retard', 'majoration',
                                      'amende_non_declaration', 'montant_total', 'statut', 'date_declaration', 'date_echeance')}
                   | {'bulletin': dict(b) if b else None})
    conn.close()
    CURRENT_DB[0] = DB_PATH
    return out


def main():
    build_db()
    conn = connect()
    tarifs = tdb.get_tarifs_module('DEBITS_BOISSONS')
    expected = {'today': TODAY.isoformat(), 'etablissements': {}, 'liste': {}, 'scenarios': [], 'periodes': [], 'echeances': {}}
    for e in conn.execute('SELECT * FROM etablissements_boissons ORDER BY id'):
        np = tdb.trimestres_non_payes(e['id'], e['annee_debut_imposition'], e['trimestre_debut_imposition'])
        expected['etablissements'][str(e['id'])] = {
            'taux': tdb._get_taux_etablissement(e['type_etablissement'], tarifs),
            'non_payes': [{'annee': t['annee'], 'trimestre': t['trimestre'], 'echeance': t['echeance']} for t in np],
            'periode': helpers.format_periode_non_payee(np),
        }
    data = tdb._get_tdb_items_data(conn, '', '')
    for it in data['items']:
        expected['liste'][str(it['id'])] = {k: it[k] for k in ('dernier_ca_declare', 'nb_non_declares', 'nb_emises', 'nb_non_paye',
                                                               'annees_non_payees', 'montant_estime_retard', 'montant_en_attente', 'total_impaye')}
    expected['kpi'] = {k: data[k] for k in ('total_impayes_global', 'total_attente_regie_global', 'nb_dossiers_impayes',
                                            'nb_dossiers_en_attente', 'nb_dossiers_a_jour')}
    conn.close()
    for y in (2024, 2025, 2026):
        for t in (1, 2, 3, 4):
            expected['echeances'][f'{y}_{t}'] = tdb._get_deadline_tdb(y, t).isoformat()
    for sc in SCENARIOS:
        expected['scenarios'].append({'input': sc, 'declarations': run_scenario(sc)})

    with open(os.path.join(OUT_DIR, 'tdb.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(expected['etablissements']), 'établissements,', len(SCENARIOS), 'scénarios ->', os.path.join(OUT_DIR, 'tdb.json'))


if __name__ == '__main__':
    main()
