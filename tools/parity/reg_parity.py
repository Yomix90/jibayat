"""
Parité Registres : exécute les VRAIES vues Flask de modules/registre.py (index, registre_global, declarations_global)
en capturant les données passées au gabarit, sur une base d'essai multi-taxes, et enregistre
tests/Fixtures/parity/reg.{db,json}.

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\reg_parity.py
"""
import datetime as _dt
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
DB_PATH = os.path.join(OUT_DIR, 'reg.db')

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
import modules.registre as reg  # noqa: E402


def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


CAPTURE = {}
reg.get_db = connect
reg.get_current_user = lambda: {'id': 1}
reg.render_template = lambda tpl, **kw: CAPTURE.update({tpl: kw}) or ''
app = Flask(__name__)


def build_db():
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)
    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    for i in range(1, 6):
        ins('contribuables', (i, f'C{i}', 'physique', f'Redevable{i}', 'Test', None, None, None, None, f'R{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    # (id, numero, module, ref, ctb, commune, annee, trim, base, taux, principal, pen, maj, amende, total, statut, date_decl, ech, date_paie, agent, notes, creation)
    decls = [
        (1, 'DCL202500001', 'TNB', 1, 1, 1, 2025, 0, 450, 20, 9010, 901, 450.5, 1351.5, 11713, 'paye', '2025-06-01', '2025-02-28', '2025-06-02', 1, None, '2025-06-01'),
        (2, 'DCL202600002', 'TNB', 2, 2, 1, 2026, 0, 800, 10, 8000, 0, 0, 0, 8000, 'emis', '2026-02-01', '2026-02-28', None, 1, None, '2026-02-01'),
        (3, 'DCL-TDB2026003', 'DEBITS_BOISSONS', 1, 3, 1, 2026, 1, 20000, 5, 1000, 100, 50, 0, 1150, 'paye', '2026-05-15', '2026-04-30', '2026-05-16', 1, None, '2026-05-15'),
        (4, 'DCL-STA2026004', 'STATIONNEMENT', 1, 4, 1, 2026, 1, 100, 300, 400, 0, 0, 0, 400, 'emis', '2026-05-01', '2026-04-30', None, 1, None, '2026-05-01'),
        (5, 'LOC-DECL2026005', 'LOCATION_LOCAUX', 1, 5, 1, 2026, 0, 420, 0, 840, 0, 0, 0, 840, 'paye', '2026-03-01', '2026-03-01', '2026-03-02', 1, '2026-01, 2026-02', '2026-03-01'),
        (6, 'DCL-ODP2026006', 'OCCUPATION_DOMAINE', 1, 1, 1, 2026, 2, 10, 80, 200, 0, 0, 0, 200, 'annule', '2026-05-01', '2026-06-30', None, 1, None, '2026-05-01'),
        (7, 'DCL202500007', 'TNB', 3, 2, 1, 2025, 0, 120, 25.5, 3060, 0, 0, 0, 3060, 'sous_seuil', '2025-02-01', '2025-02-28', None, 1, None, '2025-02-01'),
    ]
    for d in decls:
        ins('declarations', d)
    # bulletins : (id, numero, decl, ctb, commune, montant, mode, date_paie, statut, agent, regisseur, notes, creation, quittance, date_q, motif, versement, encaissement)
    bulls = [
        (1, 'BV-1', 1, 1, 1, 11713, 'especes', '2025-06-01', 'paye', 1, 1, None, '2025-06-01', 'Q-1', '2025-06-02', None, None, None),
        (2, 'BV-2', 2, 2, 1, 8000, 'especes', '2026-02-01', 'en_attente', 1, None, None, '2026-02-01', None, None, None, None, None),
        (3, 'BV-3', 3, 3, 1, 1150, 'especes', '2026-05-15', 'paye', 1, 1, None, '2026-05-15', 'Q-3', '2026-05-16', None, None, None),
        (4, 'BV-5', 5, 5, 1, 840, 'cheque', '2026-03-01', 'paye', 1, 1, None, '2026-03-01', 'Q-5', '2026-03-02', None, None, None),
    ]
    for b in bulls:
        ins('bulletins', b)
    conn.commit()
    conn.close()


def main():
    build_db()
    expected = {'index': {}, 'global': {}, 'declarations': {}}
    with app.test_request_context('/registre/'):
        inspect.unwrap(reg.index)()
    kw = CAPTURE['registre/index.html']
    expected['index'] = {k: kw[k] for k in ('total_decls_nb', 'total_decls_montant', 'total_penalites_global', 'total_emis_global',
                                            'total_recouvre_global', 'total_reste_global', 'total_quittances_nb', 'taux_global')}
    expected['index']['modules'] = {k: {x: v[x] for x in ('nb_redevables', 'nb_declarations', 'principal_decl', 'penalites_decl', 'montant_emis',
                                                         'nb_quittances', 'montant_paye', 'reste_du', 'taux_recouvrement')}
                                    for k, v in kw['stats_modules'].items()}
    for annee, module, statut in (('all', 'all', 'all'), ('2026', 'all', 'all'), ('2026', 'TNB', 'all'), ('all', 'all', 'paye'), ('all', 'all', 'en_attente')):
        with app.test_request_context(f'/registre/global?annee={annee}&module={module}&statut={statut}'):
            inspect.unwrap(reg.registre_global)()
        kw = CAPTURE['registre/paiements_global.html']
        expected['global'][f'{annee}|{module}|{statut}'] = {'n': len(kw['lignes_global']), 'tot_emis': kw['tot_emis'], 'tot_recouvre': kw['tot_recouvre'],
                                                            'taux': kw['taux_global'], 'numeros': [l['decl_num'] for l in kw['lignes_global']]}
    for annee, module in (('all', 'all'), ('2025', 'all'), ('all', 'TNB')):
        with app.test_request_context(f'/registre/declarations_global?annee={annee}&module={module}'):
            inspect.unwrap(reg.declarations_global)()
        kw = CAPTURE['registre/declarations_global.html']
        expected['declarations'][f'{annee}|{module}'] = {'n': len(kw['declarations']), 'tot_base': kw['tot_base'], 'tot_principal': kw['tot_principal'],
                                                         'tot_penalites': kw['tot_penalites'], 'tot_total': kw['tot_total']}
    with open(os.path.join(OUT_DIR, 'reg.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK ->', os.path.join(OUT_DIR, 'reg.json'))


if __name__ == '__main__':
    main()
