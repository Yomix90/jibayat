@php
    use App\Domain\Boissons\Services\BeverageCalculator;
    $t = $outlet?->taxpayer;
    $dots = '......................................................';
    [$title, $titleAr, $heading] = match ($type) {
        'creation' => ['Déclaration de création', 'إقرار الإحداث', 'إقــرار بإحـداث مـحـل ومـزاولـة الـنـشـاط'],
        'cessation' => ['Déclaration de cessation', 'إقرار التوقف', 'إقــرار بـالـتـوقـف عـن مـزاولـة الـنـشـاط'],
        default => ["Déclaration du chiffre d'affaires — modèle 6", 'إقرار بالمداخيل — نموذج رقم 6', 'إقــرار بالمداخيل ورقم المعاملات المحققة'],
    };
@endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .form { direction: rtl; text-align: right; font-size: 10.5pt; }
    .form td { padding: 6px 6px; vertical-align: top; }
    .lbl { width: 42%; color: #374151; }
    .val { border-bottom: 0.6pt dotted #9CA3AF; }
    h2 { text-align: center; font-size: 13pt; margin: 10px 0; }
    .legal { border: 0.6pt solid #E6E8EC; background: #F7F8FA; padding: 6px 10px; font-size: 8.5pt; }
</style></head>
<body>
    @include('print.partials.header', ['title' => $title, 'titleAr' => $titleAr])
    <table width="100%" class="meta"><tr>
        <td>N° : {{ $number ?? '........................' }}</td><td class="ar" dir="rtl">تاريخ التسليم : {{ now()->format('d/m/Y') }}</td>
    </tr></table>
    <p class="ar center" dir="rtl" style="margin:4px 0">الرسم على محال بيع المشروبات</p>
    <h2 dir="rtl">{{ $heading }}</h2>

    <table width="100%" class="form" dir="rtl">
        <tr><td class="lbl">الاسم العائلي والشخصي للمستغل / الاسم التجاري :</td><td class="val">{{ $t ? ($t->displayNameAr() ?? $t->displayName()) : $dots }}</td></tr>
        <tr><td class="lbl">عنوان المراسلة الشخصي :</td><td class="val">{{ $t?->address_ar ?? $t?->address ?? $dots }}</td></tr>
        <tr><td class="lbl">رقم بطاقة التعريف الوطنية (CIN/RC) :</td><td class="val">{{ $t?->cin ?? $t?->rc ?? $dots }}</td></tr>
        <tr><td class="lbl">رقم الهاتف :</td><td class="val">{{ $t?->phone ?? $dots }}</td></tr>
        <tr><td class="lbl">صفة المستغل :</td><td>☐ المالك (Propriétaire) &nbsp; ☐ المسير (Gérant) &nbsp; ☐ المدير المسؤول (Directeur)</td></tr>
        <tr><td class="lbl">الاسم التجاري للمحل :</td><td class="val">{{ $outlet?->name ?? $dots }}</td></tr>
        <tr><td class="lbl">مقر وعنوان المؤسسة :</td><td class="val">{{ $outlet?->address ?? $dots }}</td></tr>
        @if ($type === 'creation')
            <tr><td class="lbl">تاريخ الشروع في النشاط :</td><td class="val">{{ $outlet?->authorized_on?->format('d/m/Y') ?? $dots }}</td></tr>
            <tr><td class="lbl">طبيعة النشاط الممارس :</td><td class="val">{{ $outlet ? $outlet->typeLabel() : $dots }}</td></tr>
        @elseif ($type === 'cessation')
            <tr><td class="lbl">تاريخ التوقف الفعلي عن النشاط :</td><td class="val">{{ $dots }}</td></tr>
            <tr><td class="lbl">سبب التوقف (إغلاق، تفويت، تغيير نشاط) :</td><td class="val">{{ $dots }}</td></tr>
        @else
            <tr><td class="lbl">رقم الرخصة :</td><td class="val">{{ $outlet?->authorization_number ?: $dots }}</td></tr>
        @endif
    </table>

    @if ($type === 'ca')
        <table class="data" dir="rtl">
            <thead><tr><th>الفصل / الفترة المعنية</th><th>المداخيل المحققة (رقم الأعمال)</th><th>مبلغ الرسم الواجب أداؤه ({{ rtrim(rtrim(number_format((float) $rate, 2, ',', ''), '0'), ',') }} %)</th></tr></thead>
            <tbody>
                @foreach ($quarters as $q)
                    <tr><td class="center">{{ BeverageCalculator::QUARTERS_AR[$q['trimestre']] }} {{ $q['annee'] }} — <span dir="ltr">T{{ $q['trimestre'] }} {{ $q['annee'] }}</span></td><td style="height:22px"></td><td></td></tr>
                @endforeach
                <tr><td class="center">فصل إضافي .................</td><td style="height:22px"></td><td></td></tr>
            </tbody>
            <tfoot><tr><td>المجموع :</td><td>..................................</td><td>..................................</td></tr></tfoot>
        </table>
    @endif

    <p class="ar" dir="rtl" style="font-size:9.5pt">
        @if ($type === 'cessation')
            تصريح بالشرف : أشهد أنا الموقع أسفله بصحة البيانات المصرح بها وأتحمل المسؤولية القانونية الكاملة، وألتزم بأداء كافة المستحقات والرسوم العالقة إلى غاية تاريخ التوقف الفعلي.
        @elseif ($type === 'creation')
            تصريح بالشرف : أشهد أنا الموقع أسفله بصحة ودقة البيانات المصرح بها في هذا الإقرار، وألتزم بتقديم الإقرارات الدورية في مواعيدها القانونية.
        @endif
    </p>

    <table width="100%" class="signatures">
        <tr><td>Signature du déclarant<br><span dir="rtl">توقيع المستغل المصرح</span></td><td>Pour l'ordonnateur<br><span dir="rtl">تأشيرة وخاتم المصلحة</span></td></tr>
    </table>

    <p class="legal ar" dir="rtl">
        @if ($type === 'creation')
            طبقاً للمادة 67 من القانون 47.06، يُودع هذا الإقرار لدى مصلحة الوعاء داخل أجل 30 يوماً من بداية النشاط.
        @elseif ($type === 'cessation')
            طبقاً لمقتضيات القانون رقم 47.06، يتعين الإدلاء بهذا الإقرار داخل أجل 45 يوماً من تاريخ التوقف عن مزاولة النشاط.
        @else
            مقتضيات قانونية : طبقاً للمادة 69 من القانون رقم 47.06، يتعين على الخاضعين للرسم إيداع إقرار برقم أعمالهم وأداء مبلغ الرسم تلقائياً لدى شساعة المداخيل الجماعية قبل انصرام الشهر الموالي لكل ربع سنة.
        @endif
    </p>
</body></html>
