@php use App\Support\Format; use App\Domain\Documents\Services\RegisterService; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Grand livre des recettes', 'titleAr' => 'السجل العام للمداخيل'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $year ? 'Année '.$year : 'Toutes les années' }} · {{ $module ? (RegisterService::MODULES[$module]['fr'] ?? $module) : 'Toutes les taxes' }} · {{ $totals['n'] }} ligne(s)</p>
    <table class="data">
        <thead>
            <tr><th width="11%">Déclaration</th><th width="14%">Taxe</th><th width="6%">Période</th><th width="18%">Redevable<br>الملزم</th><th width="8%">CIN</th>
                <th width="10%">Émis (DH)</th><th width="9%">N° BV</th><th width="12%">Quittance<br>الوصل</th><th width="10%">Recouvré (DH)</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($paid = $r->slip_status === 'paye' || $r->status === 'paye')
                <tr>
                    <td>{{ $r->number }}</td>
                    <td>{{ RegisterService::MODULES[$r->module]['fr'] ?? $r->module }}</td>
                    <td class="center">{{ $r->year }}{{ $r->quarter ? ' T'.$r->quarter : '' }}</td>
                    <td>{{ RegisterService::taxpayerName($r) }}</td>
                    <td class="center">{{ $r->cin }}</td>
                    <td class="num">{{ Format::money($r->total) }}</td>
                    <td class="center">{{ $r->slip_number }}</td>
                    <td class="center">{{ $r->receipt_number }}@if ($r->receipt_date)<br>{{ \Illuminate\Support\Carbon::parse($r->receipt_date)->format('d/m/Y') }}@endif</td>
                    <td class="num">{{ $paid ? Format::money($r->amount ?? 0) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="5">Totaux · taux de recouvrement {{ number_format($totals['rate'], 1, ',', ' ') }} %</td><td class="num">{{ Format::money($totals['emitted']) }}</td><td colspan="2">Reste : {{ Format::money($totals['remaining']) }}</td><td class="num green">{{ Format::money($totals['paid']) }}</td></tr>
        </tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
