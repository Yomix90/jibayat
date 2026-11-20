@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'État du stock des tickets et vignettes', 'titleAr' => 'بيان مخزون التذاكر والطوابع', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Au <strong>{{ today()->format('d/m/Y') }}</strong></td><td class="num">Régisseur : <strong>{{ $regisseur ?: '—' }}</strong></td></tr></table>
    <table class="data">
        <thead><tr><th>Valeur</th><th width="9%">P.U.</th><th width="12%">Réserve TGR</th><th width="11%">Régie</th><th width="12%">Chez les agents</th><th width="11%">Consommé</th><th width="14%">Valeur en régie (DH)</th></tr></thead>
        <tbody>@foreach ($state as $v)<tr><td>{{ $v['label'] }}</td><td class="num">{{ Format::money($v['unit_value']) }}</td><td class="num">{{ number_format($v['tgr'], 0, ',', ' ') }}</td><td class="num">{{ number_format($v['regie'], 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($v['with_agents'], 0, ',', ' ') }}</td><td class="num">{{ number_format($v['consumed'], 0, ',', ' ') }}</td><td class="num">{{ Format::money($v['regie'] * $v['unit_value']) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="6">Valeur totale du stock de la régie</td><td class="num">{{ Format::money(collect($state)->sum(fn ($v) => $v['regie'] * $v['unit_value'])) }}</td></tr></tfoot>
    </table>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Visa<br><span dir="rtl">تأشيرة</span></td></tr></table>
</body></html>
