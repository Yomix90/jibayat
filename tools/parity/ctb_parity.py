"""
Parité Contribuables : exécute les VRAIES vues Flask de modules/contribuables.py (contribuables, ctb_detail,
ctb_avis_non_paiement) en capturant les données du gabarit, et enregistre tests/Fixtures/parity/ctb.{db,json}.

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\ctb_parity.py
"""
import inspect
import json
import os
import sqlite3
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
FLASK = os.path.join(ROOT, 'jibayat_app-main')
LARAVEL = os.path.join(ROOT, 'jibayat-laravel')
OUT_DIR = os.path.join(LARAVEL, 'tests', 'Fixtures', 'parity')
DB_PATH = os.path.join(OUT_DIR, 'ctb.db')

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)
os.chdir(tempfile.mkdtemp(prefix='ctb_cwd_'))  # le module crée uploads/contribuables au chargement

from flask import Flask  # noqa: E402
import modules.contribuables as ctb  # noqa: E402
import modules.helpers as helpers  # noqa: E402


def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


CAPTURE = {}
ctb.get_db = connect
ctb.get_current_user = lambda: {'id': 1, 'role': 'super_admin'}
helpers.get_user_module_permissions = lambda *a, **k: {}
ctb.render_template = lambda tpl, **kw: CAPTURE.update({tpl: kw}) or ''
ctb.flash = lambda *a, **k: None
ctb.redirect = lambda *a, **k: ''
ctb.url_for = lambda *a, **k: ''
app = Flask(__name__)


def build_db():
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)
    ins('communes', (1, 'Commune test', 'جماعة', None, None, None, None, None, None, None, None, 1))
    ctbs = [
        (1, 'CTB1', 'physique', 'ALAOUI', 'Ahmed', 'العلوي', 'أحمد', None, None, 'JB1111', None, None, 'Rue 1', None, None, None, '0611', None, 1, 1, '2024-01-01'),
        (2, 'CTB2', 'morale', 'SARL', None, None, None, 'ATLAS SARL', 'أطلس', None, '0015', 'RC9', 'Zone', None, None, None, '0622', None, 1, 1, '2024-02-01'),
        (3, 'CTB3', 'physique', 'BENNANI', 'Sara', None, None, None, None, 'JC2222', None, None, None, None, None, None, None, None, 1, 1, '2024-03-01'),
        (4, 'CTB4', 'physique', 'TAZI', 'Omar', None, None, None, None, 'JD3333', None, None, None, None, None, None, None, None, 1, 1, '2024-04-01'),
        (5, 'CTB5', 'physique', 'INACTIF', 'X', None, None, None, None, None, None, None, None, None, None, None, None, None, 1, 0, '2024-05-01'),
    ]
    for c in ctbs:
        ins('contribuables', c)
    conn.execute("INSERT INTO dossiers_tnb (id, numero_dossier, contribuable_id) VALUES (1, 1, 1)")
    conn.execute("INSERT INTO terrains (id, numero_terrain, contribuable_id, commune_id, superficie, zone, statut, annee_debut_imposition, actif) VALUES (1, 'T1', 1, 1, 400, 'A', 'non_bati', 2020, 1)")
    conn.execute("INSERT INTO terrains (id, numero_terrain, contribuable_id, commune_id, superficie, zone, statut, annee_debut_imposition, actif) VALUES (2, 'T2', 1, 1, 300, 'A', 'non_bati', 2021, 1)")
    conn.execute("INSERT INTO etablissements_boissons (id, numero, contribuable_id, commune_id, nom_etablissement, type_etablissement, statut, actif) VALUES (1, 'E1', 2, 1, 'Café Atlas', 'cafe', 'actif', 1)")
    conn.execute("INSERT INTO vehicules (id, numero, contribuable_id, commune_id, immatriculation, type_vehicule, statut, actif) VALUES (1, 'V1', 3, 1, '12-A-3', 'grand_taxi', 'actif', 1)")
    decls = [
        (1, 'D1', 'TNB', 1, 1, 1, 2025, 0, 0, 0, 9010, 901, 450.5, 0, 10361.5, 'emis', '2025-02-01', '2025-02-28', None, 1, None, '2025-02-01'),
        (2, 'D2', 'TNB', 2, 1, 1, 2026, 0, 0, 0, 3000, 0, 0, 0, 3000, 'emis', '2026-02-01', '2026-02-28', None, 1, None, '2026-02-01'),
        (3, 'D3', 'TNB', 1, 1, 1, 2024, 0, 0, 0, 2000, 0, 0, 0, 2000, 'paye', '2024-02-01', '2024-02-28', '2024-03-01', 1, None, '2024-02-01'),
        (4, 'D4', 'DEBITS_BOISSONS', 1, 2, 1, 2026, 1, 0, 0, 1000, 100, 50, 0, 1150, 'emis', '2026-05-15', '2026-04-30', None, 1, None, '2026-05-15'),
        (5, 'D5', 'DEBITS_BOISSONS', 1, 2, 1, 2025, 4, 0, 0, 800, 0, 0, 0, 800, 'emis', '2026-01-15', '2026-01-31', None, 1, None, '2026-01-15'),
        (6, 'D6', 'STATIONNEMENT', 1, 3, 1, 2026, 2, 0, 0, 400, 0, 0, 0, 400, 'emis', '2026-05-01', '2026-06-30', None, 1, None, '2026-05-01'),
        (7, 'D7', 'STATIONNEMENT', 1, 3, 1, 2026, 1, 0, 0, 400, 0, 0, 0, 400, 'annule', '2026-04-01', '2026-03-31', None, 1, None, '2026-04-01'),
    ]
    for d in decls:
        ins('declarations', d)
    bulls = [
        (1, 'BV-1', 3, 1, 1, 2000, 'especes', '2024-03-01', 'paye', 1, 1, None, '2024-03-01', 'Q-1', '2024-03-01', None, None, None),
        (2, 'BV-2', 4, 2, 1, 1150, 'especes', '2026-05-16', 'valide', 1, 1, None, '2026-05-16', 'Q-2', '2026-05-16', None, None, None),
        (3, 'BV-3', 6, 3, 1, 400, 'especes', '2026-05-02', 'en_attente', 1, None, None, '2026-05-02', None, None, None, None, None),
    ]
    for b in bulls:
        ins('bulletins', b)
    conn.commit()
    conn.close()


