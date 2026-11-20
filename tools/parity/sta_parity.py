"""
Parité Transport (stationnement + TPV) : exécute les VRAIES fonctions de Flask (modules/stationnement.py, helpers.py)
sur une base SQLite d'essai, à une date figée, et enregistre :
  - tests/Fixtures/parity/sta.db   (importée telle quelle côté Laravel)
  - tests/Fixtures/parity/sta.json (résultats attendus)

Comparé : trimestres_non_payes_sta, _tarif_pour_type, _get_sta_items_data (estimations de la liste) et la route
sta_payer elle-même (appelée sans ses décorateurs, dans un contexte de requête, sur une copie de la base).
Les tarifs d'essai sont en DH/trimestre : Flask les applique tels quels à chaque trimestre.
Le projet Flask n'est pas modifié (remplacements en mémoire uniquement).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\sta_parity.py
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
DB_PATH = os.path.join(OUT_DIR, 'sta.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
from werkzeug.datastructures import MultiDict  # noqa: E402
import modules.helpers as helpers  # noqa: E402
import modules.stationnement as sta  # noqa: E402

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


for mod in (helpers, sta):
    mod.get_db = connect
    mod.date = FrozenDate
    mod.datetime = FrozenDatetime
sta.get_tarifs_module = lambda module: [dict(r) for r in connect().execute('''
        SELECT t.*, r.code as rubrique_code, r.libelle as rubrique_libelle FROM tarifs t
        JOIN rubriques r ON t.rubrique_id = r.id LEFT JOIN arretes_fiscaux a ON t.arrete_id = a.id
        WHERE r.module = ? AND t.actif = 1 AND (a.statut = 'actif' OR a.statut IS NULL OR t.arrete_id IS NULL)
        ORDER BY t.valeur ASC''', (module,)).fetchall()]
sta.get_current_user = lambda: {'id': 1}
sta.flash = lambda *a, **k: None
sta.redirect = lambda *a, **k: None
sta.url_for = lambda *a, **k: ''
payer = inspect.unwrap(sta.sta_payer)
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
        ins('contribuables', (i, f'C{i}', 'physique', f'Transporteur{i}', 'Test', None, None, None, None, f'T{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    ins('rubriques', (1, '30101031', 'Stationnement', 'الوقوف', 'STATIONNEMENT', None, 1, None))
    ins('rubriques', (2, '30101032', 'Transport de voyageurs', 'نقل المسافرين', 'TRANSPORT_VOYAGEURS', None, 1, None))
    ins('arretes_fiscaux', (1, 'AF-2020-001', 'Initial', '2020-01-01', None, 'actif', None, None, '2020-01-01'))
    # (id, rubrique, arrete, code, libelle, valeur, unite, debut, fin, actif, surface_min, surface_max)
    ins('tarifs', (1, 1, 1, None, 'Grand Taxi', 300, 'DH/trimestre', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (2, 1, 1, None, 'Petit Taxi', 200, 'DH/trimestre', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (3, 1, 1, None, 'Autocar / Minibus', 500, 'DH/trimestre', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (4, 2, 1, None, 'Grand Taxi', 450.5, 'DH/trimestre', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (5, 2, 1, None, 'Autocar / Minibus', 1200, 'DH/trimestre', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (6, 2, 1, None, 'Navette', 99.99, 'DH/trimestre', '2020-01-01', None, 1, 0, None))

    # (id, numero, ctb, commune, immat, type, autorisation, date_aut, sieges, trim_debut, annee_debut, statut, actif, creation)
    rows = [
        (1, '1', 1, 1, '12345-A-6', 'Grand Taxi', 'AG-1', '2020-01-01', 6, 1, 2025, 'actif', 1, '2024-01-01'),
        (2, '2', 2, 1, '22-B-7', 'petit taxi', 'AG-2', None, 4, 3, 2025, 'actif', 1, '2024-01-01'),
        (3, '3', 3, 1, '33-C-8', 'Autocar / Minibus', 'AG-3', None, 40, 2, 2025, 'actif', 1, '2024-01-01'),
        (4, '4', 4, 1, '44-D-9', 'Inconnu', None, None, 0, 4, 2025, 'actif', 1, '2024-01-01'),
        (5, '5', 5, 1, '55-E-1', 'Grand Taxi', None, None, 6, 1, None, 'actif', 1, '2024-01-01'),
    ]
    for r in rows:
        ins('vehicules', r)
    d = lambda i, num, ref, y, t, total, statut: ins('declarations', (
        i, num, 'STATIONNEMENT', ref, ref, 1, y, t, 0, 0, total, 0, 0, 0, total, statut,
        '2025-05-01', '2025-04-30', None, 1, None, '2025-05-01'))
    d(1, 'DCL-STA202500001', 1, 2025, 1, 750.5, 'paye')
    d(2, 'DCL-STA202500002', 1, 2025, 2, 750.5, 'emis')
    d(3, 'DCL-STA202500003', 3, 2025, 2, 1700, 'annule')
    conn.commit()
    conn.close()


SCENARIOS = [
    # Grand taxi : T2 2025 déjà émis (doublon ignoré), T3/T4 2025 et T1 2026 en retard au 15/06/2026
    {'id': 1, 'date': '2026-06-15', 'bv': 'BV-100', 'trims': ['2025_2', '2025_3', '2025_4', '2026_1']},
    # Petit taxi (pas de tarif TPV ➜ premier tarif TPV) : T3 2025 payé le jour de l'échéance
    {'id': 2, 'date': '2025-10-31', 'bv': '', 'trims': ['2025_3']},
    # Petit taxi : T3 2025 et T4 2025 au 01/02/2026 (T4 en retard d'un jour)
    {'id': 2, 'date': '2026-02-01', 'bv': 'BV-200', 'trims': ['2025_4', '2025_3']},
    # Autocar : T2 2025 annulé auparavant ➜ redéclarable ; T4 2025 payé le 31/01/2026 (à l'heure)
    {'id': 3, 'date': '2026-01-31', 'bv': 'BV-300', 'trims': ['2025_2', '2025_3', '2025_4']},
    # Type inconnu ➜ premiers tarifs (les plus bas)
    {'id': 4, 'date': '2026-06-15', 'bv': 'BV-400', 'trims': ['2025_4', '2026_1']},
]


def run_scenario(sc):
    tmp = os.path.join(tempfile.mkdtemp(prefix='sta_sc_'), 'sc.db')
    shutil.copy(DB_PATH, tmp)
    CURRENT_DB[0] = tmp
    data = [('date_paiement', sc['date']), ('numero_bulletin', sc['bv'])] + [('trims', k) for k in sc['trims']]
    with app.test_request_context(f"/stationnement/{sc['id']}/payer", method='POST', data=MultiDict(data)):
        payer(sc['id'])
    conn = connect()
    out = []
    for r in conn.execute("SELECT * FROM declarations WHERE module='STATIONNEMENT' AND reference_id=? AND id > 3 ORDER BY annee, trimestre", (sc['id'],)):
        r = dict(r)
        b = conn.execute('SELECT numero_bulletin, montant, statut FROM bulletins WHERE declaration_id=?', (r['id'],)).fetchone()
        out.append({k: r[k] for k in ('annee', 'trimestre', 'base_calcul', 'taux', 'montant_principal', 'penalite_retard', 'majoration',
                                      'amende_non_declaration', 'montant_total', 'statut', 'date_declaration', 'date_echeance')}
                   | {'bulletin': dict(b) if b else None})
    conn.close()
    CURRENT_DB[0] = DB_PATH
    return out


def main():
    build_db()
    conn = connect()
    t_sta = sta.get_tarifs_module('STATIONNEMENT')
    t_tpv = sta.get_tarifs_module('TRANSPORT_VOYAGEURS')
    expected = {'today': TODAY.isoformat(), 'vehicules': {}, 'liste': {}, 'scenarios': []}
    for v in conn.execute('SELECT * FROM vehicules ORDER BY id'):
        np = sta.trimestres_non_payes_sta(v['id'], v['annee_debut_imposition'], v['trimestre_debut_imposition'])
        expected['vehicules'][str(v['id'])] = {
            'tarif_sta': sta._tarif_pour_type(v['type_vehicule'], t_sta),
            'tarif_tpv': sta._tarif_pour_type(v['type_vehicule'], t_tpv),
            'non_payes': [{'annee': t['annee'], 'trimestre': t['trimestre'], 'echeance': t['echeance']} for t in np],
        }
    data = sta._get_sta_items_data(conn, '')
    for it in data['items']:
        expected['liste'][str(it['id'])] = {k: it[k] for k in ('nb_non_declares', 'nb_emises', 'nb_non_paye', 'montant_impaye',
                                                               'montant_en_attente', 'annees_non_payees')}
    conn.close()
    for sc in SCENARIOS:
        expected['scenarios'].append({'input': sc, 'declarations': run_scenario(sc)})
    with open(os.path.join(OUT_DIR, 'sta.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(expected['vehicules']), 'véhicules,', len(SCENARIOS), 'scénarios ->', os.path.join(OUT_DIR, 'sta.json'))


if __name__ == '__main__':
    main()
