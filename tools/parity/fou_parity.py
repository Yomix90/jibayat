"""
Parité Fourrière : exécute les VRAIES fonctions de Flask (modules/fourriere.py) sur une base SQLite d'essai,
à une date figée, et enregistre tests/Fixtures/parity/fou.{db,json}.

Comparé : _nb_jours, _enrichir_dossier (montant dû), _get_tarif, _get_fou_items_data (indicateurs) et la route
fou_payer (sans décorateurs, sur une copie de la base).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\fou_parity.py
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
DB_PATH = os.path.join(OUT_DIR, 'fou.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
from werkzeug.datastructures import MultiDict  # noqa: E402
import modules.helpers as helpers  # noqa: E402
import modules.fourriere as fou  # noqa: E402

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


for mod in (helpers, fou):
    mod.get_db = connect
    mod.date = FrozenDate
    mod.datetime = FrozenDatetime
fou.get_current_user = lambda: {'id': 1}
helpers.check_user_permission = lambda user, perm: True
fou.flash = lambda *a, **k: None
fou.redirect = lambda *a, **k: None
fou.url_for = lambda *a, **k: ''
payer = inspect.unwrap(fou.fou_payer)
app = Flask(__name__)


def build_db():
    os.makedirs(OUT_DIR, exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)
    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    ins('rubriques', (1, '40102030', 'Fourrière', 'المحجز', 'FOURRIERE', None, 1, None))
    ins('arretes_fiscaux', (1, 'AF-2020-001', 'Initial', '2020-01-01', None, 'actif', None, None, '2020-01-01'))
    # (id, rubrique, arrete, code, libelle, valeur, unite, debut, fin, actif, smin, smax)
    ins('tarifs', (1, 1, 1, None, 'Voiture particulière / jour', 25, 'DH/jour', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (2, 1, 1, None, 'Moto / Scooter / jour', 15, 'DH/jour', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (3, 1, 1, None, 'Camion / jour', 50, 'DH/jour', '2020-01-01', None, 1, 0, None))
    ins('tarifs', (4, 1, 1, None, 'Frais de remorquage', 150, 'DH', '2020-01-01', None, 1, 0, None))
    # dossiers : (id, numero, ctb, commune, immat, type, date_mise, motif, nb_jours, frais, statut, restitution, actif, creation,
    #             num_depot, numero_immat, deposant, nom_prop, cin_prop, tel_prop, tarif_j, num_bull, date_paie, agent, sortie, notes)
    D = lambda i, typ, mise, frais, tarif, statut='en_fourriere': ins('dossiers_fourriere', (
        i, str(i), None, 1, f'{i}-A-{i}', typ, mise, 'Stationnement gênant', 0, frais, statut, None, 1, '2024-01-01',
        f'DEP-{i}', None, 'Police', None, None, None, tarif, None, None, None, None, None))
    D(1, 'Voiture particulière / jour', '2026-06-15', 150, 25)       # entré aujourd'hui : 1 jour
    D(2, 'Moto / Scooter / jour', '2026-05-01', 0, 15)
    D(3, 'Camion / jour', '2025-09-23', 150, 50)                      # ≥ 265 jours
    D(4, 'Voiture particulière / jour', '2025-06-01', 99.99, 25)      # ≥ 365 jours
    D(5, 'Voiture particulière / jour', '2026-01-10', 150, 25, 'en_attente_sortie')
    D(6, 'Moto / Scooter / jour', '2026-02-02', 0, 15, 'sortie')
    D(7, 'Inconnu', '2026-06-01', 0, 0)
    conn.commit()
    conn.close()


def main():
    build_db()
    conn = connect()
    expected = {'today': TODAY.isoformat(), 'dossiers': {}, 'tarifs': {}, 'scenarios': []}
    for r in conn.execute('SELECT * FROM dossiers_fourriere ORDER BY id'):
        e = fou._enrichir_dossier(r)
        expected['dossiers'][str(r['id'])] = {'nb_jours': e['nb_jours'], 'montant_du': e['montant_du'], 'plus265': e['plus265'], 'plus365': e['plus365']}
    for lib in ('Voiture particulière / jour', 'Moto / Scooter / jour', 'Camion / jour', 'Inconnu'):
        expected['tarifs'][lib] = fou._get_tarif(conn, lib)
    expected['stats'] = fou._get_fou_items_data(conn)['stats']
    expected['nb_jours'] = {d: fou._nb_jours(d) for d in ('2026-06-15', '2026-06-14', '2025-06-15', '2026-07-01', '')}
    conn.close()

    for sc in ({'id': 3, 'bv': 'BV-300', 'nom': 'Haddad', 'cin': 'K1'}, {'id': 1, 'bv': 'BV-100', 'nom': 'Bennani', 'cin': ''}):
        tmp = os.path.join(tempfile.mkdtemp(prefix='fou_sc_'), 'sc.db')
        shutil.copy(DB_PATH, tmp)
        CURRENT_DB[0] = tmp
        data = [('numero_bulletin', sc['bv']), ('nom_proprietaire', sc['nom']), ('cin_proprietaire', sc['cin']), ('telephone_prop', '0600')]
        with app.test_request_context(f"/fourriere/{sc['id']}/payer", method='POST', data=MultiDict(data)):
            payer(sc['id'])
        c = connect()
        d = dict(c.execute("SELECT * FROM declarations WHERE module='FOURRIERE' AND reference_id=?", (sc['id'],)).fetchone())
        b = dict(c.execute('SELECT numero_bulletin, montant, statut FROM bulletins WHERE declaration_id=?', (d['id'],)).fetchone())
        dos = dict(c.execute('SELECT nom_proprietaire, cin_proprietaire, numero_bulletin FROM dossiers_fourriere WHERE id=?', (sc['id'],)).fetchone())
        expected['scenarios'].append({'input': sc, 'declaration': {k: d[k] for k in ('annee', 'base_calcul', 'montant_principal', 'montant_total', 'statut', 'notes')},
                                      'bulletin': b, 'dossier': dos})
        c.close()
        CURRENT_DB[0] = DB_PATH

    with open(os.path.join(OUT_DIR, 'fou.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(expected['dossiers']), 'dossiers ->', os.path.join(OUT_DIR, 'fou.json'))


if __name__ == '__main__':
    main()
