@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Rapport détaillé des recettes', 'titleAr' => 'تقرير مفصل عن المداخيل', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Du <strong>{{ date('d/m/Y', strtotime($from)) }}</strong> au <strong>{{ date('d/m/Y', strtotime($to)) }}</strong></td>
        <td>Solde au début : {{ Format::money($opening) }} · Versements : {{ Format::money($remittances->sum('amount')) }}</td><td class="num">Solde à la fin : <strong>{{ Format::money($closing) }} DH</strong></td></tr></table>
    <table class="data">
        <thead><tr><th>Famille</th>@foreach ($recap['modes'] as $m)<th width="13%">{{ Ledger::modeLabel($m) }}</th>@endforeach<th width="14%">Total</th></tr></thead>
        <tbody>@foreach ($recap['families'] as $f)<tr><td>{{ $f['label'] }}</td>@foreach ($recap['modes'] as $m)<td class="num">{{ isset($f['modes'][$m]) ? Format::money($f['modes'][$m]) : '' }}</td>@endforeach<td class="num">{{ Format::money($f['total']) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td>Total</td>@foreach ($recap['modes'] as $m)<td class="num">{{ Format::money($recap['totals'][$m] ?? 0) }}</td>@endforeach<td class="num">{{ Format::money($recap['total']) }}</td></tr></tfoot>
    </table>
    <table class="data">
        <thead><tr><th width="10%">Date</th><th width="16%">Pièce</th><th>Partie versante</th><th width="12%">Code</th><th width="12%">Mode</th><th width="13%">Montant</th></tr></thead>
        <tbody>@foreach ($receipts as $r)<tr><td class="center">{{ date('d/m/Y', strtotime($r->date)) }}</td><td>{{ $r->ref }}</td><td>{{ $r->payer }}</td><td>{{ $r->code }}</td><td>{{ Ledger::modeLabel($r->mode) }}</td><td class="num">{{ Format::money($r->amount) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="5">Total ({{ $receipts->count() }} pièce(s))</td><td class="num">{{ Format::money($receipts->sum('amount')) }}</td></tr></tfoot>
    </table>
</body></html>
