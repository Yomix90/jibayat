"""
Parité Locations : exécute les VRAIES fonctions du module Flask (modules/location.py)
sur une base SQLite d'essai, à une date figée, et enregistre :
  - la base d'essai (tests/Fixtures/parity/locations.db) — importée telle quelle côté Laravel ;
  - les résultats attendus (tests/Fixtures/parity/locations.json).

Le projet Flask n'est PAS modifié : on importe ses fonctions et on remplace seulement,
dans ce processus, get_db (connexion à la base d'essai) et date.today() (date figée).

Usage :
    C:\\wamp64\\SGFC\\jibayat_app-main\\.venv\\Scripts\\python.exe tools\\parity\\locations_parity.py
"""
import datetime as _dt
import json
import os
import sqlite3
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
FLASK = os.path.join(ROOT, 'jibayat_app-main')
LARAVEL = os.path.join(ROOT, 'jibayat-laravel')
OUT_DIR = os.path.join(LARAVEL, 'tests', 'Fixtures', 'parity')
TODAY = _dt.date(2026, 6, 15)

os.environ['JIBAYAT_DATA_DIR'] = tempfile.mkdtemp(prefix='jibayat_parity_')  # n'écrit rien dans %APPDATA%
sys.path.insert(0, FLASK)

import modules.location as loc  # noqa: E402


class FrozenDate(_dt.date):
    @classmethod
    def today(cls):
        return TODAY


DB_PATH = os.path.join(OUT_DIR, 'locations.db')


def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


loc.get_db = connect
loc.date = FrozenDate


