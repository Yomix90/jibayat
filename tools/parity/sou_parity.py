"""
Parité Souk (affermage) : exécute les VRAIES fonctions de Flask (modules/souks.py) sur une base SQLite d'essai,
à une date figée, et enregistre tests/Fixtures/parity/sou.{db,json}.

Comparé : _calculer_periodes_renouvellement, _calculer_mois_non_payes_sou, _grouper_mois_par_periode,
_get_sou_items_data (liste) et la route sou_payer elle-même (sans décorateurs, sur une copie de la base).
Le projet Flask n'est pas modifié (remplacements en mémoire uniquement).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\sou_parity.py
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
DB_PATH = os.path.join(OUT_DIR, 'sou.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
from werkzeug.datastructures import MultiDict  # noqa: E402
import modules.helpers as helpers  # noqa: E402
import modules.souks as sou  # noqa: E402

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


for mod in (helpers, sou):
    mod.get_db = connect
    mod.date = FrozenDate
    mod.datetime = FrozenDatetime
sou.get_current_user = lambda: {'id': 1}
sou.flash = lambda *a, **k: None
sou.redirect = lambda *a, **k: None
sou.url_for = lambda *a, **k: ''
payer = inspect.unwrap(sou.sou_payer)
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
        ins('contribuables', (i, f'C{i}', 'physique', f'Fermier{i}', 'Test', None, None, None, None, f'S{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    # (id, numero, ctb, commune, souk, emplacement, activite, annuelle, debut, statut, actif, creation, fin, duree, taux, mensuelle)
    rows = [
        # 1 an renouvelable +5 % : périodes successives depuis 2023
        (1, '1', 1, 1, 'Souk Had', 'E-1', 'Fruits', 12000, '2023-03-15', 'actif', 1, '2024-01-01', None, 1, 5.0, 1000),
        # 2 ans, +7,5 %, redevance avec centimes ; fin de contrat avant aujourd'hui
        (2, '2', 2, 1, 'Souk Tlat', 'E-2', 'Légumes', 0, '2024-01-01', 'actif', 1, '2024-01-01', '2025-09-30', 2, 7.5, 333.33),
        # mensuelle absente ➜ annuelle / 12 ; taux 0 ➜ 5 % (Flask : « or 5.0 »)
        (3, '3', 3, 1, 'Souk Arbaa', 'E-3', 'Bétail', 9000, '2025-02-29' if False else '2025-02-28', 'actif', 1, '2024-01-01', None, 1, 0, 0),
        # 3 ans depuis le 29/02/2024 (année bissextile), taux 10 %
        (4, '4', 4, 1, 'Souk Khmis', 'E-4', 'Épices', 0, '2024-02-29', 'actif', 1, '2024-01-01', None, 3, 10, 1234.56),
        # contrat commençant dans le futur
        (5, '5', 5, 1, 'Souk Sebt', 'E-5', 'Divers', 0, '2026-09-01', 'actif', 1, '2024-01-01', None, 1, 5, 800),
    ]
    for r in rows:
        ins('affermages', r)
    # Déclarations existantes : contrat 1 — 03/2023→05/2023 payés ; 06/2023 émis ; une annulée (07/2023)
    d = lambda i, num, ref, notes, total, statut: ins('declarations', (
        i, num, 'AFFERMAGE_SOUKS', ref, ref, 1, 2023, 0, 1000, 0, total, 0, 0, 0, total, statut,
        '2023-06-01', '2023-06-01', None, 1, notes, '2023-06-01'))
    d(1, 'SOU-DECL-1', 1, '2023-03, 2023-04, 2023-05 | ok', 3000, 'paye')
    d(2, 'SOU-DECL-2', 1, '2023-06', 1000, 'emis')
    d(3, 'SOU-DECL-3', 1, '2023-07', 1000, 'annule')
    conn.commit()
    conn.close()


SCENARIOS = [
    {'id': 2, 'bv': 'BV-200', 'mois': ['2024-01', '2024-02', '2024-03']},
    {'id': 2, 'bv': 'BV-201', 'mois': [f'2025-0{m}' if m < 10 else f'2025-{m}' for m in range(1, 10)] and
        [f'{y}-{m:02d}' for y in (2024, 2025) for m in range(1, 13) if (y, m) <= (2025, 9)]},
    {'id': 4, 'bv': 'BV-400', 'mois': [f'{y}-{m:02d}' for y in (2024, 2025, 2026) for m in range(1, 13) if (2024, 2) <= (y, m) <= (2026, 6)]},
    {'id': 3, 'bv': 'BV-300', 'mois': ['2025-02', '2025-03']},
]


def run_scenario(sc):
    tmp = os.path.join(tempfile.mkdtemp(prefix='sou_sc_'), 'sc.db')
    shutil.copy(DB_PATH, tmp)
    CURRENT_DB[0] = tmp
    data = [('numero_bulletin', sc['bv']), ('mode_paiement', 'especes')] + [('mois_selectionnes', m) for m in sc['mois']]
    with app.test_request_context(f"/souks/{sc['id']}/payer", method='POST', data=MultiDict(data)):
        payer(sc['id'])
    conn = connect()
    r = conn.execute("SELECT * FROM declarations WHERE module='AFFERMAGE_SOUKS' AND reference_id=? AND id > 3", (sc['id'],)).fetchone()
    out = None
    if r:
        b = conn.execute('SELECT numero_bulletin, montant, statut FROM bulletins WHERE declaration_id=?', (r['id'],)).fetchone()
        out = {'annee': r['annee'], 'base_calcul': r['base_calcul'], 'montant_total': r['montant_total'], 'statut': r['statut'],
               'notes': r['notes'], 'bulletin': dict(b) if b else None}
    conn.close()
    CURRENT_DB[0] = DB_PATH
    return out


def to_json(v):
    if isinstance(v, (_dt.date,)):
        return v.isoformat()
    if isinstance(v, dict):
        return {k: to_json(x) for k, x in v.items()}
    if isinstance(v, list):
        return [to_json(x) for x in v]
    return v


def main():
    build_db()
    conn = connect()
    expected = {'today': TODAY.isoformat(), 'contrats': {}, 'liste': {}, 'scenarios': []}
    for a in conn.execute('SELECT * FROM affermages ORDER BY id'):
        rev = float(a['redevance_mensuelle'] or float(a['redevance_annuelle'] or 0) / 12)
        duree, taux = int(a['duree_contrat'] or 1), float(a['taux_augmentation'] or 5.0)
        periodes = sou._calculer_periodes_renouvellement(a['date_debut'] or '', rev, duree, taux)
        mois = sou._calculer_mois_non_payes_sou(a['id'], a['date_debut'] or '', a['date_fin'] or '', rev, duree, taux)
        expected['contrats'][str(a['id'])] = to_json({
            'rev_mens': rev, 'periodes': periodes,
            'mois': [{k: m[k] for k in ('mois', 'redevance', 'renouvellement', 'est_emis')} for m in mois],
            'groupes': [{k: g[k] for k in ('renouvellement', 'redevance_mensuelle', 'nb_mois', 'montant', 'mois_debut', 'mois_fin')}
                        for g in sou._grouper_mois_par_periode(mois)],
        })
    data = sou._get_sou_items_data(conn)
    for d in data['items_data']:
        expected['liste'][str(d['item']['id'])] = {k: d[k] for k in ('total_impaye', 'total_non_declare', 'total_en_attente',
                                                                     'nb_non_declares', 'nb_emises', 'nb_mois_impaye', 'rev_mens')}
    conn.close()
    for sc in SCENARIOS:
        expected['scenarios'].append({'input': sc, 'declaration': run_scenario(sc)})
    with open(os.path.join(OUT_DIR, 'sou.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(expected['contrats']), 'contrats,', len(SCENARIOS), 'scénarios ->', os.path.join(OUT_DIR, 'sou.json'))


if __name__ == '__main__':
    main()
