@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; use App\Domain\Regie\Services\MinutesService; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Rapport annuel de la régie — '.$r['year'], 'titleAr' => 'التقرير السنوي للوكالة — '.$r['year'], 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Recettes : <strong>{{ Format::money($r['total']) }} DH</strong> ({{ $r['year'] - 1 }} : {{ Format::money($r['previous_total']) }})</td>
        <td>Versé au comptable : <strong>{{ Format::money($r['remitted']) }} DH</strong></td><td class="num">Mois clôturés : {{ $r['closed'] }}/12 · solde {{ Format::money($r['closing']) }} DH</td></tr></table>
    <table class="data">
        <thead><tr><th width="11%">Code</th><th>Rubrique</th><th width="13%">{{ $r['year'] - 1 }}</th><th width="13%">{{ $r['year'] }}</th><th width="9%">Évolution</th><th width="13%">Prévision</th><th width="9%">Réalisation</th></tr></thead>
        <tbody>@foreach ($r['rows'] as $row)<tr><td>{{ $row['code'] }}</td><td>{{ $row['label'] }}</td><td class="num">{{ Format::money($row['previous']) }}</td><td class="num">{{ Format::money($row['total']) }}</td>
            <td class="num">{{ $row['evolution'] === null ? '—' : number_format($row['evolution'], 1, ',', ' ').' %' }}</td><td class="num">{{ $row['budget'] === null ? '—' : Format::money($row['budget']) }}</td>
            <td class="num">{{ $row['budget'] ? number_format($row['total'] / $row['budget'] * 100, 1, ',', ' ').' %' : '—' }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="num">{{ Format::money($r['previous_total']) }}</td><td class="num">{{ Format::money($r['total']) }}</td><td colspan="3"></td></tr></tfoot>
    </table>
    <table class="data">
        <thead><tr><th>Mois</th>@foreach (Ledger::MONTHS as $m)<th>{{ mb_substr($m, 0, 4) }}</th>@endforeach</tr></thead>
        <tbody><tr><td>Recettes</td>@foreach ($r['months'] as $v)<td class="num">{{ Format::money($v, 0) }}</td>@endforeach</tr></tbody>
    </table>
    <table class="data">
        <thead><tr><th>Activité</th><th width="15%">Nombre</th></tr></thead>
        <tbody>@foreach (MinutesService::TYPES as $k => $t)<tr><td>{{ $t['fr'] }}</td><td class="center">{{ $r['reports'][$k] ?? 0 }}</td></tr>@endforeach
            <tr><td>Pièces annulées</td><td class="center">{{ $r['cancelled'] }}</td></tr></tbody>
    </table>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>L’ordonnateur<br><span dir="rtl">الآمر بالصرف</span></td></tr></table>
</body></html>
