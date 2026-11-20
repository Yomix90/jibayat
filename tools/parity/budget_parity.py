"""
Parité Budget : exécute les VRAIES vues Flask de modules/regie.py (regie_budget, regie_budget_synchroniser,
regie_budget_importer, regie_budget_ligne_ajouter) sur une base d'essai, et enregistre
tests/Fixtures/parity/budget.{db,json} et tests/Fixtures/parity/budget_import.xlsx.

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\budget_parity.py
"""
import inspect
import io
import json
import os
import sqlite3
import sys
import tempfile

import openpyxl

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
FLASK = os.path.join(ROOT, 'jibayat_app-main')
LARAVEL = os.path.join(ROOT, 'jibayat-laravel')
OUT_DIR = os.path.join(LARAVEL, 'tests', 'Fixtures', 'parity')
DB_PATH = os.path.join(OUT_DIR, 'budget.db')
WORK_DB = os.path.join(tempfile.mkdtemp(prefix='budget_'), 'work.db')

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
import database  # noqa: E402
import modules.regie as regie  # noqa: E402

CURRENT = {'path': DB_PATH}


def connect():
    conn = sqlite3.connect(CURRENT['path'])
    conn.row_factory = sqlite3.Row
    return conn


CAPTURE = {}
regie.get_db = connect
database.get_db = connect
regie.get_current_user = lambda: {'id': 1}
regie.render_template = lambda tpl, **kw: CAPTURE.update({tpl: kw}) or ''
regie.flash = lambda *a, **k: None
regie.redirect = lambda *a, **k: ''
regie.url_for = lambda *a, **k: ''
app = Flask(__name__)
app.secret_key = 'x'


