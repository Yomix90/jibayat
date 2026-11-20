<?php

use App\Http\Controllers\Boissons\PrintController as BoissonsPrint;
use App\Http\Controllers\Documents\RegisterController;
use App\Http\Controllers\Caisse\NoticeController;
use App\Http\Controllers\Caisse\TaxpayerController;
use App\Http\Controllers\Documents\BudgetController;
use App\Http\Controllers\Documents\TresorController;
use App\Http\Controllers\DomainePublic\PrintController as OdpPrint;
use App\Http\Controllers\Fourriere\PrintController as FourrierePrint;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Locations\PrintController as LocationsPrint;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionStatusController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\Souk\PrintController as SoukPrint;
use App\Http\Controllers\Tnb\PrintController as TnbPrint;
use App\Http\Controllers\Transport\PrintController as TransportPrint;
use App\Livewire\Boissons;
use App\Livewire\Caisse;
use App\Livewire\Documents;
use App\Livewire\DomainePublic;
use App\Livewire\Fourriere;
use App\Livewire\Locations;
use App\Livewire\Parametres;
use App\Livewire\Regie;
use App\Livewire\Souk;
use App\Livewire\Tnb;
use App\Livewire\Transport;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

/*
| Écrans déjà construits : nom de route du registre ➜ composant Livewire plein écran.
| Les autres pages du registre restent des pages « en préparation » (PageController).
*/
$implemented = [
    'parametres.rubriques' => Parametres\Rubriques::class,
    'parametres.arretes' => Parametres\TaxOrders::class,
    'parametres.calcul' => Parametres\CalcParameters::class,
    'parametres.regie' => Parametres\Intervenants::class,
    'parametres.utilisateurs' => Parametres\Users::class,
    'parametres.roles' => Parametres\Roles::class,
    'parametres.journal' => Parametres\ActivityLog::class,
    'parametres.commune' => Parametres\Commune::class,
    'parametres.modules' => Parametres\Modules::class,
    'parametres.sessions' => Parametres\Sessions::class,
    'parametres.etat' => Parametres\SystemStatus::class,
    'parametres.importation' => Parametres\Importation::class,
    'parametres.imports' => Parametres\Imports::class,
    'parametres.sauvegardes' => Parametres\Sauvegardes::class,
    'parametres.rapports-email' => Parametres\RapportsEmail::class,
    'declarations.nouvelle' => \App\Livewire\Declarations\Nouvelle::class,
    'declarations.registre' => \App\Livewire\Declarations\Registre::class,
    'declarations.echeances' => \App\Livewire\Declarations\Echeances::class,
    'declarations.formulaires' => \App\Livewire\Declarations\Formulaires::class,
    'parametres.guide' => \App\Livewire\Aide\Guide::class,
    'parametres.a-propos' => \App\Livewire\Aide\APropos::class,
    'parametres.signaler' => \App\Livewire\Aide\Signaler::class,

    // Caisse du régisseur
    'caisse.bulletins' => Caisse\Bulletins::class,
    'caisse.validation-groupee' => Caisse\ValidationGroupee::class,
    'caisse.rejetes' => Caisse\BulletinsRejetes::class,
    'caisse.encaissements' => Caisse\Encaissements::class,

    // Locations
    'locations.locataires' => Locations\Locataires::class,
    'locations.fiche' => Locations\Fiche::class,
    'locations.encaisser' => Locations\Encaisser::class,
    'locations.secteurs' => Locations\Secteurs::class,
    'locations.boutiques' => Locations\Boutiques::class,
    'locations.synchronisation' => Locations\Synchronisation::class,
    'locations.tableau-de-bord' => Locations\TableauDeBord::class,
    'locations.registre' => Locations\Registre::class,
    'locations.avis' => Locations\Avis::class,

    // TNB
    'tnb.terrains' => Tnb\Terrains::class,
    'tnb.fiche' => Tnb\Fiche::class,
    'tnb.encaisser' => Tnb\Encaisser::class,
    'tnb.recensement' => Tnb\Recensement::class,
    'tnb.avis' => Tnb\Avis::class,
    'tnb.avis-lot' => Tnb\AvisLot::class,
    'tnb.tableau-de-bord' => Tnb\TableauDeBord::class,
    'tnb.registre' => Tnb\Registre::class,

    // Débits de boissons
    'boissons.etablissements' => Boissons\Etablissements::class,
    'boissons.fiche' => Boissons\Fiche::class,
    'boissons.encaisser' => Boissons\Encaisser::class,
    'boissons.declaration-annuelle' => Boissons\DeclarationAnnuelle::class,
    'boissons.cessation' => Boissons\Cessation::class,
    'boissons.declarations' => Boissons\Formulaires::class,
    'boissons.avis' => Boissons\Avis::class,
    'boissons.tableau-de-bord' => Boissons\TableauDeBord::class,
    'boissons.registre' => Boissons\Registre::class,

    // Transport (stationnement + TPV)
    'transport.vehicules' => Transport\Vehicules::class,
    'transport.fiche' => Transport\Fiche::class,
    'transport.encaisser' => Transport\Encaisser::class,
    'transport.avis' => Transport\Avis::class,
    'transport.tableau-de-bord' => Transport\TableauDeBord::class,
    'transport.registre' => Transport\Registre::class,

    // Souk (affermage)
    'souk.contrats' => Souk\Contrats::class,
    'souk.fiche' => Souk\Fiche::class,
    'souk.encaisser' => Souk\Encaisser::class,
    'souk.avis' => Souk\Avis::class,
    'souk.tableau-de-bord' => Souk\TableauDeBord::class,
    'souk.registre' => Souk\Registre::class,

    // Domaine public (ODP)
    'domaine-public.occupations' => DomainePublic\Occupations::class,
    'domaine-public.fiche' => DomainePublic\Fiche::class,
    'domaine-public.encaisser' => DomainePublic\Encaisser::class,
    'domaine-public.renouveler' => DomainePublic\Renouveler::class,
    'domaine-public.avis' => DomainePublic\Avis::class,
    'domaine-public.tableau-de-bord' => DomainePublic\TableauDeBord::class,
    'domaine-public.registre' => DomainePublic\Registre::class,

    // Fourrière
    'fourriere.dossiers' => Fourriere\Dossiers::class,
    'fourriere.fiche' => Fourriere\Fiche::class,
    'fourriere.encaisser' => Fourriere\Encaisser::class,
    'fourriere.sortie' => Fourriere\Sortie::class,
    'fourriere.encheres' => Fourriere\Encheres::class,
    'fourriere.groupes' => Fourriere\Groupes::class,
    'fourriere.ventes' => Fourriere\Ventes::class,
    'fourriere.tableau-de-bord' => Fourriere\TableauDeBord::class,
    'fourriere.registre' => Fourriere\Registre::class,
    'fourriere.parametres' => Fourriere\Parametres::class,

    // Contribuables
    'caisse.contribuables' => Caisse\Contribuables::class,
    'caisse.fiche-contribuable' => Caisse\FicheContribuable::class,
    'caisse.nouveau-contribuable' => Caisse\NouveauContribuable::class,
    'caisse.avis' => Caisse\Avis::class,
    'caisse.lettres' => Caisse\Lettres::class,
    // Régie des recettes (demande du 20/11/2026)
    'regie.tableau-de-bord' => Regie\TableauDeBord::class,
    'regie.saisie' => Regie\Saisie::class,
    'regie.brouillons' => Regie\Brouillons::class,
    'regie.recettes' => Regie\Recettes::class,
    'regie.livre-caisse' => Regie\LivreCaisse::class,
    'regie.etat-mensuel' => Regie\EtatMensuel::class,
    'regie.operations-tresor' => Regie\OperationsTresor::class,
    'regie.situation' => Regie\Situation::class,
    'regie.versements' => Regie\Versements::class,
    'regie.envois' => Regie\Envois::class,
    'regie.taxe-speciale' => Regie\TaxeSpeciale::class,
    'regie.virements' => Regie\Virements::class,
    'regie.pv-arrete' => Regie\PvArrete::class,
    'regie.pv-verification' => Regie\PvVerification::class,
    'regie.pv-recolement' => Regie\PvRecolement::class,
    'regie.pv-remise' => Regie\PvRemise::class,
    'regie.cloture' => Regie\Cloture::class,
    'regie.centre-controle' => Regie\CentreControle::class,
    'regie.rapprochement' => Regie\Rapprochement::class,
    'regie.etat-rapprochement' => Regie\EtatRapprochement::class,
    'regie.recap' => Regie\Recap::class,
    'regie.documents' => Regie\Documents::class,
    'regie.controle-registre' => Regie\ControleRegistre::class,
    'regie.scanner' => Regie\Scanner::class,
    'regie.boites' => Regie\Boites::class,
    'regie.stock' => Regie\Stock::class,
    'regie.saisie-tickets' => Regie\SaisieTickets::class,
    'regie.decharges-vignettes' => Regie\DechargesVignettes::class,
    'regie.etat-stock' => Regie\EtatStock::class,
    'regie.intelligence' => Regie\Intelligence::class,
    'regie.config-stock' => Regie\ConfigStock::class,
    'regie.rapports' => Regie\Rapports::class,
    'regie.tableau-recettes' => Regie\TableauRecettes::class,
    'regie.rapport-annuel' => Regie\RapportAnnuel::class,
    // États & documents
    'documents.grand-livre' => Documents\GrandLivre::class,
    'documents.declarations' => Documents\RegistreDeclarations::class,
    'documents.registres' => Documents\HubRegistres::class,
    'documents.pieces-annulees' => Documents\PiecesAnnulees::class,
    'documents.timbres' => Documents\Timbres::class,
    'documents.paquets' => Documents\Paquets::class,
    'documents.carnets' => Documents\Carnets::class,
    'documents.versements' => Documents\Versements::class,
    'documents.releves-gir' => Documents\RelevesGir::class,
    'documents.emission' => Documents\Emission::class,
    'documents.budget' => Documents\Budget::class,
];

