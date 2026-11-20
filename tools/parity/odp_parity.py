"""
Parité Domaine public (ODP) : exécute les VRAIES fonctions de Flask (modules/occupation.py) sur une base SQLite d'essai,
à une date figée, et enregistre tests/Fixtures/parity/odp.{db,json}.

Comparé : _calculer_bases_tarif (toutes unités), _get_odp_tarif, _get_odp_items_data (liste) et la route
odp_declarer elle-même (sans décorateurs, sur une copie de la base) : trimestres et années entières.
Le projet Flask n'est pas modifié (remplacements en mémoire uniquement).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\odp_parity.py
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
DB_PATH = os.path.join(OUT_DIR, 'odp.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
from werkzeug.datastructures import MultiDict  # noqa: E402
import modules.helpers as helpers  # noqa: E402
import modules.occupation as odp  # noqa: E402

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


for mod in (helpers, odp):
    mod.get_db = connect
    mod.date = FrozenDate
    mod.datetime = FrozenDatetime
odp.get_tarifs_module = lambda module: [dict(r) for r in connect().execute('''
        SELECT t.*, r.code as rubrique_code, r.libelle as rubrique_libelle FROM tarifs t
        JOIN rubriques r ON t.rubrique_id = r.id LEFT JOIN arretes_fiscaux a ON t.arrete_id = a.id
        WHERE r.module = ? AND t.actif = 1 AND (a.statut = 'actif' OR a.statut IS NULL OR t.arrete_id IS NULL)
        ORDER BY t.valeur ASC''', (module,)).fetchall()]
odp.get_current_user = lambda: {'id': 1}
odp.flash = lambda *a, **k: None
odp.redirect = lambda *a, **k: None
odp.url_for = lambda *a, **k: ''
declarer = inspect.unwrap(odp.odp_declarer)
app = Flask(__name__)

# Tarifs de l'essai. Flask (_get_odp_tarif) ne trouve QUE le premier tarif par valeur croissante (ici « Chantier ») :
# un autre type renvoie None et la déclaration plante. Les occupations d'essai utilisent donc « Chantier ».
TARIFS = [
    (1, 1, 1, 'ODP-TER', 'Terrasse / Étalage', 50, 'DH/m²/an', '2020-01-01', None, 1, 0, None),
    (2, 1, 1, 'ODP-KIO', 'Kiosque', 80, 'DH/m²/an', '2020-01-01', None, 1, 0, None),
    (3, 1, 1, 'ODP-CHA', 'Chantier', 30, 'DH/m²/mois', '2020-01-01', None, 1, 0, None),
]


def build_db():
    os.makedirs(OUT_DIR, exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)
    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    for i in range(1, 6):
        ins('contribuables', (i, f'C{i}', 'physique', f'Occupant{i}', 'Test', None, None, None, None, f'O{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    ins('rubriques', (1, '40102011', 'Occupation du domaine public', 'احتلال الملك العام', 'OCCUPATION_DOMAINE', None, 1, None))
    ins('arretes_fiscaux', (1, 'AF-2020-001', 'Initial', '2020-01-01', None, 'actif', None, None, '2020-01-01'))
    for t in TARIFS:
        ins('tarifs', t)
    # (id, numero, ctb, commune, type, localisation, superficie, autorisation, debut, fin, trim_debut, annee_debut, statut, actif, creation)
    rows = [
        (1, '1', 1, 1, 'Chantier', 'Bd Hassan II', 12.5, 'AUT-1', '2025-01-01', '2026-12-31', 1, 2025, 'actif', 1, '2024-01-01'),
        (2, '2', 2, 1, ' chantier ', 'Place', 7.33, 'AUT-2', '2024-04-01', '2025-12-31', 2, 2024, 'actif', 1, '2024-01-01'),
        (3, '3', 3, 1, 'ODP-CHA', 'Souk', 3, None, '2025-07-01', None, 3, 2025, 'actif', 1, '2024-01-01'),
        (4, '4', 4, 1, 'Chantier', 'Corniche', 1, None, '2025-01-01', None, 1, None, 'suspendu', 1, '2024-01-01'),
    ]
    for r in rows:
        ins('occupations', r)
    d = lambda i, num, ref, y, t, total, statut: ins('declarations', (
        i, num, 'OCCUPATION_DOMAINE', ref, ref, 1, y, t, 0, 0, total, 0, 0, 0, total, statut,
        '2025-05-01', '2025-03-31', None, 1, None, '2025-05-01'))
    d(1, 'DCL-ODP202500001', 1, 2025, 1, 156.25, 'paye')
    d(2, 'DCL-ODP202500002', 1, 2025, 2, 156.25, 'emis')
    d(3, 'DCL-ODP202500003', 2, 2024, 0, 366.5, 'paye')     # année 2024 payée en entier
    d(4, 'DCL-ODP202500004', 2, 2025, 3, 91.63, 'annule')
    conn.commit()
    conn.close()


SCENARIOS = [
    {'id': 1, 'date': '2026-06-15', 'bv': 'BV-100', 'trims': ['2025_3', '2025_4', '2026_1']},
    {'id': 2, 'date': '2026-06-15', 'bv': 'BV-200', 'trims': ['2025_0', '2026_1']},
    {'id': 3, 'date': '2026-06-15', 'bv': '', 'trims': ['2025_3']},
]


def run_scenario(sc):
    tmp = os.path.join(tempfile.mkdtemp(prefix='odp_sc_'), 'sc.db')
    shutil.copy(DB_PATH, tmp)
    CURRENT_DB[0] = tmp
    data = [('date_declaration', sc['date']), ('numero_bulletin', sc['bv'])] + [('trims', k) for k in sc['trims']]
    with app.test_request_context(f"/occupation-domaine/{sc['id']}/declarer", method='POST', data=MultiDict(data)):
        declarer(sc['id'])
    conn = connect()
    out = []
    for r in conn.execute("SELECT * FROM declarations WHERE module='OCCUPATION_DOMAINE' AND reference_id=? AND id > 4 ORDER BY annee, trimestre", (sc['id'],)):
        r = dict(r)
        b = conn.execute('SELECT numero_bulletin, montant, statut FROM bulletins WHERE declaration_id=?', (r['id'],)).fetchone()
        out.append({k: r[k] for k in ('annee', 'trimestre', 'base_calcul', 'taux', 'montant_principal', 'montant_total', 'statut', 'date_echeance')}
                   | {'bulletin': dict(b) if b else None})
    conn.close()
    CURRENT_DB[0] = DB_PATH
    return out


def main():
    build_db()
    expected = {'today': TODAY.isoformat(), 'bases': [], 'liste': {}, 'scenarios': []}
    for sup in (12.5, 7.33, 0.333, 100):
        for unite, val in (('DH/m²/an', 50), ('DH/m²/trimestre', 12.75), ('DH/m²/mois', 30), ('DH/m²/jour', 1.5), ('DH', 99.99), ('DH/an', 33.33)):
            trim, an = odp._calculer_bases_tarif(sup, {'valeur': val, 'unite': unite})
            expected['bases'].append({'superficie': sup, 'valeur': val, 'unite': unite, 'trim': trim, 'an': an})
    conn = connect()
    data = odp._get_odp_items_data(conn)
    for it in data['items']:
        expected['liste'][str(it['id'])] = {k: it[k] for k in ('trim_base', 'an_base', 'est_expire', 'nb_non_declares', 'nb_emises',
                                                               'nb_impayes', 'montant_impaye', 'montant_en_attente')}
    conn.close()
    for sc in SCENARIOS:
        expected['scenarios'].append({'input': sc, 'declarations': run_scenario(sc)})
    with open(os.path.join(OUT_DIR, 'odp.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(expected['liste']), 'occupations,', len(SCENARIOS), 'scénarios ->', os.path.join(OUT_DIR, 'odp.json'))


if __name__ == '__main__':
    main()
