@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Tableau des recettes '.$t['year'], 'titleAr' => 'جدول المداخيل '.$t['year'], 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table class="data">
        <thead><tr><th width="8%">Code</th><th>Rubrique</th>@foreach (Ledger::MONTHS as $m)<th width="5.6%">{{ mb_substr($m, 0, 4) }}</th>@endforeach<th width="8%">Total</th></tr></thead>
        <tbody>@foreach ($t['rows'] as $r)<tr><td>{{ $r['code'] }}</td><td>{{ $r['label'] }}</td>@foreach ($r['months'] as $v)<td class="num">{{ $v ? Format::money($v, 0) : '' }}</td>@endforeach<td class="num">{{ Format::money($r['total']) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="2">Total</td>@foreach ($t['totals'] as $v)<td class="num">{{ Format::money($v, 0) }}</td>@endforeach<td class="num">{{ Format::money($t['total']) }}</td></tr></tfoot>
    </table>
</body></html>
