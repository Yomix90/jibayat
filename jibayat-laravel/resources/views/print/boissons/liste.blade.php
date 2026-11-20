@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Débits de boissons — État des établissements', 'titleAr' => 'الرسم على محال بيع المشروبات'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $rows->count() }} établissement(s) · montants estimés sur le dernier CA déclaré</p>

    <table class="data">
        <thead>
            <tr>
                <th width="5%">N°</th><th width="18%">Établissement<br>المحل</th><th width="20%">Exploitant<br>المستغل</th>
                <th width="9%">CIN / RC</th><th width="6%">Taux</th><th width="10%">Dernier CA</th>
                <th width="16%">Trimestres dus<br>الفصول المستحقة</th><th width="16%">Estimation (DH)<br>تقدير</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($o = $r['outlet'])
                <tr>
                    <td class="center"><strong>{{ $o->number }}</strong></td>
                    <td>{{ $o->name }}@if ($o->status === 'cesse')<br><span class="muted">cessation</span>@endif</td>
                    <td>{{ $o->taxpayer?->displayName() }}@if ($o->taxpayer?->displayNameAr())<br><span dir="rtl">{{ $o->taxpayer->displayNameAr() }}</span>@endif</td>
                    <td class="center">{{ $o->taxpayer?->cin ?? $o->taxpayer?->rc }}</td>
                    <td class="center">{{ rtrim(rtrim(number_format($r['rate'], 2, ',', ''), '0'), ',') }} %</td>
                    <td class="num">{{ Format::money($r['last_base']) }}</td>
                    <td>@if ($r['nb_non_paye'] === 0)<span class="green">À jour</span>@else{{ $r['nb_non_declares'] }} à déclarer, {{ $r['nb_emises'] }} émis<br><span class="muted">{{ implode(', ', $r['annees_non_payees']) }}</span>@endif</td>
                    <td class="num">@if ($r['total_impaye'] > 0)<span class="red">{{ Format::money($r['total_impaye']) }}</span>@else — @endif</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7">Total estimé</td><td class="num red">{{ Format::money($rows->sum('total_impaye')) }}</td></tr></tfoot>
    </table>

    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
