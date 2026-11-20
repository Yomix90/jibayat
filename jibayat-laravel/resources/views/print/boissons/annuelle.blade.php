@php use App\Support\Format; use App\Domain\Boissons\Services\BeverageCalculator; $t = $outlet->taxpayer; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style> .ident td { padding: 3px 6px; font-size: 10pt; } </style></head>
<body>
    @include('print.partials.header', ['title' => 'Déclaration annuelle '.$year, 'titleAr' => 'إقرار سنوي بالمداخيل — '.$year])
    <table width="100%" class="meta"><tr>
        <td>N° : <strong>{{ $number }}</strong></td><td class="center">Trimestres réglés : {{ $paid }} / 4</td><td class="ar" dir="rtl">السنة المالية : {{ $year }}</td>
    </tr></table>

    <table width="100%" class="ident" dir="rtl">
        <tr><td class="ar">الاسم العائلي والشخصي للمستغل :</td><td class="ar"><strong>{{ $t?->displayNameAr() ?? $t?->displayName() }}</strong></td>
            <td class="ar">رقم بطاقة التعريف (CIN/RC) :</td><td>{{ $t?->cin ?? $t?->rc ?? '—' }}</td></tr>
        <tr><td class="ar">الاسم التجاري للمحل :</td><td><strong>{{ $outlet->name }}</strong></td>
            <td class="ar">رقم الرخصة :</td><td>{{ $outlet->authorization_number ?: '—' }}</td></tr>
        <tr><td class="ar">عنوان المحل :</td><td>{{ $outlet->address ?: '—' }}</td>
            <td class="ar">رقم الهاتف :</td><td>{{ $t?->phone ?: '—' }}</td></tr>
    </table>

    <table class="data" dir="rtl">
        <thead><tr><th>الربع السنوي<br>Trimestre</th><th>المداخيل المصرح بها<br>CA déclaré</th><th>مبلغ الرسم<br>Taxe</th><th>الذعائر والغرامات<br>Pénalités</th><th>المجموع (درهم)<br>Total</th></tr></thead>
        <tbody>
            @foreach ($rows as $q => $r)
                @php($d = $r['declaration'])
                <tr>
                    <td class="center">{{ BeverageCalculator::QUARTERS_AR[$q] }} — T{{ $q }}</td>
                    @if ($d)
                        <td class="num"><span dir="ltr">{{ Format::money($d->base_amount) }}</span></td>
                        <td class="num"><span dir="ltr">{{ Format::money($d->principal) }}</span></td>
                        <td class="num"><span dir="ltr">{{ Format::money($r['pen']) }}</span></td>
                        <td class="num"><strong><span dir="ltr">{{ Format::money($d->total) }}</span></strong></td>
                    @else
                        <td class="center">—</td><td class="center">—</td><td class="center">—</td><td class="center red">لم يصرح به بعد</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr>
            <td>المجموع السنوي</td><td class="num"><span dir="ltr">{{ Format::money($totals['base']) }}</span></td><td class="num"><span dir="ltr">{{ Format::money($totals['principal']) }}</span></td>
            <td class="num"><span dir="ltr">{{ Format::money($totals['pen']) }}</span></td><td class="num"><span dir="ltr">{{ Format::money($totals['total']) }}</span> DH</td>
        </tr></tfoot>
    </table>
    <p class="ar muted" dir="rtl" style="font-size:8.5pt">ملاحظة قانونية : يُعتبر هذا الإقرار بمثابة حصر للحسابات السنوية للمحل الجبائي المعني برسم بيع المشروبات، وتُطبق مقتضيات المراقبة البعدية واسترداد الرسوم وفق القانون 47.06.</p>

    <table width="100%" class="signatures">
        <tr><td>Le déclarant<br><span dir="rtl">توقيع المصرح</span></td><td>Service fiscal<br><span dir="rtl">تأشيرة مصلحة الجبايات</span></td></tr>
    </table>
</body></html>
