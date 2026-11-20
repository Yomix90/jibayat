"""
Parité Régie des timbres : exécute les VRAIES routes Flask de modules/regie.py (ouvrir_paquet, versement_employe,
creer_bordereau, index) sur une base d'essai et enregistre tests/Fixtures/parity/rg.{db,json}.
La base rg.db est l'état AVANT les opérations ; le JSON décrit l'état APRÈS (carnets, écarts, bordereau, statistiques).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\rg_parity.py
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
DB_PATH = os.path.join(OUT_DIR, 'rg.db')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
from werkzeug.datastructures import MultiDict  # noqa: E402
import modules.regie as rg  # noqa: E402

CURRENT = [DB_PATH]


class FrozenDate(_dt.date):
    @classmethod
    def today(cls):
        return TODAY


class FrozenDatetime(_dt.datetime):
    @classmethod
    def now(cls, tz=None):
        return _dt.datetime(TODAY.year, TODAY.month, TODAY.day, 10, 0, 0)


def connect():
    conn = sqlite3.connect(CURRENT[0])
    conn.row_factory = sqlite3.Row
    return conn


CAPTURE = {}
rg.get_db = connect
rg.date = FrozenDate
rg.datetime = FrozenDatetime
rg.get_current_user = lambda: {'id': 1}
rg.flash = lambda *a, **k: None
rg.redirect = lambda *a, **k: None
rg.url_for = lambda *a, **k: ''
rg.render_template = lambda tpl, **kw: CAPTURE.update({tpl: kw}) or ''
app = Flask(__name__)


def build_db():
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)
    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    ins('regie_valeurs', (1, 'timbre', 'Timbre 20 DH', 20, 50, 1))
    ins('regie_valeurs', (2, 'ticket', 'Ticket souk 2,5 DH', 2.5, 100, 1))
    ins('regie_services', (1, 'Service des souks', 1))
    ins('regie_services', (2, 'État civil', 1))
    ins('regie_employes', (1, 'M-1', 'Ait', 'Brahim', 1, 1))
    ins('regie_employes', (2, 'M-2', 'Ouali', 'Fatima', 2, 1))
    # paquets : (id, valeur, numero, premier, dernier, quantite, reception, statut, agent, creation)
    ins('regie_paquets', (1, 1, 'P-001', '1001', '1500', 500, '2026-01-10', 'recu', 1, '2026-01-10'))
    ins('regie_paquets', (2, 2, 'P-002', '1', '1003', 1003, '2026-02-01', 'recu', 1, '2026-02-01'))
    ins('regie_paquets', (3, 1, 'P-003', '1', '7', 7, '2026-03-01', 'recu', 1, '2026-03-01'))
    conn.commit()
    conn.close()


def main():
    build_db()
    tmp = os.path.join(tempfile.mkdtemp(prefix='rg_'), 'rg.db')
    shutil.copy(DB_PATH, tmp)
    CURRENT[0] = tmp
    ctx = lambda path, data=None: app.test_request_context(path, method='POST', data=MultiDict(data or []))
    for pid in (1, 2, 3):
        with ctx(f'/regie/paquets/{pid}/ouvrir'):
            inspect.unwrap(rg.ouvrir_paquet)(pid)
    c = connect()
    ids = {r['numero_carnet']: r['id'] for r in c.execute('SELECT id, numero_carnet FROM regie_carnets')}
    c.execute("UPDATE regie_carnets SET statut='affecte', service_id=1, employe_id=1, date_affectation='2026-04-01' WHERE numero_carnet IN ('P-001-C01','P-001-C02','P-002-C01')")
    c.commit()
    c.close()
    for num, montant, date_v in (('P-001-C01', 1000, '2026-05-02'), ('P-001-C02', 987.5, '2026-05-20'), ('P-002-C01', 250.25, '2026-06-01')):
        with ctx('/regie/versements/employe', [('carnet_id', str(ids[num])), ('montant_verse', str(montant)), ('date_versement', date_v), ('observation', 'test')]):
            inspect.unwrap(rg.versement_employe)()
    with ctx('/regie/versements/bordereau', [('carnet_ids', str(ids['P-001-C01'])), ('carnet_ids', str(ids['P-001-C02'])), ('date_versement', '2026-06-10'), ('numero_bordereau', '')]):
        inspect.unwrap(rg.creer_bordereau)()
    with app.test_request_context('/regie/'):
        inspect.unwrap(rg.index)()
    kw = CAPTURE['regie/index.html']
    c = connect()
    expected = {
        'carnets': [dict(r) for r in c.execute('SELECT numero_carnet, num_premier, num_dernier, statut, montant_verse, ecart, bordereau_id FROM regie_carnets ORDER BY numero_carnet')],
        'paquets': [dict(r) for r in c.execute('SELECT numero_paquet, statut FROM regie_paquets ORDER BY id')],
        'bordereaux': [dict(r) for r in c.execute('SELECT numero, date_versement, montant_total FROM regie_bordereaux')],
        'stats': dict(kw['stats']),
        'mois_verse': kw['mois_verse'],
        'versements': [['P-001-C01', 1000, '2026-05-02'], ['P-001-C02', 987.5, '2026-05-20'], ['P-002-C01', 250.25, '2026-06-01']],
        'today': TODAY.isoformat(),
    }
    c.close()
    with open(os.path.join(OUT_DIR, 'rg.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(expected['carnets']), 'carnets ->', os.path.join(OUT_DIR, 'rg.json'))


if __name__ == '__main__':
    main()
