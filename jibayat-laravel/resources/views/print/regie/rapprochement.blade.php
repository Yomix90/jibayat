@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'État de rapprochement régie / comptable', 'titleAr' => 'بيان المطابقة بين الوكالة والمحاسب', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Mois : <strong>{{ Ledger::MONTHS[$r['month']] }} {{ $r['year'] }}</strong></td><td>Comptable : <strong>{{ $comptable }}</strong></td><td class="num">{{ $r['statement'] ? 'Relevé TGR importé' : 'Aucun relevé TGR importé' }}</td></tr></table>
    <table class="data">
        <thead><tr><th width="14%">Code</th><th>Rubrique</th><th width="15%">Régie</th><th width="15%">Comptable (TGR)</th><th width="15%">Écart</th></tr></thead>
        <tbody>@foreach ($r['rows'] as $row)<tr><td>{{ $row['code'] }}</td><td>{{ $row['label'] }}</td><td class="num">{{ Format::money($row['regie']) }}</td><td class="num">{{ Format::money($row['tgr']) }}</td><td class="num {{ abs($row['gap']) > 0.009 ? 'red' : '' }}">{{ Format::money($row['gap']) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="num">{{ Format::money($r['total_regie']) }}</td><td class="num">{{ Format::money($r['total_tgr']) }}</td><td class="num">{{ Format::money($r['total_gap']) }}</td></tr></tfoot>
    </table>
    <p class="muted">Versements au comptable du mois : {{ Format::money($r['remitted']) }} DH.</p>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le comptable<br><span dir="rtl">المحاسب</span></td></tr></table>
</body></html>
