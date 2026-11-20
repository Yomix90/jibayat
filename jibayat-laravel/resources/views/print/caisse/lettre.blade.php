@php
    use App\Support\Format;
    use App\Domain\Documents\Services\RegisterService;
    $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة';
    $subject = ['relance' => 'رسالة تذكير بأداء الواجبات الجماعية', 'mise_en_demeure' => 'إنذار بأداء الواجبات الجماعية', 'notification' => 'إشعار بالواجبات الجماعية غير المؤداة'][$letter->type] ?? 'رسالة تذكير';
@endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { direction: rtl; text-align: right; font-size: 10.5pt; line-height: 1.65; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 12pt; font-weight: bold; color: #B91C1C; }
    .draft { color: #B91C1C; font-weight: bold; text-align: center; font-size: 9pt; }
</style></head>
<body>
@foreach ($byTaxpayer as $lines)
    @php($c = $lines->first())
    @if (! $loop->first)<pagebreak />@endif
    @include('print.partials.header', ['title' => 'Lettre de relance', 'titleAr' => $subject])
    @if ($letter->status === 'brouillon')<p class="draft">BROUILLON — مسودة</p>@endif
    @if (in_array($letter->status, ['annulee', 'annule'], true))<p class="draft">ANNULÉE — ملغاة</p>@endif
    <div class="letter">
        <table width="100%"><tr><td class="ar">عـدد : {{ $letter->number }}</td><td class="ar">التاريخ : {{ $letter->generated_on?->format('d/m/Y') }}</td><td class="ar">رقم الملزم : {{ $c->taxpayer_number }}</td></tr></table>
        <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
        <p>إلـــــى</p>
        <div class="box">
            السيد(ة) / الشركة : {{ trim(($c->last_name_ar ?? '').' '.($c->first_name_ar ?? '')) ?: ($c->company_name_ar ?: (trim($c->last_name.' '.$c->first_name) ?: $c->company_name)) }}
            @if ($c->cin)<br>رقم البطاقة الوطنية : {{ $c->cin }}@endif
            @if ($c->address_ar || $c->address)<br>العنوان : {{ $c->address_ar ?: $c->address }}@endif
        </div>
        <p><strong>الموضوع : {{ $subject }}</strong></p>
        <p>وبعـد، نذكركم بأن المبالغ التالية ما تزال غير مؤداة إلى حدود تاريخه رغم توصلكم بإشعار بعدم الأداء :</p>
        <table class="data" dir="rtl">
            <thead><tr><th>الرسم / Taxe</th><th>السنة</th><th>رقم الإقرار</th><th>رقم الإشعار</th><th>المبلغ (درهم)</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr><td>{{ RegisterService::MODULES[$l->module]['ar'] ?? $l->module }}</td><td class="center">{{ $l->year }}{{ $l->quarter ? ' / ف'.$l->quarter : '' }}</td>
                        <td class="center">{{ $l->decl_number }}</td><td class="center">{{ $l->notice_number }}</td><td class="center"><span dir="ltr">{{ Format::money($l->amount_due) }}</span></td></tr>
                @endforeach
            </tbody>
        </table>
        <p>المجموع : <span class="total"><span dir="ltr">{{ Format::money($lines->sum('amount_due')) }}</span> درهم</span></p>
        <p>لذا ندعوكم إلى تسوية وضعيتكم لدى وكالة المداخيل الجماعية في أقرب الآجال، تفادياً لسلوك مسطرة التحصيل الجبري طبقاً للقانون رقم 15.97 والقانون 47.06.</p>
        <p>وتقبلوا خالص التحيات، والسلام.</p>
        <p style="text-align:left"><strong>رئيس المجلس الجماعي</strong><br>{{ $commune?->president_ar }}</p>
    </div>
@endforeach
</body></html>
