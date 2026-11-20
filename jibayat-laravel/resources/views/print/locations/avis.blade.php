@php use App\Support\Format; $t = $lease->taxpayer; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.65; }
    .ltr { direction: ltr; unicode-bidi: embed; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 13pt; font-weight: bold; color: #B91C1C; }
</style></head>
<body>
    @include('print.partials.header', ['title' => 'Avis de non-paiement', 'titleAr' => 'إنذار بعدم الأداء'])

    <div class="letter">
        <table width="100%"><tr>
            <td class="ar">عـدد : .................... / {{ now()->year }}</td>
            <td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td>
            <td class="ar">رقم الملزم : {{ $t?->number ?? '—' }}</td>
        </tr></table>

        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            @if ($t?->company_name || $t?->person_type === 'morale')
                الشركة : {{ $t?->company_name_ar ?? $t?->company_name }}
                @if ($t?->ice)<br>المعرف الموحد للمقاولة (ICE) : {{ $t->ice }}@endif
                @if ($t?->rc) — السجل التجاري رقم : {{ $t->rc }}@endif
            @else
                السيد(ة) : {{ $t?->displayNameAr() ?? $t?->displayName() }}
                @if ($t?->cin)<br>الحامل(ة) لبطاقة التعريف الوطنية رقم : {{ $t->cin }}@endif
            @endif
            @if ($t?->address_ar || $t?->address)<br>العنوان : {{ $t->address_ar ?? $t->address }}@endif
        </div>

        <p><strong>الموضوع : إنذار بعدم أداء واجبات كراء المحل الجماعي رقم {{ $lease->shop?->number ?? $lease->premises_ref ?? $lease->number }} (العقد رقم {{ $lease->number }})</strong></p>
        <p class="center">ـ سلام تام بوجود مولانا الإمام المؤيد بالله ـ</p>
        <p>وبعـد،</p>
        <p>يُعلمكم رئيس المجلس الجماعي لـ {{ $communeAr }}، بأنه طبقاً لسجلات مصلحة الجبايات والتحصيل، تبيّن أن ذمتكم المالية ما تزال مشغولة
            بواجبات الكراء عن الفترة « {{ $periodAr }} » (<span class="ltr" dir="ltr">{{ $period }}</span>) مفصلة كالآتي :</p>

        <table class="data" dir="rtl">
            <thead><tr><th>الفترة / Période</th><th>عدد الأشهر</th><th>السومة الشهرية (درهم)</th><th>المبلغ (درهم)</th></tr></thead>
            <tbody>
                @foreach ($groups as $g)
                    <tr>
                        <td class="center">{{ (int) substr($g['mois_debut'], 5) }}/{{ substr($g['mois_debut'], 0, 4) }}@if ($g['mois_fin'] !== $g['mois_debut']) ← {{ (int) substr($g['mois_fin'], 5) }}/{{ substr($g['mois_fin'], 0, 4) }}@endif</td>
                        <td class="center">{{ $g['nb_mois'] }}</td>
                        <td class="center"><span dir="ltr">{{ Format::money($g['tarif']) }}</span></td>
                        <td class="center"><span dir="ltr">{{ Format::money($g['montant']) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>المجموع الإجمالي الواجب أداؤه فوراً : <span class="total"><span dir="ltr">{{ Format::money($total) }}</span> درهم</span></p>
        <p>وعليه، فإننا نُعذركم رسمياً وندعوكم لتسوية هذه المبالغ داخل أجل أقصاه 30 يوماً من تاريخ التوصل بهذا الإشعار، تجنباً لتطبيق إجراءات
            المتابعة والتحصيل الجبري المنصوص عليها في القانون رقم 15.97 بمثابة مدونة تحصيل الديون العمومية والقانون 47.06.</p>
        <p>وتقبلوا خالص التقدير والاحترام، والسلام.</p>
        <p>حرر بـ {{ $communeAr }} في : {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
        <p class="muted" style="font-size:8pt">المرجع القانوني : القانون رقم 47.06 والقانون رقم 15.97 بمثابة مدونة تحصيل الديون العمومية · ملف عـدد : {{ $lease->number }}</p>
    </div>
</body></html>
