@php use App\Support\Format; use App\Domain\Documents\Services\RegisterService; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.65; }
    .ltr { direction: ltr; unicode-bidi: embed; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 13pt; font-weight: bold; color: #B91C1C; }
    tr.grp td { background: #F2F3F5; font-weight: bold; }
</style></head>
<body>
    @include('print.partials.header', ['title' => 'Avis de non-paiement', 'titleAr' => 'إنذار بعدم الأداء'])
    <div class="letter">
        <table width="100%"><tr>
            <td class="ar">عـدد : {{ $number }}</td><td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td><td class="ar">رقم الملزم : {{ $t->number }}</td>
        </tr></table>
        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            @if ($t->person_type === 'morale' || $t->company_name)
                الشركة : {{ $t->company_name_ar ?? $t->company_name }}@if ($t->ice)<br>المعرف الموحد للمقاولة (ICE) : {{ $t->ice }}@endif
            @else
                السيد(ة) : {{ $t->displayNameAr() ?? $t->displayName() }}@if ($t->cin)<br>الحامل(ة) لبطاقة التعريف الوطنية رقم : {{ $t->cin }}@endif
            @endif
            @if ($t->address_ar || $t->address)<br>العنوان : {{ $t->address_ar ?? $t->address }}@endif
        </div>
        <p><strong>الموضوع : إنذار بعدم أداء الرسوم والواجبات الجماعية</strong></p>
        <p class="center">ـ سلام تام بوجود مولانا الإمام المؤيد بالله ـ</p>
        @if (! $groups)
            <p>لا توجد أي مبالغ غير مؤداة في ذمتكم إلى حدود تاريخه.</p>
        @else
            <p>وبعـد، يُعلمكم رئيس المجلس الجماعي بأن ذمتكم المالية ما تزال مشغولة بالمبالغ التالية عن الفترة « {{ $periode['ar'] }} » (<span class="ltr" dir="ltr">{{ $periode['fr'] }}</span>) :</p>
            <table class="data" dir="rtl">
                <thead><tr><th>الرسم / Taxe</th><th>السنة</th><th>الفصل</th><th>الأصل</th><th>الغرامات والزيادات</th><th>المجموع (درهم)</th></tr></thead>
                <tbody>
                    @foreach ($groups as $code => $g)
                        <tr class="grp"><td colspan="5">{{ RegisterService::MODULES[$code]['ar'] ?? $g['label'] }} — {{ RegisterService::MODULES[$code]['fr'] ?? $g['label'] }} · {{ $g['periode']['ar'] }}</td>
                            <td class="center"><span dir="ltr">{{ Format::money($g['total']) }}</span></td></tr>
                        @foreach ($g['lignes'] as $l)
                            <tr><td></td><td class="center">{{ $l['annee'] }}</td><td class="center">{{ $l['trimestre'] ?: '—' }}</td>
                                <td class="center"><span dir="ltr">{{ Format::money($l['principal']) }}</span></td>
                                <td class="center"><span dir="ltr">{{ Format::money($l['penalite'] + $l['majoration'] + $l['amende']) }}</span></td>
                                <td class="center"><span dir="ltr">{{ Format::money($l['total']) }}</span></td></tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            <p>المجموع الإجمالي الواجب أداؤه : <span class="total"><span dir="ltr">{{ Format::money($total) }}</span> درهم</span></p>
            <p>وعليه، ندعوكم لتسوية هذه المبالغ قبل {{ \Illuminate\Support\Carbon::parse($deadline)->format('d/m/Y') }} لدى وكالة المداخيل الجماعية، تجنباً لتطبيق إجراءات
                التحصيل الجبري المنصوص عليها في القانون رقم 15.97 بمثابة مدونة تحصيل الديون العمومية والقانون 47.06.</p>
        @endif
        <p>وتقبلوا خالص التقدير والاحترام، والسلام.</p>
        <p>حرر بـ {{ $communeAr }} في : {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
    </div>
</body></html>
