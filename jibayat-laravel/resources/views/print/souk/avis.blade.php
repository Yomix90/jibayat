@php use App\Support\Format; $t = $contract->taxpayer; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.7; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 13pt; font-weight: bold; color: #B91C1C; }
</style></head>
<body>
    @include('print.partials.header', ['title' => 'Avis de non-paiement — Souk', 'titleAr' => 'إعذار أداء رسوم إيجار الأسواق الجماعية'])
    <div class="letter">
        <table width="100%"><tr>
            <td class="ar">عـدد : <span dir="ltr">{{ $number }}</span></td>
            <td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td>
            <td class="ar">المرفق / السوق : {{ $contract->souk_name }}</td>
        </tr></table>

        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            {{ ($t?->company_name || $t?->person_type === 'morale') ? 'الشركة' : 'السيد(ة)' }} : {{ $t?->company_name_ar ?? $t?->displayNameAr() ?? $t?->displayName() }}<br>
            السوق / المرفق : {{ $contract->souk_name }} — الموقع / الدكان : {{ $contract->pitch_number ?: '—' }}
        </div>

        <p><strong>الموضوع : إنذار بعدم أداء مستحقات استغلال مرفق تجاري بسوق جماعي</strong></p>
        <p class="center">ـ سلام تام بوجود مولانا الإمام المؤيد بالله ـ</p>
        <p>وبعـد،</p>
        <p>يُعلمكم رئيس المجلس الجماعي لـ {{ $communeAr }}، بأن سجلات إدارة المداخيل تُثبت استفادتكم من عقد ورخصة استغلال المحل التجاري / الموقع رقم
            {{ $contract->pitch_number ?: '—' }} بسوق « {{ $contract->souk_name }} ».</p>
        <p>وحيث إنه تبيّن عدم قيامكم بأداء واجبات الاستغلال المستحقة عن الفترة « {{ $periodAr }} » (<span dir="ltr">{{ $period }}</span>)،
            فإنكم ملزمون بأداء واجبات الكراء والاستغلال المترتبة في ذمتكم، مفصلة كالآتي :</p>

        <table class="data" dir="rtl">
            <thead><tr><th>الفترة</th><th>عدد الأشهر</th><th>السومة الشهرية (درهم)</th><th>المبلغ (درهم)</th></tr></thead>
            <tbody>
                @foreach ($groups as $g)
                    <tr>
                        <td class="center"><span dir="ltr">{{ $g['mois_debut'] }}@if ($g['mois_fin'] !== $g['mois_debut']) → {{ $g['mois_fin'] }}@endif</span></td>
                        <td class="center">{{ $g['nb_mois'] }}</td>
                        <td class="center"><span dir="ltr">{{ Format::money($g['redevance_mensuelle']) }}</span></td>
                        <td class="center"><span dir="ltr">{{ Format::money($g['montant']) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>المجموع الإجمالي الواجب أداؤه فوراً : <span class="total"><span dir="ltr">{{ Format::money($total) }}</span> درهم</span></p>
        <p>وعليه، فإننا نُعذركم رسمياً وندعوكم لتسوية هذا المبلغ لدى صندوق القابض / وكيل المداخيل الجماعية، داخل أجل أقصاه 30 يوماً من تاريخ التوصل
            بهذا الإعذار، تفادياً لتطبيق الإجراءات الزجرية المعمول بها والتي قد تصل إلى فسخ العقد واسترجاع المحل للملك الجماعي مع المتابعة القضائية
            لاستخلاص الديون العمومية.</p>
        <p>وتقبلوا خالص التقدير والاحترام، والسلام.</p>
        <p>حرر بـ {{ $communeAr }} في : {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
        <p class="muted" style="font-size:8pt">المرجع : كناش التحملات وعقود كراء محلات ومرافق الأسواق الجماعية · ملف عـدد : {{ $contract->number }}</p>
    </div>
</body></html>