def build_db():
    os.makedirs(OUT_DIR, exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(LARAVEL, 'tests', 'Fixtures', 'flask_schema.sql'), encoding='utf-8').read())
    ins = lambda t, v: conn.execute(f"INSERT INTO {t} VALUES ({','.join('?' * len(v))})", v)

    ins('communes', (1, 'Commune test', None, None, None, None, None, None, None, None, None, 1))
    for i in range(1, 7):
        ins('contribuables', (i, f'C{i}', 'physique', f'Locataire{i}', 'Test', None, None, None, None, f'J{i}000', None, None,
                              None, None, None, None, None, None, 1, 1, '2024-01-01'))
    ins('rubriques', (1, '40102026', 'Location', 'كراء', 'LOCATION_LOCAUX', None, 1, None))
    # Arrêtés : 2024 (actif jusqu'au 01/01/2026) puis 2026
    ins('arretes_fiscaux', (1, 'AF-2024-001', 'Arrêté 2024', '2024-01-01', '2026-01-01', 'remplace', None, None, '2024-01-01'))
    ins('arretes_fiscaux', (2, 'AF-2026-002', 'Arrêté 2026', '2026-01-01', None, 'actif', None, None, '2026-01-01'))

    ins('loc_secteurs', (1, 'MAR', 'Marché central', None, 'fixe', 250, 'DH/mois', 1, '2024-01-01', 1))
    ins('loc_secteurs', (2, 'GAR', 'Gare routière', None, 'm2', 12.5, 'DH/m2/mois', 1, '2024-01-01', 2))
    # Tarifs de location : fixe synchronisé (historique 300 ➜ 350), m² synchronisé (20 ➜ 22.5), fixe non synchronisé
    ins('loc_tarifs', (1, 1, 'Boutique standard', 'fixe', 350, 'DH/mois', 1, '2024-01-01', 11))
    ins('loc_tarifs', (2, 1, 'Grand local', 'm2', 22.5, 'DH/m2/mois', 1, '2024-01-01', 12))
    ins('loc_tarifs', (3, 2, 'Kiosque', 'fixe', 180, 'DH/mois', 1, '2024-01-01', None))
    # Tarifs fiscaux (même code dans chaque arrêté = historique)
    ins('tarifs', (1, 1, 1, 'MAR-T1', 'Marché central — Boutique standard', 300, 'DH/mois', '2024-01-01', '2026-01-01', 0, 0, None))
    ins('tarifs', (11, 1, 2, 'MAR-T1', 'Marché central — Boutique standard', 350, 'DH/mois', '2026-01-01', None, 1, 0, None))
    ins('tarifs', (2, 1, 1, 'MAR-T2', 'Marché central — Grand local', 20, 'DH/m2/mois', '2024-01-01', '2026-01-01', 0, 0, None))
    ins('tarifs', (12, 1, 2, 'MAR-T2', 'Marché central — Grand local', 22.5, 'DH/m2/mois', '2026-01-01', None, 1, 0, None))

    ins('loc_boutiques', (1, 1, 'B-01', 'Boutique 1', 12, 'exploitee', None, '2024-01-01'))
    ins('loc_boutiques', (2, 2, 'G-01', 'Grand local 1', 30, 'exploitee', None, '2024-01-01'))
    ins('loc_boutiques', (3, 1, 'B-02', 'Boutique 2', 12, 'disponible', None, '2024-01-01'))

    # Baux : (id, numero, ctb, commune, ref_local, adresse, superficie, loyer, debut, fin, statut, actif, creation, secteur, boutique, tarif)
    ins('baux', (1, '1', 1, 1, 'B-01', 'Marché', 12, 300, '2025-10-01', None, 'actif', 1, '2025-10-01', 1, 1, 1))     # fixe + historique
    ins('baux', (2, '2', 2, 1, 'G-01', 'Marché', 30, 0, '2025-11-15', None, 'actif', 1, '2025-11-15', 1, 2, 2))       # m² + historique
    ins('baux', (3, '3', 3, 1, 'K-01', 'Gare', 0, 0, '2026-02-01', None, 'actif', 1, '2026-02-01', 2, None, 3))        # tarif non synchronisé
    ins('baux', (4, '4', 4, 1, 'S-01', 'Gare', 8, 0, '2026-03-01', '2026-05-20', 'actif', 1, '2026-03-01', 2, None, None))  # secteur m², date de fin
    ins('baux', (5, '5', 5, 1, 'L-01', 'Rue', 0, 420, '2026-01-01', None, 'actif', 1, '2026-01-01', None, None, None))  # loyer du bail
    ins('baux', (6, '6', 6, 1, 'B-03', 'Marché', 12, 300, '2027-01-01', None, 'actif', 1, '2026-06-01', 1, None, 1))   # pas encore commencé

    # Bail 1 : oct.–déc. 2025 payés, janv. 2026 émis (en attente) ; bail 2 : déc. 2025 payé
    ins('declarations', (1, 'LOC-DECL202500001', 'LOCATION_LOCAUX', 1, 1, 1, 2025, 0, 300, 0, 900, 0, 0, 0, 900, 'paye',
                         '2025-12-20', '2025-12-20', '2025-12-21', None, '2025-10, 2025-11, 2025-12', '2025-12-20'))
    ins('declarations', (2, 'LOC-DECL202600001', 'LOCATION_LOCAUX', 1, 1, 1, 2026, 0, 350, 0, 350, 0, 0, 0, 350, 'emis',
                         '2026-02-01', '2026-02-01', None, None, '2026-01 | acompte', '2026-02-01'))
    ins('declarations', (3, 'LOC-DECL202600002', 'LOCATION_LOCAUX', 2, 2, 1, 2025, 0, 600, 0, 600, 0, 0, 0, 600, 'paye',
                         '2026-01-05', '2026-01-05', '2026-01-06', None, '2025-12', '2026-01-05'))
    ins('declarations', (4, 'LOC-DECL202600003', 'LOCATION_LOCAUX', 2, 2, 1, 2026, 0, 675, 0, 675, 0, 0, 0, 675, 'annule',
                         '2026-02-05', '2026-02-05', None, None, '2026-01', '2026-02-05'))
    ins('bulletins', (1, 'BV-1', 1, 1, 1, 900, 'especes', '2025-12-20', 'paye', None, None, None, '2025-12-20', 'Q-1', '2025-12-21', None, None, None))
    ins('bulletins', (2, 'BV-2', 2, 1, 1, 350, 'especes', '2026-02-01', 'en_attente', None, None, None, '2026-02-01', None, None, None, None, None))
    ins('bulletins', (3, 'BV-3', 3, 2, 1, 600, 'especes', '2026-01-05', 'paye', None, None, None, '2026-01-05', 'Q-2', '2026-01-06', None, None, None))
    conn.commit()
    conn.close()


def main():
    build_db()
    conn = connect()
    baux = [dict(r) for r in conn.execute('SELECT * FROM baux ORDER BY id')]
    conn.close()
    expected = {'today': TODAY.isoformat(), 'leases': {}}
    for bail in baux:
        mois = loc._calculer_mois_non_payes(bail['id'], bail['date_debut'] or '', bail['date_fin'] or '', bail=bail)
        groupes = loc._grouper_par_tarif(mois)
        expected['leases'][str(bail['id'])] = {
            'loyer_mensuel': loc._calculer_loyer(bail),
            'mois_non_payes': mois,
            'total_du': round(sum(m['tarif'] for m in mois), 2),
            'groupes': [{k: g[k] for k in ('tarif', 'nb_mois', 'montant', 'mois_debut', 'mois_fin')} for g in groupes],
        }
    with open(os.path.join(OUT_DIR, 'locations.json'), 'w', encoding='utf-8') as f:
        json.dump(expected, f, ensure_ascii=False, indent=2, default=str)
    print('OK :', len(baux), 'baux ->', os.path.join(OUT_DIR, 'locations.json'))


if __name__ == '__main__':
    main()
