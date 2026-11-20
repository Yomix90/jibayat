@php use App\Support\Format; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.65; }
    .ltr { direction: ltr; unicode-bidi: embed; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 13pt; font-weight: bold; color: #B91C1C; }
</style></head>
<body>
@foreach ($notices as $n)
    @php($p = $n['parcel'])
    @php($t = $p->taxpayer)
    @if (! $loop->first)<pagebreak />@endif
    @include('print.partials.header', ['title' => 'Avis de non-paiement — TNB', 'titleAr' => 'إنذار بأداء الرسم على الأراضي الحضرية غير المبنية'])

    <div class="letter">
        <table width="100%"><tr>
            <td class="ar">عـدد : <span dir="ltr">{{ $n['number'] }}</span></td>
            <td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td>
            <td class="ar">ملف عـدد : {{ $n['file'] ?? $p->number }}</td>
        </tr></table>

        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            @if ($t?->company_name || $t?->person_type === 'morale')
                الشركة : {{ $t?->company_name_ar ?? $t?->company_name }}
                @if ($t?->rc) — السجل التجاري رقم : {{ $t->rc }}@endif
            @else
                السيد(ة) : {{ $t?->displayNameAr() ?? $t?->displayName() }}
                @if ($t?->cin)<br>الحامل(ة) لبطاقة التعريف الوطنية رقم : {{ $t->cin }}@endif
            @endif
            @if ($t?->address_ar || $t?->address)<br>العنوان : {{ $t->address_ar ?? $t->address }}@endif
        </div>

        <p><strong>الموضوع : إنذار بشأن أداء الرسم المفروض على الأراضي الحضرية غير المبنية</strong></p>
        <p class="center">ـ سلام تام بوجود مولانا الإمام المؤيد بالله ـ</p>
        <p>وبعـد،</p>
        <p>صلة بالموضوع المشار إليه أعلاه، وبناءً على عمليات الإحصاء والمراقبة الميدانية وسجلات الوعاء الجبائي، يُشرفني إخباركم بأن سجلات الجماعة
            تُبيّن ملكيتكم أو حيازتكم للقطعة الأرضية الحضرية غير المبنية الحاملة للرقم {{ $p->number }}
            @if ($p->land_title)(مطلب/رسم عقاري : <span dir="ltr">{{ $p->land_title }}</span>)@endif
            والكائنة بـ {{ $p->address_ar ?: 'نفوذ الجماعة' }} والبالغة مساحتها الخاضعة <span dir="ltr">{{ number_format((float) $p->area, 0, ',', ' ') }}</span> م².</p>
        <p>وعليه، فإنكم ملزمون قانوناً بأداء الرسم على الأراضي الحضرية غير المبنية طبقاً لمقتضيات المواد 39 إلى 46 من القانون رقم 47.06
            المتعلق بجبايات الجماعات الترابية، وذلك عن الفترة « {{ $n['periodAr'] }} » :</p>

        <table class="data" dir="rtl">
            <thead><tr><th>السنة</th><th>الأصل (درهم)</th><th>الذعائر والغرامات (درهم)</th><th>المجموع (درهم)</th></tr></thead>
            <tbody>
                @foreach ($n['years'] as $y)
                    <tr>
                        <td class="center"><strong>{{ $y['annee'] }}</strong></td>
                        <td class="center"><span dir="ltr">{{ Format::money($y['principal']) }}</span></td>
                        <td class="center"><span dir="ltr">{{ Format::money($y['penalite'] + $y['majoration'] + $y['amende']) }}</span></td>
                        <td class="center"><strong><span dir="ltr">{{ Format::money($y['total']) }}</span></strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>المجموع الإجمالي الواجب أداؤه فوراً شاملاً للأصل والذعائر والغرامات والزيادات القانونية المستحقة :
            <span class="total"><span dir="ltr">{{ Format::money($n['total']) }}</span> درهم</span></p>
        <p>لـذا، فإننا نُعذركم رسمياً وندعوكم للتقدم فوراً إلى مصلحة الجبايات والمداخيل قصد أداء المبلغ المستحق، وذلك داخل أجل أقصاه 30 يوماً
            من تاريخ التوصل بهذا الإشعار، تجنباً لتطبيق إجراءات التحصيل الجبري والمتابعات المنصوص عليها قانوناً.</p>
        <p>وتقبلوا خالص التقدير والاحترام، والسلام.</p>
        <p>حرر بـ {{ $communeAr }} في : {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
        <p class="muted" style="font-size:8pt">المرجع القانوني : المواد 39-46 من القانون 47.06 المتعلق بجبايات الجماعات الترابية</p>
    </div>
@endforeach
</body></html>
