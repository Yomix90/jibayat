@php use App\Support\Format; $t = $occupation->taxpayer; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.7; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 13pt; font-weight: bold; color: #B91C1C; }
</style></head>
<body>
    @include('print.partials.header', ['title' => 'Avis de non-paiement — Domaine public', 'titleAr' => 'إعذار أداء إتاوة احتلال الملك العام'])
    <div class="letter">
        <table width="100%"><tr>
            <td class="ar">عـدد : <span dir="ltr">{{ $number }}</span></td>
            <td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td>
            <td class="ar">ملف عـدد : {{ $occupation->number }}</td>
        </tr></table>

        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            {{ ($t?->company_name || $t?->person_type === 'morale') ? 'الشركة' : 'السيد(ة)' }} : {{ $t?->company_name_ar ?? $t?->displayNameAr() ?? $t?->displayName() }}<br>
            نوع الاحتلال : {{ $occupation->type }} — المساحة : <span dir="ltr">{{ Format::money($occupation->area) }}</span> م² — الموقع : {{ $occupation->location }}
        </div>

        <p><strong>الموضوع : إنذار بشأن أداء إتاوة احتلال الملك العام الجماعي المؤقت</strong></p>
        <p class="center">ـ سلام تام بوجود مولانا الإمام المؤيد بالله ـ</p>
        <p>وبعـد،</p>
        <p>يؤسفني إخباركم بأنه تبيّن من خلال مراجعة سجلات مصلحة الجبايات والمداخيل الجماعية، أنكم لم تقوموا بتسوية وضعيتكم الجبائية وأداء الإتاوات
            المستحقة عن احتلال الملك العام الجماعي المتعلق بنشاط « {{ $occupation->type }} » عن الفترة « {{ $periodAr }} » (<span dir="ltr">{{ $period }}</span>) :</p>

        <table class="data" dir="rtl">
            <thead><tr><th>الفصل</th><th>تاريخ الاستحقاق</th><th>المبلغ (درهم)</th></tr></thead>
            <tbody>
                @foreach ($quarters as $q)
                    <tr><td class="center"><span dir="ltr">T{{ $q['trimestre'] }} {{ $q['annee'] }}</span></td>
                        <td class="center">{{ \Illuminate\Support\Carbon::parse($q['echeance'])->format('d/m/Y') }}</td>
                        <td class="center"><span dir="ltr">{{ Format::money($q['montant']) }}</span></td></tr>
                @endforeach
            </tbody>
        </table>

        <p>المبلغ الإجمالي الواجب أداؤه فوراً : <span class="total"><span dir="ltr">{{ Format::money($total) }}</span> درهم</span></p>
        <p>وعليه، فإنكم مدعوون رسمياً إلى التقدم فوراً لدى صندوق وكالة المداخيل الجماعية داخل أجل أقصاه 30 يوماً من تاريخ التوصل بهذا الإعذار قصد تسوية
            ما بذمتكم، تجنباً لاتخاذ الإجراءات الإدارية والقانونية الزجرية، بما في ذلك سحب رخصة الاحتلال وتطبيق مقتضيات المتابعة والتحصيل الجبري.</p>
        <p>وتقبلوا فائق التقدير والاحترام، والسلام.</p>
        <p>حرر بـ {{ $communeAr }} في : {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
        <p class="muted" style="font-size:8pt">المرجع القانوني : القرار الجبائي الجماعي المنظم لشغل الأملاك الجماعية العامة مؤقتاً</p>
    </div>
</body></html>