// Langue d'interface (FR / ع) : enregistrée dans le compte, ou en session sur la page de connexion.
Route::post('/langue/{locale}', function (Illuminate\Http\Request $request, string $locale) {
    abort_unless(in_array($locale, App\Http\Middleware\SetLocale::LOCALES, true), 404);
    $request->session()->put('locale', $locale);
    $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

    return back();
})->name('langue')->middleware('throttle:30,1');

Route::middleware('auth')->group(function () use ($implemented) {
    Route::get('/', HomeController::class)->name('home');
    // Entrée d'un espace : /tnb ➜ tableau de bord du module (anciennes adresses /espaces/tnb redirigées).
    $spaceKeys = implode('|', array_map(fn ($k) => preg_quote($k, '/'), array_keys(require base_path('config/navigation.php'))));
    Route::get('/espaces/{space}', fn (string $space) => redirect('/'.$space, 301));
    Route::get('/{space}', SpaceController::class)->name('espace')->where('space', $spaceKeys);
    Route::get('/session/statut', SessionStatusController::class)->name('session.status');
    Route::get('/session/activite', SessionStatusController::class)->name('session.touch');

    // Une route par page du registre config/navigation.php : /{espace}/{page} ➜ "{espace}.{page}".
    foreach ((new Navigation(require base_path('config/navigation.php')))->pages() as $page) {
        $route = Route::get($page['uri'], $implemented[$page['route']] ?? PageController::class)
            ->name($page['route'])->middleware(['recent', 'module.active']);
        if ($page['permission']) {
            $route->middleware('can:'.$page['permission']);
        }
    }

    // Pages de détail (hors registre).
    Route::get('/parametres/arretes/{taxOrder}', Parametres\TaxOrderShow::class)
        ->name('parametres.arretes.show')->middleware('can:parametres.voir');

    // Impressions (PDF A4)
    Route::get('/locations/imprimer/registre', [LocationsPrint::class, 'register'])
        ->name('locations.repertoire')->middleware(['can:locations.voir', 'module.active']);
    Route::get('/locations/imprimer/avis/{lease}', [LocationsPrint::class, 'notice'])
        ->name('locations.avis-imprimer')->middleware(['can:locations.voir', 'module.active']);

    Route::middleware(['can:tnb.voir', 'module.active'])->group(function () {
        Route::get('/tnb/imprimer/dossiers', [TnbPrint::class, 'register'])->name('tnb.imprimer-dossiers');
        Route::get('/tnb/imprimer/avis', [TnbPrint::class, 'notices'])->name('tnb.avis-lot-imprimer');
        Route::get('/tnb/imprimer/avis/{parcel}', [TnbPrint::class, 'notices'])->name('tnb.avis-imprimer');
        Route::get('/tnb/documents/{document}', [TnbPrint::class, 'document'])->name('tnb.document');
    });

    Route::middleware(['can:boissons.voir', 'module.active'])->prefix('boissons/imprimer')->name('boissons.')->group(function () {
        Route::get('/liste', [BoissonsPrint::class, 'register'])->name('imprimer-liste');
        Route::get('/avis/{outlet}', [BoissonsPrint::class, 'notice'])->name('avis-imprimer');
        Route::get('/annuelle/{outlet}', [BoissonsPrint::class, 'annual'])->name('annuelle-imprimer');
        Route::get('/formulaire/{type}', [BoissonsPrint::class, 'form'])->name('formulaire')->whereIn('type', ['creation', 'ca', 'cessation']);
    });

    Route::middleware(['can:transport.voir', 'module.active'])->prefix('transport/imprimer')->name('transport.')->group(function () {
        Route::get('/liste', [TransportPrint::class, 'register'])->name('imprimer-liste');
        Route::get('/avis/{vehicle}', [TransportPrint::class, 'notice'])->name('avis-imprimer');
    });

    Route::middleware(['can:souk.voir', 'module.active'])->prefix('souk/imprimer')->name('souk.')->group(function () {
        Route::get('/liste', [SoukPrint::class, 'register'])->name('imprimer-liste');
        Route::get('/avis/{contract}', [SoukPrint::class, 'notice'])->name('avis-imprimer');
        Route::get('/documents/{document}', [SoukPrint::class, 'document'])->name('document');
    });

    Route::middleware(['can:domaine_public.voir', 'module.active'])->prefix('domaine-public/imprimer')->name('domaine-public.')->group(function () {
        Route::get('/liste', [OdpPrint::class, 'register'])->name('imprimer-liste');
        Route::get('/avis/{occupation}', [OdpPrint::class, 'notice'])->name('avis-imprimer');
    });

    Route::middleware(['can:fourriere.voir', 'module.active'])->prefix('fourriere/imprimer')->name('fourriere.')->group(function () {
        Route::get('/liste', [FourrierePrint::class, 'register'])->name('imprimer-liste');
        Route::get('/groupe/{group}', [FourrierePrint::class, 'group'])->name('groupe-imprimer');
        Route::get('/pv/{sale}', [FourrierePrint::class, 'report'])->name('pv');
    });

    Route::middleware(['can:registres.voir'])->prefix('documents')->name('documents.')->group(function () {
        Route::get('/export/{type}', [RegisterController::class, 'export'])->name('export')->whereIn('type', ['paiements', 'declarations']);
        Route::get('/imprimer/grand-livre', [RegisterController::class, 'grandLivre'])->name('imprimer-grand-livre');
        Route::get('/imprimer/pieces-annulees', [RegisterController::class, 'cancelled'])->name('imprimer-pieces-annulees');
    });
    Route::get('/documents/imprimer/bordereau/{remittance}', [RegisterController::class, 'remittance'])
        ->name('documents.bordereau-imprimer')->middleware(['can:regie.voir', 'module.active']);

    Route::middleware(['can:tgr.voir', 'module.active'])->prefix('documents/tgr')->name('documents.')->group(function () {
        Route::get('/imprimer', [TresorController::class, 'tgrPdf'])->name('tgr-imprimer');
        Route::get('/export', [TresorController::class, 'tgrExport'])->name('tgr-export')->middleware('can:regie.exporter');
    });
    Route::middleware(['can:emission.voir', 'module.active'])->prefix('documents/emission/imprimer')->name('documents.')->group(function () {
        Route::get('/bordereau/{slip}', [TresorController::class, 'slip'])->name('emission-imprimer');
        Route::get('/{year}/{month}', [TresorController::class, 'month'])->name('emission-imprimer-mois')->whereNumber(['year', 'month']);
    });

    Route::middleware(['can:budget.voir', 'module.active'])->prefix('documents/budget')->name('documents.')->group(function () {
        Route::get('/imprimer', [BudgetController::class, 'pdf'])->name('budget-imprimer');
        Route::get('/export', [BudgetController::class, 'export'])->name('budget-export')->middleware('can:budget.exporter');
    });

    Route::middleware(['can:contribuables.voir'])->prefix('contribuables')->name('caisse.')->group(function () {
        Route::get('/export', [TaxpayerController::class, 'export'])->name('contribuables-export')->middleware('can:contribuables.exporter');
        Route::get('/imprimer', [TaxpayerController::class, 'print'])->name('contribuables-imprimer')->middleware('can:contribuables.exporter');
        Route::get('/{taxpayer}/avis', [TaxpayerController::class, 'notice'])->name('contribuable-avis');
        Route::get('/documents/{document}', [TaxpayerController::class, 'document'])->name('contribuable-document');
    });

    Route::get('/importation/modele/{module}', function (string $module, App\Domain\Import\ExcelImportService $service) {
        abort_unless(isset(App\Domain\Import\ExcelImportService::MODULES[$module]), 404);
        $book = $service->template($module);

        return response()->streamDownload(fn () => (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output'),
            "Modele_Import_{$module}_".now()->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    })->name('parametres.importation-modele')->middleware('can:importation.voir');

    Route::get('/sauvegardes/{name}', function (string $name, App\Domain\Systeme\BackupService $service) {
        activity('systeme')->causedBy(auth()->user())->withProperties(['fichier' => $name])->log('Sauvegarde téléchargée');

        return response()->download($service->path($name));
    })->name('parametres.sauvegarde-telecharger')->middleware('can:config.gerer');

    Route::middleware(['can:contribuables.voir'])->prefix('declarations/imprimer')->name('declarations.')->group(function () {
        Route::get('/{declaration}', [App\Http\Controllers\Declarations\PrintController::class, 'declaration'])->name('imprimer')->whereNumber('declaration');
        Route::get('/formulaire/{module}/{type}', [App\Http\Controllers\Declarations\PrintController::class, 'blank'])->name('formulaire');
    });

    Route::middleware(['can:avis.voir'])->prefix('avis')->name('caisse.')->group(function () {
        Route::get('/export', [NoticeController::class, 'export'])->name('avis-export')->middleware('can:avis.exporter');
        Route::get('/lettres/{letter}/imprimer', [NoticeController::class, 'letter'])->name('lettre-imprimer');
    });

    // Régie des recettes : impressions et exports
    Route::prefix('regie/imprimer')->name('regie.imprimer.')->controller(App\Http\Controllers\Regie\PrintController::class)->group(function () {
        Route::middleware('can:recettes.voir')->group(function () {
            Route::get('/livre-caisse', 'livre')->name('livre');
            Route::get('/etat-mensuel', 'etatMensuel')->name('etat-mensuel');
            Route::get('/operations-tresor', 'operations')->name('operations');
            Route::get('/situation', 'situation')->name('situation');
            Route::get('/versement/{remittance}', 'versement')->name('versement');
            Route::get('/envoi/{dispatch}', 'envoi')->name('envoi');
            Route::get('/taxe-speciale', 'taxeSpeciale')->name('taxe-speciale');
            Route::get('/virement/{entry}', 'virement')->name('virement');
            Route::get('/pv/{report}', 'pv')->name('pv')->whereNumber('report');
            Route::get('/pv-vierge/{type}', 'pvBlank')->name('pv-vierge');
            Route::get('/rapprochement', 'rapprochement')->name('rapprochement');
            Route::get('/recap', 'recap')->name('recap');
            Route::get('/rapport', 'rapport')->name('rapport');
            Route::get('/tableau', 'tableau')->name('tableau');
            Route::get('/rapport-annuel', 'rapportAnnuel')->name('rapport-annuel');
        });
        Route::middleware('can:regie.voir')->group(function () {
            Route::get('/etat-stock', 'etatStock')->name('etat-stock');
            Route::get('/decharge/{discharge}', 'decharge')->name('decharge')->whereNumber('discharge');
            Route::get('/decharge-vierge', 'dechargeBlank')->name('decharge-vierge');
            Route::get('/decharge-vignettes', 'dechargeVignettes')->name('decharge-vignettes');
        });
        Route::get('/boite/{box}', 'boite')->name('boite')->middleware('can:archives.voir');
    });
    Route::get('/regie/scan/{document}', [App\Http\Controllers\Regie\PrintController::class, 'scan'])->name('regie.scan')->middleware('can:archives.voir');
    Route::get('/regie/export/tableau', [App\Http\Controllers\Regie\PrintController::class, 'exportTableau'])->name('regie.export.tableau')->middleware('can:recettes.exporter');
    Route::get('/regie/export/intelligence', [App\Http\Controllers\Regie\PrintController::class, 'exportIntelligence'])->name('regie.export.intelligence')->middleware('can:regie.exporter');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Empreinte / clé d'accès (WebAuthn) : enregistrement (connecté) et connexion (invité, limitée en fréquence).
Laragear\WebAuthn\Http\Routes::register()->middleware('throttle:20,1');

require __DIR__.'/auth.php';
