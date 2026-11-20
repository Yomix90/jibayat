"""
Parité Trésor : relevés GIR (modules/tgr_parser.py) et bordereaux d'émission (modules/emission/*).

Crée des fichiers Excel d'essai au format des exports TGR / GIR (tests/Fixtures/parity/tgr_releve.xlsx,
gir_bordereau_{01,02,03}.xlsx), exécute les VRAIES fonctions Flask (parse_releve, chiffre_en_lettre,
parse_bordereau_versement, regenerer_annee sans PDF) et enregistre tests/Fixtures/parity/tresor.json.

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\tresor_parity.py
"""
import datetime as _dt
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
DB_PATH = os.path.join(tempfile.mkdtemp(prefix='tresor_'), 'emission.db')

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

import modules.tgr_parser as tgr  # noqa: E402
import modules.emission.parser as eparser  # noqa: E402
import modules.emission.generator as egen  # noqa: E402


def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


egen.get_db = connect
egen.export_bordereau_pdf = lambda *a, **k: None

# BUG FLASK : _RowWrapper ne gère pas les tranches (row[::-1] dans _extract_operations) — tout import de relevé
# échoue en production (« Erreur lors du traitement du fichier »). Correctif minimal EN MÉMOIRE pour obtenir le
# résultat que le code voulait produire ; le projet Flask n'est pas modifié.
_orig_getitem = tgr._RowWrapper.__getitem__
tgr._RowWrapper.__getitem__ = lambda self, idx: self._cells[idx] if isinstance(idx, slice) else _orig_getitem(self, idx)


def make_tgr_file(path):
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.append(['Activité mensuelle de la régie'])
    ws.append(['COMMUNE/PROVINCE', 'Commune Aït Amira'])
    ws.append(['RÉGIE', 'Régie des recettes'])
    ws.append(['JOURNÉE COMPTABLE', 'Du 01/05/2026 au 31/05/2026'])
    ws.append([])
    header = [None] * 26
    header[1] = 'Mode de paiement'
    header[3] = 'Réf. paiement'
    header[5] = 'Date'
    header[7] = 'Partie versante'
    header[10] = 'Réf. créance'
    header[12] = 'Rubrique'
    header[14] = 'Principal'
    header[16] = 'Majoration'
    header[18] = 'Pénalités'
    header[21] = 'Astreintes'
    header[24] = 'Total'
    ws.append(header)
    ops = [
        ('Espèces', 1001, _dt.datetime(2026, 5, 3), 'BENNANI ALI', 55501, '1140102026\nProduit de location des locaux', 840, 0, 0, 0, 840),
        ('Chèque', 1002, '05/05/2026', 'SARL ATLAS', 55502, '1140101011 Taxe sur les débits de boissons', 1000, 50, 100, 0, 1150),
        ('Virement', 1003, '2026-05-20', 'TAZI', None, '1130101014\nTaxe sur les terrains urbains non bâtis', 9010, 450.5, 901, 1351.5, 11713),
        ('Espèces', 1004, _dt.datetime(2026, 5, 28), 'OUALI', 55504, '1140203033\nDroit de stationnement', 400, 0, 0, 0, 400),
    ]
    for mode, ref, d, partie, refc, lib, p, m, pe, a, t in ops:
        row = [None] * 26
        row[1], row[3], row[5], row[7], row[10], row[12] = mode, ref, d, partie, refc, lib
        row[14], row[16], row[18], row[21], row[24] = p, m, pe, a, t
        ws.append(row)
    sub = [None] * 26
    sub[1] = 'SOUS TOTAL'
    ws.append(sub)
    tot = [None] * 26
    tot[1] = 'TOTAL GENERAL'
    tot[24] = 14103
    ws.append(tot)
    wb.save(path)


def make_gir_file(path, mois_nom, lignes, total=None):
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.append(['ROYAUME DU MAROC'])
    ws.append(['BORDEREAU DE VERSEMENT'])
    ws.append([f'MOIS : {mois_nom}', None, 'EXERCICE : 2026'])
    ws.append(['Code Budgétaire', 'Nature de la recette', None, 'Montant'])
    for code, nature, montant in lignes:
        ws.append([code, nature, None, montant])
    if total is not None:
        ws.append(['TOTAL GENERAL', None, None, total])
    ws.append(['Le Régisseur'])
    wb.save(path)


def build_emission_db():
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    conn.commit()
    conn.close()


def main():
    os.makedirs(OUT_DIR, exist_ok=True)
    tgr_path = os.path.join(OUT_DIR, 'tgr_releve.xlsx')
    make_tgr_file(tgr_path)
    meta, ops = tgr.parse_releve(tgr_path)
    expected = {
        'tgr': {'meta': {k: (v.isoformat() if hasattr(v, 'isoformat') else v) for k, v in meta.to_dict().items() if k != 'date_edition'},
                'operations': [o.to_dict() for o in ops]},
        'lettres': {str(v): tgr.chiffre_en_lettre(v) for v in (0, 1, 21, 71, 80, 81, 91, 100, 200, 1000, 1080, 2000.5, 14103, 1000000, 2350177.75, 99.99)},
        'gir': {}, 'emission': {},
    }
    files = {
        1: ('JANVIER', [('1140102026', 'Loyers', 1200), ('1140101011', 'Débits de boissons', 300.5), ('1130101014', 'TNB', 9010)], 10510.5),
        2: ('FÉVRIER', [('1140102026', 'Loyers', 800), ('9999999', 'Recette diverse', 120), (None, 'Sans code', 50)], None),
        3: ('MARS', [('1140102026', 'Loyers', 1500.25), ('1140203033', 'Stationnement', 400)], 2000),
    }
    build_emission_db()
    conn = connect()
    for mois, (nom, lignes, total) in files.items():
        path = os.path.join(OUT_DIR, f'gir_bordereau_{mois:02d}.xlsx')
        make_gir_file(path, nom, lignes, total)
        parsed = eparser.parse_bordereau_versement(path)
        expected['gir'][str(mois)] = parsed
        conn.execute('INSERT INTO bordereaux_versement (id, mois, annee, fichier_source, total_general, date_import) VALUES (?,?,?,?,?,?)',
                     (mois, parsed['mois'], parsed['annee'], os.path.basename(path), parsed['total_general'], '2026-06-01'))
        for l in parsed['lignes']:
            conn.execute('INSERT INTO lignes_recettes (bordereau_id, code_budgetaire, nature_recette, montant) VALUES (?,?,?,?)',
                         (mois, l['code_budgetaire'], l['nature_recette'], l['montant']))
    conn.execute("INSERT INTO emission_config (annee, premiere_partie_mode, premiere_partie_valeur, deuxieme_partie_mode, deuxieme_partie_valeur, notes) VALUES (2026, 'auto', 0, 'auto', 0, '')")
    conn.commit()
    conn.close()
    gen = egen.regenerer_annee(2026, tempfile.mkdtemp())
    expected['emission'] = [{k: g[k] for k in ('numero_bordereau', 'mois', 'rubrique', 'code_budgetaire', 'intitule', 'montant_present', 'report_anterieurs', 'total',
                                               'total_present_global', 'report_global', 'premiere_partie_global', 'deuxieme_partie_global')} for g in gen]

    with open(os.path.join(OUT_DIR, 'tresor.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=1)
    print('OK :', len(ops), 'opérations TGR,', len(gen), "bordereaux d'émission ->", os.path.join(OUT_DIR, 'tresor.json'))


if __name__ == '__main__':
    main()