def main():
    build_db()
    out = {'list': {}, 'detail': {}, 'avis': {}}
    for q, module in (('', ''), ('ALAOUI', ''), ('JC', ''), ('', 'TNB'), ('', 'AUCUN'), ('', 'DEBITS_BOISSONS')):
        with app.test_request_context(f'/contribuables?q={q}&module={module}'):
            inspect.unwrap(ctb.contribuables)()
        kw = CAPTURE['contribuables/contribuables.html']
        out['list'][f'{q}|{module}'] = {'total': kw['total_global_impaye'],
                                         'items': [{'numero': i['numero'], 'impaye': i['total_impaye'], 'modules': [[m['code'], m['nb']] for m in i['modules']]} for i in kw['items']]}
    for cid in (1, 2, 3, 4):
        with app.test_request_context(f'/contribuables/{cid}/detail'):
            inspect.unwrap(ctb.ctb_detail)(cid)
        kw = CAPTURE['contribuables/ctb_detail.html']
        out['detail'][str(cid)] = {'modules': [[m['code'], m['nb']] for m in kw['modules']], 'impaye': {k: v['montant'] for k, v in kw['impaye_par_module'].items()},
                                   'total_impaye': kw['total_impaye'], 'total_encaisse': kw['total_encaisse'],
                                   'declarations': [d['numero'] for d in kw['declarations']], 'bulletins': [b['numero_bulletin'] for b in kw['bulletins']]}
        with app.test_request_context(f'/contribuables/{cid}/avis-non-paiement'):
            inspect.unwrap(ctb.ctb_avis_non_paiement)(cid)
        kw = CAPTURE['contribuables/ctb_avis_non_paiement.html']
        out['avis'][str(cid)] = {'total': kw['total_montant'], 'periode_fr': kw['periode_globale']['texte_fr'], 'periode_ar': kw['periode_globale']['texte_ar'],
                                 'groups': {m: {'total': g['total'], 'periode_fr': g['periode_fr'], 'n': len(g['lignes'])} for m, g in kw['lignes_g'].items()},
                                 'numero': kw['avis_num']}
    with open(os.path.join(OUT_DIR, 'ctb.json'), 'w', encoding='utf-8') as f:
        json.dump(out, f, ensure_ascii=False, indent=1)
    print('OK ->', os.path.join(OUT_DIR, 'ctb.json'))


if __name__ == '__main__':
    main()
