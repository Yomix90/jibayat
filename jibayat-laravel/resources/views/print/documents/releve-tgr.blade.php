@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Activité mensuelle — relevé TGR', 'titleAr' => 'كشف العمليات الشهرية — الخزينة العامة'])
    <table width="100%" class="meta"><tr>
        <td>Commune : <strong>{{ $meta['commune'] ?: '—' }}</strong></td>
        <td>Régie : <strong>{{ $meta['regie'] ?: '—' }}</strong></td>
        <td>Période : <strong>@if ($meta['periode_debut'])du {{ date('d/m/Y', strtotime($meta['periode_debut'])) }} au {{ date('d/m/Y', strtotime($meta['periode_fin'])) }}@else — @endif</strong></td>
        <td class="num">{{ count($operations) }} opération(s)@if ($filters) · filtré @endif</td>
    </tr></table>
    <table class="data">
        <thead><tr><th width="3%">N°</th><th width="8%">Réf. paiement</th><th width="7%">Date</th><th width="17%">Partie versante</th><th width="7%">Mode</th>
            <th width="23%">Rubrique</th><th width="7%">Principal</th><th width="7%">Majoration</th><th width="7%">Pénalités</th><th width="7%">Astreintes</th><th width="8%">Total</th></tr></thead>
        <tbody>
            @foreach ($operations as $op)
                <tr><td class="center">{{ $op['num'] }}</td><td>{{ $op['ref_paiement'] }}</td><td class="center">{{ $op['date'] ? date('d/m/Y', strtotime($op['date'])) : '' }}</td>
                    <td>{{ $op['partie_versante'] }}</td><td>{{ $op['mode_paiement'] }}</td><td>{{ $op['code_rubrique'] }} — {{ $op['libelle_rubrique'] }}</td>
                    <td class="num">{{ Format::money($op['principal']) }}</td><td class="num">{{ Format::money($op['majoration']) }}</td>
                    <td class="num">{{ Format::money($op['penalites']) }}</td><td class="num">{{ Format::money($op['astreintes']) }}</td><td class="num">{{ Format::money($op['total']) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="6">Total</td>
            @foreach (['principal', 'majoration', 'penalites', 'astreintes', 'total'] as $k)<td class="num">{{ Format::money(array_sum(array_column($operations, $k))) }}</td>@endforeach</tr></tfoot>
    </table>
    <p style="margin-top:8px">Arrêté le présent relevé à la somme de : <strong>{{ $words }}</strong>.</p>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le percepteur (TGR)<br><span dir="rtl">القابض</span></td></tr></table>
</body></html>
