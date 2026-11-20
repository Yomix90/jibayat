<?php

namespace App\Domain\Documents\Support;

/**
 * Nomenclature budgétaire officielle des recettes — copie exacte de Flask database.DEFAULT_BUDGET_NOMENCLATURE.
 * [chapitre, code GIR, libellé FR, libellé AR, domaine, domaine FR, domaine AR, module associé, ordre, réalisé N-1 (2020), proposé (2022)].
 * Les deux derniers montants ne servent qu'à l'exercice 2022 (reprise historique de la commune), comme dans Flask.
 */
final class BudgetNomenclature
{
    public const ITEMS = [
        ['10.10.10.11', '1110101011', 'Taxe de légalisation des signatures et de certification des documents', 'رسم تصحيح الإمضاء و الإشهاد بالتطابق', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 1, 369000.0, 370000.0],
        ['10.10.30.31', '1110103031', 'Droit d\'état civil', 'رسم الحالة المدنية', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 2, 255000.0, 270000.0],
        ['10.10.20.33', '1110102033', 'Frais d\'enquêtes de commodo et incommodo', 'صوائر ابحاث المنافع والمضار', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 3, 1700.0, 100.0],
        ['10.20.30.31', '1110203031', 'Numérotage des immeubles', 'ترقيم العمارات', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 4, 0.0, 100.0],
        ['10.30.20.21', '1110302021', 'Produit de vente de mobilier et matériel réformés', 'منتوج بيع أثاث وأدوات استغني عنها', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 5, 0.0, 40000.0],
        ['10.30.20.22', '1110302022', 'Produit de vente de fruits, arbres et bois', 'منتوج بيع الفواكه. الأشجار والأخشاب', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 6, 0.0, 100.0],
        ['10.30.20.23', '1110302023', 'Produit des dossiers d\'appels d\'offres et plans', 'منتوج اثمنة العروض و الانتقاء و بيع مختلف التصاميم', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 7, 19730.0, 20000.0],
        ['10.30.20.24', '1110302024', 'Vente des animaux et objets abandonnés en fourrière', 'بيع الحيوانات والأشياء المتخلى عنها في المحجز', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', 'FOURRIERE_ENCHERES', 8, 85600.0, 120000.0],
        ['10.40.10.11', '1110401011', 'Produit des amendes fiscales et de transactions en matière de taxes', 'المتصل من الدعائر الجبائية و التراضي فيما يتعلق بالضرائب', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 9, 3544.4, 500.0],
        ['10.40.30.31', '1110403031', 'Prélèvement sur les recettes réalisées pour le compte de tiers', 'اقتطاع من المداخيل المحققة لفائدة الغير', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 10, 0.0, 100.0],
        ['10.40.10.14', '1110401014', 'Pourcentage sur les ventes publiques effectuées par la collectivité', 'النسبة المئوية المقبوضة في البيوعات العمومية', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 11, 0.0, 14000.0],
        ['10.40.30.32', '1110403032', 'Redevance de fourrière communale', 'واجب المحجز الجماعي', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', 'FOURRIERE', 12, 174857.0, 130000.0],
        ['10.50.10', '1110501010', 'Part de la commune dans le produit de la TVA', 'منتوج الضريبة على القيمة المضافة', 'ADMINISTRATION', 'Administration Générale', 'مجال الإدارة العامة', null, 13, 9191000.0, 9191000.0],
        ['20.10.30.31', '1120103031', 'Remboursement des frais de transport par ambulance communale', 'استرجاع صواير النقل بواسطة سيارة الاسعاف الجماعية', 'SOCIAL', 'Affaires Sociales', 'مجال الشؤون الاجتماعية', null, 14, 31100.0, 30000.0],
        ['20.10.30.32', '1120103032', 'Vidange des fosses d\'aisance', 'إفراغ المراحيض', 'SOCIAL', 'Affaires Sociales', 'مجال الشؤون الاجتماعية', null, 15, 4080.0, 100.0],
        ['20.10.33.34', '1120103334', 'Raccordement au réseau d\'eau potable', 'الاتصال بشبكة الماء الصالح للشرب', 'SOCIAL', 'Affaires Sociales', 'مجال الشؤون الاجتماعية', null, 16, 535000.0, 950000.0],
        ['20.20.30.32', '1120203032', 'Droits de bibliothèque communale', 'الخزانة الجماعية', 'SOCIAL', 'Affaires Sociales', 'مجال الشؤون الاجتماعية', null, 17, 0.0, 100.0],
        ['20.30.20.21', '1120302021', 'Recettes des terrains et stades de sport', 'مدخول الملاعب الرياضية', 'SOCIAL', 'Affaires Sociales', 'مجال الشؤون الاجتماعية', null, 18, 0.0, 100.0],
        ['30.10.10.14', '1130101014', 'Taxe sur les terrains urbains non bâtis (TNB)', 'الرسم على الاراضي الحضرية غير المبنية', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', 'TNB', 19, 0.0, 20000.0],
        ['30.10.10.15', '1130101015', 'Taxe sur les opérations de construction (Permis de bâtir)', 'الرسم على رخص عمليات البناء', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 20, 600420.6, 600000.0],
        ['30.10.10.16', '1130101016', 'Taxe sur les opérations de lotissement', 'الرسم على تجزئة الأراضي', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 21, 0.0, 100.0],
        ['30.10.10.17', '1130101017', 'Taxe sur les opérations de morcellement', 'الرسم على تقسيم الأراضي', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 22, 0.0, 100.0],
        ['30.10.10.11', '1130101011', 'Taxe urbaine (Taxe d\'habitation)', 'رسم السكن', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 23, 0.0, 100.0],
        ['30.10.10.19', '1130101019', 'Taxe sur les services communaux (TSC)', 'رسم الخدمات الجماعية', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 24, 0.0, 100.0],
        ['30.20.30.32', '1130203032', 'Redevance d\'enlèvement des déchets et ordures industrielles', 'الرسم على رفع نفايات الحدائق و النفايات والصناعية', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 25, 0.0, 100.0],
        ['30.20.30.35', '1130203035', 'Transport des corps et défunts', 'نقل الأموات', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 26, 0.0, 100.0],
        ['30.20.10.11', '1130201011', 'Taxe sur la dégradation des chaussées et voirie', 'الرسم المترتب على اتلاف الطرق', 'TECHNIQUE', 'Affaires Techniques & Urbanisme', 'مجال الشؤون التقنية', null, 27, 0.0, 100.0],
        ['40.10.10.11', '1140101011', 'Taxe sur les débits de boissons', 'الرسم على المشروبات غير الكحولية', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', 'DEBITS_BOISSONS', 28, 89119.3, 100000.0],
        ['40.10.10.16', '1140101016', 'Impôt des patentes (reliquat)', 'الضريبة البتانتا', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 29, 0.0, 10000.0],
        ['40.10.10.18', '1140101018', 'Taxe principale d\'abattage aux abattoirs communaux', 'الضريبة الرسمية على الذبح', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 30, 0.0, 100.0],
        ['40.10.10.25', '1140101025', 'Taxe professionnelle (part de la commune)', 'الضريبة المهنية', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 31, 640832.48, 1300000.0],
        ['40.10.20.22', '1140102022', 'Droits perçus sur les marchés de bétail (Rahba)', 'الواجبات المقبوضة بسوق البهائم', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 32, 0.0, 100.0],
        ['40.10.20.23', '1140102023', 'Droits d\'entrée et de stationnement au souk hebdomadaire', 'واجب الدخول و الوقوف بالسوق الاسبوعي', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 33, 0.0, 100.0],
        ['40.10.20.21', '1140102021', 'Droits de place perçus dans les foires et places publiques', 'الواجبات المقبوضة بالساحات العمومية', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 34, 0.0, 100.0],
        ['40.10.20.25', '1140102025', 'Produit de location d\'outillages communaux', 'منتوج كراء أدوات في حوزة الجماعة', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 35, 0.0, 100.0],
        ['40.10.20.26', '1140102026', 'Loyers des bâtiments et locaux commerciaux communaux', 'البنايات التجارية', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', 'LOCATION_LOCAUX', 36, 912271.29, 820035.0],
        ['40.10.20.27', '1140102027', 'Droits d\'affermage et location du souk hebdomadaire', 'واجب إيجار السوق', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', 'AFFERMAGE_SOUKS', 37, 1045000.0, 1140000.0],
        ['40.10.20.28', '1140102028', 'Produit des bains maures et douches publics', 'منتوج الحمامات و الرشاشات', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 38, 0.0, 100.0],
        ['40.10.20.37', '1140102037', 'Taxe d\'occupation temporaire du domaine public communal', 'رسم شغل الملك العام الجماعي', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', 'OCCUPATION_DOMAINE', 39, 8436.4, 30000.0],
        ['40.10.30.35', '1140103035', 'Taxe sur le lavage des abats et boyaux', 'ضريبة غسل الأمعاء', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 40, 0.0, 100.0],
        ['40.10.30.43', '1140103043', 'Produit de distribution et vente d\'eau potable', 'منتوج الماء', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 41, 3804565.16, 4200000.0],
        ['40.20.10.16', '1140201016', 'Taxe sur le transport public de voyageurs', 'الرسم على النقل العمومي للمسافرين', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 42, 42480.0, 50880.0],
        ['40.20.30.32', '1140203032', 'Produit d\'exploitation des parcs de stationnement autos', 'منتوج استغلال محطات السيارات', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', null, 43, 0.0, 100.0],
        ['40.20.10.11', '1140201011', 'Taxe sur les licences de taxis et de transport public de voyageurs', 'الضريبة على وقوف سيارة الأجرة', 'ECONOMIQUE', 'Affaires Économiques', 'مجال الشؤون الاقتصادية', 'STATIONNEMENT', 44, 31508.0, 35600.0],
        ['50.10.10', '1150101000', 'Produits des intérêts des fonds placés au Trésor', 'فائدة الأموال المودوعة بالخزينة', 'SOUTIEN', 'Finances & Soutien', 'مجال الدعم والمالية', null, 45, 75507.31, 60000.0],
        ['50.20.10', '2350201011', 'Subventions et dotations accordées par l\'État', 'إمدادات من طرف الدولة', 'SOUTIEN', 'Finances & Soutien', 'مجال الدعم والمالية', null, 46, 0.0, 0.0],
        ['50.40.40', '1150404040', 'Recettes accidentelles et imprévues', 'مداخيل طارئة غير منتظرة', 'SOUTIEN', 'Finances & Soutien', 'مجال الدعم والمالية', null, 47, 8560.0, 100.0],
        ['23.50.10.10.11', '2350101011', 'Excédents de la première partie du budget communal', 'فائض الجزء الأول من الميزانية', 'SOUTIEN', 'Finances & Soutien', 'مجال الدعم والمالية', null, 48, 308279.29, 0.0],
    ];

    /** Domaines dans l'ordre d'affichage : code => [libellé AR, libellé FR]. */
    public const DOMAINS = [
        'ADMINISTRATION' => ['مجال الإدارة العامة', 'Administration Générale'],
        'SOCIAL' => ['مجال الشؤون الاجتماعية', 'Affaires Sociales'],
        'TECHNIQUE' => ['مجال الشؤون التقنية', 'Affaires Techniques & Urbanisme'],
        'ECONOMIQUE' => ['مجال الشؤون الاقتصادية', 'Affaires Économiques & Domaine Public'],
        'SOUTIEN' => ['مجال الدعم والمالية', 'Finances & Soutien'],
    ];
}
