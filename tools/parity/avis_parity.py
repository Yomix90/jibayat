"""
Parité Avis & lettres : exécute les VRAIES vues Flask de modules/avis.py (avis, generer_avis, generer_lettre,
lettre_imprimer, lettre_approuver, lettre_annuler) sur la base d'essai des contribuables (+ un avis existant),
et enregistre tests/Fixtures/parity/avis.{db,json}.

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\avis_parity.py
"""
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
DB_PATH = os.path.join(OUT_DIR, 'avis.db')
WORK = os.path.join(tempfile.mkdtemp(prefix='avis_'), 'work.db')

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
import database  # noqa: E402
import modules.avis as av  # noqa: E402
import modules.helpers as helpers  # noqa: E402

CUR = {'p': DB_PATH}


def connect():
    conn = sqlite3.connect(CUR['p'])
    conn.row_factory = sqlite3.Row
    return conn


CAPTURE = {}
av.get_db = connect
database.get_db = connect
helpers.get_db = connect
av.get_current_user = lambda: {'id': 1}
av.render_template = lambda tpl, **kw: CAPTURE.update({tpl: kw}) or ''
av.flash = lambda *a, **k: None
av.redirect = lambda *a, **k: ''
av.url_for = lambda *a, **k: ''
app = Flask(__name__)


def build_db():
    shutil.copy(os.path.join(OUT_DIR, 'ctb.db'), DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.execute("INSERT INTO avis_non_paiement (id, numero_avis, declaration_id, contribuable_id, commune_id, montant_du, date_emission, lot_id, statut) "
                 "VALUES (1, 'AVS202600001', 2, 1, 1, 3000, '2026-03-01', 'LOT1', 'emis')")
    conn.commit()
    conn.close()


def overview(q=''):
    with app.test_request_context('/avis' + q):
        inspect.unwrap(av.avis)()
    kw = CAPTURE['admin/avis.html']
    return {'total': kw['total_global'], 'annees': kw['annees'],
            'modules': [{'module': m['module'], 'total': m['total'], 'nb_redevables': m['nb_redevables'], 'nb_avis_emis': m['nb_avis_emis'],
                         'rows': [[r['decl_numero'], r['numero_avis']] for r in m['rows']]} for m in kw['modules_list']],
            'stats': [{k: s[k] for k in ('module', 'nb_impayes', 'nb_redevables', 'total_du', 'nb_emis', 'total_recouvre')} for s in kw['stats_module']],
            'lettres': [[l['numero_lettre'], l['statut'], l['nb_redevables'], l['montant_total']] for l in kw['lettres']]}


def main():
    build_db()
    shutil.copy(DB_PATH, WORK)
    CUR['p'] = WORK
    out = {'initial': overview(), 'filtre_tnb': overview('?module=TNB'), 'filtre_emis': overview('?statut=emis'), 'filtre_2026': overview('?annee=2026')}
    with app.test_request_context('/avis/generer', method='POST', data={'module': 'TNB'}):
        inspect.unwrap(av.generer_avis)()
    out['apres_generer_tnb'] = overview()
    with app.test_request_context('/avis/generer', method='POST', data={'module': ''}):
        inspect.unwrap(av.generer_avis)()
    out['apres_generer_tout'] = overview()
    with app.test_request_context('/avis/lettre/generer', method='POST', data={'module': 'TNB', 'type_lettre': 'relance'}):
        inspect.unwrap(av.generer_lettre)()
    with app.test_request_context('/avis/lettre/1/imprimer'):
        inspect.unwrap(av.lettre_imprimer)(1)
    kw = CAPTURE['admin/lettre_notification.html']
    out['lettre'] = {'numero': kw['lettre']['numero_lettre'], 'nb': kw['lettre']['nb_redevables'], 'total': kw['lettre']['montant_total'],
                     'details': [[d['decl_numero'], d['numero_avis'], d['montant_du']] for d in kw['details']]}
    with app.test_request_context('/avis/lettre/1/approuver', method='POST'):
        inspect.unwrap(av.lettre_approuver)(1)
    with app.test_request_context('/avis/lettre/generer', method='POST', data={'module': 'DEBITS_BOISSONS', 'type_lettre': 'mise_en_demeure'}):
        inspect.unwrap(av.generer_lettre)()
    with app.test_request_context('/avis/lettre/2/annuler', method='POST'):
        inspect.unwrap(av.lettre_annuler)(2)
    out['final'] = overview()
    conn = connect()
    out['avis_lettre'] = {r['numero_avis']: r['lettre_id'] for r in conn.execute('SELECT numero_avis, lettre_id FROM avis_non_paiement ORDER BY id')}
    conn.close()
    with open(os.path.join(OUT_DIR, 'avis.json'), 'w', encoding='utf-8') as f:
        json.dump(out, f, ensure_ascii=False, indent=1)
    print('OK ->', os.path.join(OUT_DIR, 'avis.json'), out['lettre'], out['final']['lettres'])


if __name__ == '__main__':
    main()