def build_db():
    """Base importée côté Laravel : encaissements multi-taxes et une vente aux enchères (sans lignes budgétaires)."""
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)
    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    for i in range(1, 4):
        ins('contribuables', (i, f'C{i}', 'physique', f'Redevable{i}', 'Test', None, None, None, None, f'R{i}00', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    decls = [
        (1, 'D1', 'TNB', 1, 1, 1, 2026, 0, 0, 0, 9010, 0, 0, 0, 9010, 'paye', '2026-02-01', None, '2026-02-02', 1, None, '2026-02-01'),
        (2, 'D2', 'TNB', 2, 2, 1, 2025, 0, 0, 0, 500, 0, 0, 0, 500, 'paye', '2025-12-01', None, '2025-12-31', 1, None, '2025-12-01'),
        (3, 'D3', 'DEBITS_BOISSONS', 1, 3, 1, 2026, 1, 0, 0, 1150, 0, 0, 0, 1150, 'paye', '2026-05-15', None, '2026-05-16', 1, None, '2026-05-15'),
        (4, 'D4', 'LOCATION_LOCAUX', 1, 1, 1, 2026, 0, 0, 0, 840, 0, 0, 0, 840, 'paye', '2026-03-01', None, '2026-03-02', 1, None, '2026-03-01'),
        (5, 'D5', 'LOCATION_LOCAUX', 1, 1, 1, 2026, 0, 0, 0, 420, 0, 0, 0, 420, 'emis', '2026-04-01', None, None, 1, None, '2026-04-01'),
        (6, 'D6', 'STATIONNEMENT', 1, 2, 1, 2026, 1, 0, 0, 400, 0, 0, 0, 400, 'paye', '2026-04-10', None, '2026-04-10', 1, None, '2026-04-10'),
        (7, 'D7', 'FOURRIERE', 1, 3, 1, 2026, 0, 0, 0, 230, 0, 0, 0, 230, 'paye', '2026-06-01', None, '2026-06-01', 1, None, '2026-06-01'),
        (8, 'D8', 'OCCUPATION_DOMAINE', 1, 2, 1, 2026, 2, 0, 0, 300, 0, 0, 0, 300, 'paye', '2026-07-01', None, '2026-07-01', 1, None, '2026-07-01'),
        (9, 'D9', 'AFFERMAGE_SOUKS', 1, 3, 1, 2026, 0, 0, 0, 5000, 0, 0, 0, 5000, 'paye', '2026-01-10', None, '2026-01-10', 1, None, '2026-01-10'),
    ]
    for d in decls:
        ins('declarations', d)
    bulls = [
        (1, 'BV-1', 1, 1, 1, 9010, 'especes', '2026-02-02', 'paye', 1, 1, None, '2026-02-01', 'Q-1', '2026-02-02', None, None, None),
        (2, 'BV-2', 2, 2, 1, 500, 'especes', '2025-12-31', 'paye', 1, 1, None, '2025-12-01', 'Q-2', '2025-12-31', None, None, None),
        (3, 'BV-3', 3, 3, 1, 1150, 'especes', '2026-05-16', 'paye', 1, 1, None, '2026-05-15', 'Q-3', '2026-05-16', None, None, None),
        (4, 'BV-4', 4, 1, 1, 840, 'cheque', '2026-03-02', 'paye', 1, 1, None, '2026-03-01', 'Q-4', '2026-03-02', None, None, None),
        (5, 'BV-5', 5, 1, 1, 420, 'especes', '2026-04-01', 'en_attente', 1, None, None, '2026-04-01', None, None, None, None, None),
        (6, 'BV-6', 6, 2, 1, 400, 'especes', '2026-04-10', 'paye', 1, 1, None, '2026-04-10', 'Q-6', '2026-04-10', None, None, None),
        (7, 'BV-7', 7, 3, 1, 230, 'especes', '2026-06-01', 'paye', 1, 1, None, '2026-06-01', 'Q-7', '2026-06-01', None, None, None),
        (8, 'BV-8', 8, 2, 1, 300, 'especes', '2026-07-01', 'paye', 1, 1, None, '2026-07-01', 'Q-8', '2026-07-01', None, None, None),
        (9, 'BV-9', 9, 3, 1, 5000, 'virement', '2026-01-10', 'paye', 1, 1, None, '2026-01-10', 'Q-9', '2026-01-10', None, None, None),
    ]
    for b in bulls:
        ins('bulletins', b)
    conn.execute("INSERT INTO dossiers_fourriere (id, numero, contribuable_id, commune_id, immatriculation, type_vehicule, date_mise_fourriere, statut) "
                 "VALUES (1, 'FOU-1', 3, 1, '1234-A-5', 'voiture', '2025-10-01', 'vendu')")
    conn.execute("INSERT INTO fou_ventes (id, dossier_id, nom_acheteur, prix_adjudication, date_vente) VALUES (1, 1, 'Acheteur', 3500, '2026-03-20')")
    conn.execute("INSERT INTO fou_ventes (id, dossier_id, nom_acheteur, prix_adjudication, date_vente) VALUES (2, 1, 'Acheteur 2', 999, '2025-11-20')")
    conn.commit()
    conn.close()


def make_import_file():
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = 'Budget 2026'
    ws.append(['الفصول', 'نوع المداخيل', 'المحققة', 'المقترحة', 'المحققة 2026'])
    ws.append(['10.10.10.11', 'رسم تصحيح الإمضاء', 369000, 380000, 12000])
    ws.append([10103031, 'رسم الحالة المدنية', 255000, 260000, None])          # 8 chiffres sans points -> 10.10.30.31
    ws.append(['10.10.10.12', 'Chapitre corrigé', 1000, 2000, 0])              # correction officielle -> 10.10.30.31 (écrase)
    ws.append(['المجموع مجال الإدارة العامة', None, None, None, None])        # bascule vers SOCIAL
    ws.append(['20.99.10.11', 'Recette sociale locale', 10, 20, 30])          # hors nomenclature : domaine SOCIAL
    ws.append(['المجموع مجال الشؤون الاجتماعية', None, None, None, None])     # bascule vers TECHNIQUE
    ws.append(['30.10.10.14', 'TNB', 0, 25000, 0])                            # le réalisé synchronisé est conservé (0 importé)
    ws.append(['abc', 'ligne ignorée', 1, 2, 3])
    path = os.path.join(OUT_DIR, 'budget_import.xlsx')
    wb.save(path)
    return path


def view(annee):
    with app.test_request_context(f'/regie/budget?annee={annee}'):
        inspect.unwrap(regie.regie_budget)()
    kw = CAPTURE['regie/budget.html']
    return {'annees': kw['annees_dispo'], 'grand_total': kw['grand_total'],
            'domaines': [{k: d[k] for k in ('code', 'total_n1', 'total_propose', 'total_realise', 'total_ecart', 'total_taux')}
                         | {'lignes': [{k: l[k] for k in ('chapitre', 'code_gir', 'libelle', 'libelle_fr', 'libelle_ar', 'domaine', 'module_associe',
                                                          'montant_realise_n1', 'montant_propose', 'montant_realise', 'taux', 'ecart')} for l in d['lignes']]}
                         for d in kw['domaines']]}


def main():
    build_db()
    import_path = make_import_file()
    # Travail sur une copie : la base fixture reste sans lignes budgétaires (Laravel les crée comme Flask).
    import shutil
    shutil.copy(DB_PATH, WORK_DB)
    CURRENT['path'] = WORK_DB
    expected = {}
    expected['init_2026'] = view(2026)
    with app.test_request_context('/regie/budget/synchroniser', method='POST', data={'annee': '2026'}):
        inspect.unwrap(regie.regie_budget_synchroniser)()
    expected['sync_2026'] = view(2026)
    with open(import_path, 'rb') as fh:
        content = fh.read()
    with app.test_request_context('/regie/budget/importer', method='POST',
                                  data={'annee': '2026', 'fichier_excel': (io.BytesIO(content), 'budget_import.xlsx')}, content_type='multipart/form-data'):
        inspect.unwrap(regie.regie_budget_importer)()
    expected['import_2026'] = view(2026)
    with app.test_request_context('/regie/budget/ligne/ajouter', method='POST',
                                  data={'annee': '2026', 'chapitre': '40.99.10.11', 'libelle_fr': 'Nouvelle recette', 'libelle_ar': 'مدخول جديد',
                                        'domaine': 'ECONOMIQUE', 'montant_propose': '1500', 'montant_realise': '700', 'montant_realise_n1': '100'}):
        inspect.unwrap(regie.regie_budget_ligne_ajouter)()
    expected['add_2026'] = view(2026)
    expected['seed_2022'] = view(2022)
    with open(os.path.join(OUT_DIR, 'budget.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK ->', os.path.join(OUT_DIR, 'budget.json'), '| total 2026 réalisé :', expected['add_2026']['grand_total'])


if __name__ == '__main__':
    main()
