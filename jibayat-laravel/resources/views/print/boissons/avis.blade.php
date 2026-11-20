@php $t = $outlet->taxpayer; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.7; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
</style></head>
<body>
    @include('print.partials.header', ['title' => 'Avis de non-paiement — Débits de boissons', 'titleAr' => 'إعذار أداء الرسم على محال بيع المشروبات'])

    <div class="letter">
        <table width="100%"><tr>
            <td class="ar">عـدد : <span dir="ltr">{{ $number }}</span></td>
            <td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td>
            <td class="ar">ملف عـدد : {{ $outlet->number }}</td>
        </tr></table>

        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            @if ($t?->company_name || $t?->person_type === 'morale')
                الشركة : {{ $t?->company_name_ar ?? $t?->company_name }}<br>المستغلة لمحل : « {{ $outlet->name }} »
            @else
                السيد(ة) : {{ $t?->displayNameAr() ?? $t?->displayName() }}<br>المستغل لمحل : « {{ $outlet->name }} »
            @endif
        </div>

        <p><strong>الموضوع : إنذار بالإقرار وأداء الرسم على محال بيع المشروبات</strong></p>
        <p class="center">ـ سلام تام بوجود مولانا الإمام المؤيد بالله ـ</p>
        <p>وبعـد،</p>
        <p>صلةً بالموضوع المشار إليه أعلاه، تُفيدكم مصالح الجبايات الجماعية بأنه تقيّد بسجلاتها محل بيع المشروبات الحامل للاسم التجاري
            « {{ $outlet->name }} » الكائن بـ {{ $outlet->address ?: 'نفوذ الجماعة' }} والمسجل في اسمكم.</p>
        <p>وحيث إنه تبيّن لمصالحنا عدم قيامكم بالتصريح برقم المعاملات وتسوية المستحقات الجبائية المترتبة في ذمتكم عن الفترة « {{ $periodAr }} »
            (<span dir="ltr">{{ $period }}</span>)، فإنكم ملزمون قانوناً بتسوية وضعيتكم الجبائية طبقاً لمقتضيات المواد 64 إلى 73 من القانون رقم 47.06
            المتعلق بجبايات الجماعات الترابية، والمحددة بنسبة <span dir="ltr">{{ number_format($rate, 0) }}%</span> من رقم الأعمال الإجمالي المحقق.</p>
        <p>لـذا، فإننا نُعذركم رسمياً وندعوكم للتقدم فوراً إلى مصلحة المداخيل والجبايات الجماعية قصد إيداع إقراراتكم برقم المعاملات المحقق خلال
            الفترة المذكورة وأداء الرسم المستحق، وذلك داخل أجل أقصاه 30 يوماً من تاريخ التوصل بهذا الإعذار، تجنباً لتطبيق الجزاءات والذعائر الجبائية
            المفروضة تلقائياً والمتابعات القانونية الجاري بها العمل.</p>
        <p>وتقبلوا خالص التقدير والاحترام، والسلام.</p>
        <p>حرر بـ {{ $communeAr }} في : {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
        <p class="muted" style="font-size:8pt">المرجع القانوني : المواد 64-73 من القانون 47.06 المتعلق بجبايات الجماعات الترابية</p>
    </div>
</body></html>
