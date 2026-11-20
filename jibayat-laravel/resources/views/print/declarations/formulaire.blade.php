@php
    use App\Support\Format;
    $dots = '..........................................................';
    $v = fn ($x) => filled($x) ? e($x) : '<span class="dots">'.$dots.'</span>';
    $date = fn ($x) => $x ? $x->format('d/m/Y') : '<span class="dots">..... / ..... / ..........</span>';
    $f = $t['fields'];
@endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .ref { font-size: 8pt; color: #6B7280; text-align: center; margin: 2px 0 8px; }
    .box { border: 0.6pt solid #CBD2DA; border-radius: 4px; margin-top: 8px; }
    .box-title { background: #1E2A3A; color: #fff; font-weight: bold; font-size: 9pt; padding: 4px 8px; }
    .box-title .ar { float: right; }
    table.form { width: 100%; border-collapse: collapse; }
    table.form td { padding: 5px 8px; font-size: 9pt; vertical-align: top; border-bottom: 0.4pt dotted #E6E8EC; }
    table.form td.lbl { width: 30%; color: #374151; }
    table.form td.lbl-ar { width: 30%; text-align: right; direction: rtl; color: #374151; }
    .dots { color: #9CA3AF; }
    .honor { font-size: 9pt; margin-top: 8px; line-height: 1.5; }
    .sig td { height: 70px; vertical-align: top; border: 0.6pt solid #CBD2DA; padding: 6px; font-size: 8.5pt; width: 50%; }
    .status { font-size: 8.5pt; padding: 4px 8px; background: #F2F3F5; margin-top: 6px; }
    .late { color: #B91C1C; font-weight: bold; }
    .cancel { color: #B91C1C; font-weight: bold; text-align: center; font-size: 11pt; border: 1pt solid #B91C1C; padding: 4px; margin-bottom: 6px; }
</style></head>
<body>
    @include('print.partials.header', ['title' => $t['fr'], 'titleAr' => $t['ar'], 'serviceFr' => 'Service d’assiette fiscale', 'serviceAr' => 'مصلحة الوعاء الجبائي'])
    @if ($d?->cancelled_at)<div class="cancel">ANNULÉE — ملغاة · {{ $d->cancellation_reason }}</div>@endif
    <p class="ref">{{ $m['fr'] }} — {{ $m['ar'] }} · @if ($t['art'] !== '—')Article {{ $t['art'] }} de la loi n° 47-06 modifiée par la loi n° 07-20 — المادة {{ $t['art'] }} من القانون رقم 47.06 كما تم تغييره بالقانون رقم 07.20 @else Déclaration interne de la commune — إقرار داخلي للجماعة @endif</p>

    <table width="100%" class="meta"><tr>
        <td>N° d’enregistrement / رقم التسجيل : <strong>{!! $d ? e($d->number) : $v(null) !!}</strong></td>
        <td class="num">Date de dépôt / تاريخ الإيداع : <strong>{!! $date($d?->filed_on) !!}</strong></td>
    </tr></table>

    <div class="box">
        <div class="box-title">1. Identification du redevable <span class="ar">1. هوية الملزم</span></div>
        <table class="form">
            <tr><td class="lbl">Nom et prénom / Raison sociale</td><td>{!! $v($taxpayer?->displayName()) !!}</td><td class="lbl-ar">الاسم الكامل / الاسم التجاري</td></tr>
            <tr><td class="lbl">Nom en arabe</td><td dir="rtl">{!! $v($taxpayer?->displayNameAr()) !!}</td><td class="lbl-ar">الاسم بالعربية</td></tr>
            <tr><td class="lbl">CIN / ICE / RC</td><td>{!! $v(collect([$taxpayer?->cin, $taxpayer?->ice, $taxpayer?->rc])->filter()->implode(' · ')) !!}</td><td class="lbl-ar">رقم البطاقة / المعرف الموحد / السجل التجاري</td></tr>
            <tr><td class="lbl">Adresse</td><td>{!! $v($taxpayer?->address) !!}</td><td class="lbl-ar">العنوان</td></tr>
            <tr><td class="lbl">Téléphone</td><td>{!! $v($taxpayer?->phone) !!}</td><td class="lbl-ar">الهاتف</td></tr>
        </table>
    </div>

    <div class="box">
        <div class="box-title">2. Objet de la déclaration <span class="ar">2. موضوع الإقرار</span></div>
        <table class="form">
            @if ($module === 'TNB')
                <tr><td class="lbl">N° du terrain / titre foncier</td><td>{!! $v($object ? $object->number.($object->land_title ? ' · TF '.$object->land_title : '') : null) !!}</td><td class="lbl-ar">رقم البقعة / الرسم العقاري</td></tr>
                <tr><td class="lbl">Lotissement · lot</td><td>{!! $v($object ? trim(($object->subdivision ?? '').' · '.($object->parcel_number ?? ''), ' ·') : null) !!}</td><td class="lbl-ar">التجزئة · رقم البقعة</td></tr>
                <tr><td class="lbl">Superficie (m²) · zone</td><td>{!! $v($object ? Format::money($object->area).' m² · zone '.$object->zone : null) !!}</td><td class="lbl-ar">المساحة · المنطقة</td></tr>
                <tr><td class="lbl">Adresse du terrain</td><td>{!! $v($object?->address) !!}</td><td class="lbl-ar">عنوان البقعة</td></tr>
            @elseif ($module === 'DEBITS_BOISSONS')
                <tr><td class="lbl">Établissement · n°</td><td>{!! $v($object ? $object->name.' · n° '.$object->number : null) !!}</td><td class="lbl-ar">المؤسسة · الرقم</td></tr>
                <tr><td class="lbl">Catégorie</td><td>{!! $v($object?->type) !!}</td><td class="lbl-ar">الصنف</td></tr>
                <tr><td class="lbl">Adresse de l’établissement</td><td>{!! $v($object?->address) !!}</td><td class="lbl-ar">عنوان المؤسسة</td></tr>
                <tr><td class="lbl">N° d’autorisation</td><td>{!! $v($object?->authorization_number) !!}</td><td class="lbl-ar">رقم الرخصة</td></tr>
            @else
                <tr><td class="lbl">Immatriculation · n°</td><td>{!! $v($object ? $object->registration.' · n° '.$object->number : null) !!}</td><td class="lbl-ar">رقم التسجيل · الرقم</td></tr>
                <tr><td class="lbl">Catégorie du véhicule</td><td>{!! $v($object?->type) !!}</td><td class="lbl-ar">صنف العربة</td></tr>
                <tr><td class="lbl">N° d’agrément</td><td>{!! $v($object?->authorization_number) !!}</td><td class="lbl-ar">رقم المأذونية</td></tr>
            @endif
        </table>
    </div>

    <div class="box">
        <div class="box-title">3. Contenu de la déclaration <span class="ar">3. مضمون الإقرار</span></div>
        <table class="form">
            @if (in_array('fiscal_year', $f, true))<tr><td class="lbl">Année concernée</td><td>{!! $v($d?->fiscal_year) !!}</td><td class="lbl-ar">السنة المعنية</td></tr>@endif
            @if (in_array('event_on', $f, true))
                <tr><td class="lbl">{{ ['cessation' => 'Date de l’arrêt d’activité', 'reprise' => 'Date de reprise de l’activité', 'creation' => 'Date de début d’activité'][$type] ?? 'Date du fait' }}</td>
                    <td>{!! $date($d?->event_on) !!}</td><td class="lbl-ar">{{ ['cessation' => 'تاريخ إيقاف النشاط', 'reprise' => 'تاريخ استئناف النشاط', 'creation' => 'تاريخ بداية النشاط'][$type] ?? 'تاريخ الواقعة' }}</td></tr>
            @endif
            @if (in_array('reason', $f, true))<tr><td class="lbl">Motif</td><td>{!! $v($d?->reason) !!}</td><td class="lbl-ar">السبب</td></tr>@endif
            @if (in_array('resume_on', $f, true))<tr><td class="lbl">Date de reprise prévue</td><td>{!! $date($d?->resume_on) !!}</td><td class="lbl-ar">التاريخ المتوقع لاستئناف النشاط</td></tr>@endif
            @if (in_array('amount', $f, true))<tr><td class="lbl">{{ $t['amount_label'] }}</td><td>{!! $v($d?->amount !== null ? Format::money($d->amount) : null) !!}</td><td class="lbl-ar">{{ $type === 'recettes_annuelles' ? 'المداخيل المحققة (درهم، دون احتساب الضريبة على القيمة المضافة)' : 'المساحة المصرح بها (م²)' }}</td></tr>@endif
            @if (in_array('inactivity', $f, true))
                <tr><td class="lbl">Inactivité totale / partielle</td><td>{!! $v(isset($d->details['inactivity']) ? ($d->details['inactivity'] === 'totale' ? 'Totale — كلية' : 'Partielle — جزئية') : null) !!}</td><td class="lbl-ar">العطالة كلية / جزئية</td></tr>
                <tr><td class="lbl">Partie inactive</td><td>{!! $v($d->details['inactive_part'] ?? null) !!}</td><td class="lbl-ar">الجزء الذي شملته العطالة</td></tr>
            @endif
            @if (in_array('new_holder', $f, true))<tr><td class="lbl">Nouveau titulaire / acquéreur</td><td>{!! $v($d->details['new_holder'] ?? null) !!}</td><td class="lbl-ar">المالك أو المستغل الجديد</td></tr>@endif
            @if (in_array('new_address', $f, true))<tr><td class="lbl">Nouvelle adresse</td><td>{!! $v($d->details['new_address'] ?? null) !!}</td><td class="lbl-ar">العنوان الجديد</td></tr>@endif
            <tr><td class="lbl">Observation</td><td>{!! $v($d->details['notes'] ?? null) !!}</td><td class="lbl-ar">ملاحظة</td></tr>
        </table>
    </div>

    @if ($d?->deadline)
        <div class="status">Délai légal / الأجل القانوني : {{ $d->deadline->format('d/m/Y') }}
            @if ($d->is_late) — <span class="late">Déposée hors délai — مودع خارج الأجل (majoration de 15 % prévue par la loi — زيادة 15% طبقا للقانون)</span>@endif</div>
    @else
        <div class="status">{{ $t['hint'] }}</div>
    @endif

    <p class="honor">Je soussigné(e) certifie l’exactitude des renseignements portés sur la présente déclaration.<br>
        <span dir="rtl" style="display:block; text-align:right">أشهد أنا الموقع(ة) أسفله بصحة المعلومات المضمنة في هذا الإقرار.</span></p>

    <table width="100%" class="sig" style="border-collapse: collapse; margin-top: 6px"><tr>
        <td>Signature du redevable — توقيع الملزم<br><span class="muted">Fait à ........................ le ..... / ..... / ..........</span></td>
        <td>Visa et cachet du service d’assiette — تأشيرة وخاتم مصلحة الوعاء<br>@if ($d)<span class="muted">Reçu par {{ $d->agent?->name }} le {{ $d->filed_on->format('d/m/Y') }}</span>@endif</td>
    </tr></table>
</body></html>
