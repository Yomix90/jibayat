@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => "Contrats d'affermage des souks", 'titleAr' => 'عقود كراء الأسواق الجماعية'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $rows->count() }} contrat(s)</p>
    <table class="data">
        <thead>
            <tr><th width="5%">N°</th><th width="16%">Souk / emplacement<br>السوق</th><th width="20%">Fermier<br>المستغل</th><th width="9%">CIN</th>
                <th width="14%">Contrat<br>العقد</th><th width="10%">Redevance / mois</th><th width="13%">Mois dus<br>الأشهر المستحقة</th><th width="13%">Reste dû (DH)<br>الباقي</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($c = $r['contract'])
                <tr>
                    <td class="center"><strong>{{ $c->number }}</strong></td>
                    <td>{{ $c->souk_name }}{{ $c->pitch_number ? ' · '.$c->pitch_number : '' }}</td>
                    <td>{{ $c->taxpayer?->displayName() }}@if ($c->taxpayer?->displayNameAr())<br><span dir="rtl">{{ $c->taxpayer->displayNameAr() }}</span>@endif</td>
                    <td class="center">{{ $c->taxpayer?->cin }}</td>
                    <td class="center">{{ $c->starts_on?->format('d/m/Y') }}<br>→ {{ $c->ends_on?->format('d/m/Y') ?? '…' }}</td>
                    <td class="num">{{ Format::money($r['current_fee']) }}</td>
                    <td>@if ($r['nb_mois_impaye'] === 0)<span class="green">À jour</span>@else{{ $r['nb_non_declares'] }} à encaisser, {{ $r['nb_emises'] }} émis @endif</td>
                    <td class="num">@if ($r['total_impaye'] > 0)<span class="red">{{ Format::money($r['total_impaye']) }}</span>@else — @endif</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7">Total</td><td class="num red">{{ Format::money($rows->sum('total_impaye')) }}</td></tr></tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
