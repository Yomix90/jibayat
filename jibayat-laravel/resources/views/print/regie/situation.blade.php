@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; $modes = array_keys($s['modes']); @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Situation journalière de la caisse', 'titleAr' => 'الوضعية اليومية للصندوق', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Journée du <strong>{{ date('d/m/Y', strtotime($s['date'])) }}</strong></td><td>Solde à l’ouverture : <strong>{{ Format::money($s['opening']) }} DH</strong></td><td class="num">Solde à la fermeture : <strong>{{ Format::money($s['closing']) }} DH</strong></td></tr></table>
    <table class="data">
        <thead><tr><th width="13%">Code</th><th>Rubrique</th><th width="7%">Pièces</th>@foreach ($modes as $m)<th width="12%">{{ Ledger::modeLabel($m) }}</th>@endforeach<th width="13%">Total</th></tr></thead>
        <tbody>@foreach ($s['rubrics'] as $r)<tr><td>{{ $r['code'] }}</td><td>{{ $r['label'] }}</td><td class="center">{{ $r['pieces'] }}</td>@foreach ($modes as $m)<td class="num">{{ isset($r['modes'][$m]) ? Format::money($r['modes'][$m]) : '' }}</td>@endforeach<td class="num">{{ Format::money($r['total']) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="2">Total des recettes</td><td class="center">{{ $s['pieces'] }}</td>@foreach ($modes as $m)<td class="num">{{ Format::money($s['modes'][$m]) }}</td>@endforeach<td class="num">{{ Format::money($s['receipts']) }}</td></tr></tfoot>
    </table>
    @if ($s['remittances'])
        <table class="data"><thead><tr><th>Versement au comptable</th><th width="20%">Montant</th></tr></thead>
            <tbody>@foreach ($s['remittances'] as $r)<tr><td>{{ $r->number }}</td><td class="num">{{ Format::money($r->amount) }}</td></tr>@endforeach</tbody></table>
    @endif
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td></tr></table>
</body></html>
