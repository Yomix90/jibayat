@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; $modes = $r['modes']; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Récapitulatif comptable', 'titleAr' => 'الملخص المحاسبي', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Période du <strong>{{ date('d/m/Y', strtotime($r['from'])) }}</strong> au <strong>{{ date('d/m/Y', strtotime($r['to'])) }}</strong></td><td class="num">{{ $r['pieces'] }} pièce(s) · {{ Format::money($r['total']) }} DH</td></tr></table>
    <table class="data">
        <thead><tr><th width="11%">Code</th><th>Rubrique</th><th width="6%">Pièces</th>@foreach ($modes as $m)<th width="11%">{{ Ledger::modeLabel($m) }}</th>@endforeach<th width="12%">Total</th></tr></thead>
        <tbody>
            @foreach ($r['families'] as $f)
                <tr><td colspan="{{ count($modes) + 4 }}"><strong>{{ $f['label'] }}</strong></td></tr>
                @foreach ($f['rubrics'] as $row)<tr><td>{{ $row['code'] }}</td><td>{{ $row['label'] }}</td><td class="center">{{ $row['pieces'] }}</td>@foreach ($modes as $m)<td class="num">{{ isset($row['modes'][$m]) ? Format::money($row['modes'][$m]) : '' }}</td>@endforeach<td class="num">{{ Format::money($row['total']) }}</td></tr>@endforeach
                <tr><td colspan="3" class="num"><em>Sous-total {{ $f['label'] }}</em></td>@foreach ($modes as $m)<td class="num"><em>{{ isset($f['modes'][$m]) ? Format::money($f['modes'][$m]) : '' }}</em></td>@endforeach<td class="num"><strong>{{ Format::money($f['total']) }}</strong></td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">Total général</td>@foreach ($modes as $m)<td class="num">{{ Format::money($r['totals'][$m] ?? 0) }}</td>@endforeach<td class="num">{{ Format::money($r['total']) }}</td></tr></tfoot>
    </table>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td></tr></table>
</body></html>
