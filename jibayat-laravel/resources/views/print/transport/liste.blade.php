@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Transport — État des véhicules', 'titleAr' => 'الوقوف والرسم على النقل العمومي للمسافرين'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $rows->count() }} véhicule(s)</p>
    <table class="data">
        <thead>
            <tr><th width="5%">N°</th><th width="12%">Immatriculation<br>رقم التسجيل</th><th width="13%">Type<br>النوع</th><th width="22%">Propriétaire<br>المالك</th>
                <th width="9%">CIN</th><th width="11%">Par trimestre</th><th width="15%">Trimestres dus<br>الفصول المستحقة</th><th width="13%">Reste dû (DH)<br>الباقي</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($v = $r['vehicle'])
                <tr>
                    <td class="center"><strong>{{ $v->number }}</strong></td>
                    <td class="center">{{ $v->registration }}</td>
                    <td>{{ $v->type }}</td>
                    <td>{{ $v->taxpayer?->displayName() }}@if ($v->taxpayer?->displayNameAr())<br><span dir="rtl">{{ $v->taxpayer->displayNameAr() }}</span>@endif</td>
                    <td class="center">{{ $v->taxpayer?->cin }}</td>
                    <td class="num">{{ Format::money($r['per_quarter']) }}</td>
                    <td>@if ($r['nb_non_paye'] === 0)<span class="green">À jour</span>@else{{ $r['nb_non_declares'] }} à payer, {{ $r['nb_emises'] }} émis<br><span class="muted">{{ implode(', ', $r['annees_non_payees']) }}</span>@endif</td>
                    <td class="num">@if ($r['montant_impaye'] > 0)<span class="red">{{ Format::money($r['montant_impaye']) }}</span>@else — @endif</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7">Total</td><td class="num red">{{ Format::money($rows->sum('montant_impaye')) }}</td></tr></tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
