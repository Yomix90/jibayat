"""
Parité Importation Excel : exécute les VRAIES vues Flask de modules/importation.py (apercu, executer) sur des fichiers
d'essai (tests/Fixtures/parity/import_<module>.xlsx) et enregistre tests/Fixtures/parity/imp.{db,json}.

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\imp_parity.py
"""
import inspect
import io
import json
import os
import shutil
import sqlite3
import sys
import tempfile

import openpyxl

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
FLASK = os.path.join(ROOT, 'jibayat_app-main')
LARAVEL = os.path.join(ROOT, 'jibayat-laravel')
OUT_DIR = os.path.join(LARAVEL, 'tests', 'Fixtures', 'parity')
DB_PATH = os.path.join(OUT_DIR, 'imp.db')
WORK = os.path.join(tempfile.mkdtemp(prefix='imp_'), 'work.db')

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')
sys.path.insert(0, FLASK)

from flask import Flask  # noqa: E402
import modules.importation as imp  # noqa: E402

CUR = {'p': DB_PATH}


def connect():
    conn = sqlite3.connect(CUR['p'])
    conn.row_factory = sqlite3.Row
    return conn


imp.get_db = connect
imp.get_current_user = lambda: {'id': 1}
imp.flash = lambda *a, **k: None
imp.redirect = lambda *a, **k: ''
imp.url_for = lambda *a, **k: ''
app = Flask(__name__)

FILES = {
    'DEBITS_BOISSONS': [
        ['CIN Propriétaire *', 'Nom Propriétaire *', 'Prénom Propriétaire', 'Téléphone', 'N° Dossier Établissement', 'Nom Établissement *', 'Type Établissement *',
         'Adresse Établissement', 'Superficie (m²)', 'N° Autorisation', 'Date Autorisation (AAAA-MM-JJ)', 'Trimestre Début * (1-4)', 'Année Début * (AAAA)'],
        ['JB1111', 'ALAOUI', 'Ahmed', '0611', '', 'Café Ennasr', 'cafe', 'Av. 1', 85, 'AUT-1', '2023-01-10', 2, 2023],   # contribuable existant (CIN)
        ['NEW001', 'BENNANI', 'Rachid', '0622', 'E-77', 'Restaurant Atlas', 'restaurant', 'Av. 2', 140, '', '', 1, 2024],  # nouveau contribuable
        ['', 'TAZI', 'Omar', '', '', 'Bar X', 'bar', '', '', '', '', 5, 2025],                                             # rapproché par nom + prénom, trimestre 5 -> 1
        ['ZZ9', 'SANS ETAB', '', '', '', '', 'cafe', '', '', '', '', 1, 2025],                                             # erreur à l'aperçu (nom établissement)
        ['YY8', 'MAUVAIS TYPE', '', '', '', 'Snack', 'kiosque', '', '', '', '', 1, 2025],                                 # erreur à l'aperçu (type)
    ],
    'CONTRIBUABLES': [
        ['N° Contribuable', 'Type Personne * (physique/morale)', 'Nom / Raison Sociale *', 'Prénom', 'N° CIN / Passeport', 'N° ICE', 'N° RC', 'Identifiant Fiscal (IF)', 'Téléphone', 'Email', 'Adresse'],
        ['', 'physique', 'ALAMI', 'Hicham', 'AA123456', '', '', '', '0661', 'a@b.ma', 'Centre'],
        ['', 'morale', 'STE ATLAS TRAVAUX SARL', '', '', '001524389000045', '12456', '', '0528', '', 'ZI'],
        ['', 'physique', 'ALAOUI', 'Ahmed', 'JB1111', '', '', '', '', '', ''],  # existant
    ],
}


def build_db():
    shutil.copy(os.path.join(OUT_DIR, 'ctb.db'), DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.execute("INSERT INTO arretes_fiscaux (id, numero, titre, date_effet, statut) VALUES (1, 'AF-1', 'Arrêté', '2020-01-01', 'actif')")
    conn.execute("INSERT INTO rubriques (id, code, libelle, module, actif) VALUES (1, 'TDB', 'Débits de boissons', 'DEBITS_BOISSONS', 1)")
    for i, (code, lib, val) in enumerate([('TDB-CAFE', 'Café / Salon de thé', 5), ('TDB-REST', 'Restaurant', 7), ('TDB-BAR', 'Bar / Brasserie', 10)], 1):
        conn.execute("INSERT INTO tarifs (id, rubrique_id, arrete_id, code_tarif, libelle, valeur, unite, date_debut, actif) VALUES (?, 1, 1, ?, ?, ?, '%', '2020-01-01', 1)",
                     (i, code, lib, val))
    conn.commit()
    conn.close()


class Upload(io.BytesIO):
    pass


def make_file(module):
    wb = openpyxl.Workbook()
    for row in FILES[module]:
        wb.active.append(row)
    path = os.path.join(OUT_DIR, f'import_{module.lower()}.xlsx')
    wb.save(path)
    return path


def run(view, module, path):
    with open(path, 'rb') as fh:
        data = {'module_code': module, 'fichier': (io.BytesIO(fh.read()), os.path.basename(path))}
    with app.test_request_context('/importation/x', method='POST', data=data, content_type='multipart/form-data'):
        return inspect.unwrap(view)()


def main():
    build_db()
    shutil.copy(DB_PATH, WORK)
    CUR['p'] = WORK
    out = {}
    for module in FILES:
        path = make_file(module)
        resp = run(imp.apercu, module, path)
        payload = (resp[0] if isinstance(resp, tuple) else resp).get_json()
        out[module] = {'apercu': {'total': payload['total_lignes'], 'valides': payload['nb_valides'],
                                  'lignes': [{'ligne': r['_ligne_excel'], 'valide': r['_est_valide'], 'erreurs': r['_erreurs'], 'existant': r['_ctb_statut'].startswith('👤'),
                                              'tarif': r['_tarif_libelle'], 'tarif_trouve': r['_tarif_trouve']} for r in payload['apercu']]}}
        run(imp.executer, module, path)
    conn = connect()
    out['etablissements'] = [dict(r) for r in conn.execute(
        "SELECT e.numero, e.nom_etablissement, e.type_etablissement, e.superficie, e.trimestre_debut_imposition, e.annee_debut_imposition, c.numero AS ctb, c.cin "
        "FROM etablissements_boissons e JOIN contribuables c ON c.id = e.contribuable_id WHERE e.id > 1 ORDER BY e.id")]
    out['contribuables'] = [dict(r) for r in conn.execute("SELECT nom, prenom, cin, ice, type_personne FROM contribuables WHERE id > 5 ORDER BY id")]
    out['historique'] = [dict(r) for r in conn.execute("SELECT module, nb_lignes_total, nb_succes, nb_erreurs FROM imports_historique ORDER BY id")]
    conn.close()
    with open(os.path.join(OUT_DIR, 'imp.json'), 'w', encoding='utf-8') as f:
        json.dump(out, f, ensure_ascii=False, indent=1)
    print(json.dumps({k: out[k] for k in ('etablissements', 'contribuables', 'historique')}, ensure_ascii=False, indent=1))


if __name__ == '__main__':
    main()
