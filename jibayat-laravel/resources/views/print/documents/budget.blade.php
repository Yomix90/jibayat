@php use App\Support\Format; $fr = $lang === 'fr'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style> tr.dom td { background: #F2F3F5; font-weight: bold; color: #1E2A3A; } tr.sub td { font-weight: bold; background: #E6E8EC; } tr.grand td { font-weight: bold; background: #F6EBC8; font-size: 9pt; } </style>
</head>
<body @if (! $fr) dir="rtl" @endif>
    @include('print.partials.header', ['title' => "Projet de budget des recettes $year et réalisations", 'titleAr' => "مشروع ميزانية المداخيل لسنة $year والمداخيل المحققة"])
    <table class="data">
        <thead><tr>
            <th width="10%">{{ $fr ? 'Chapitre' : 'الفصول' }}</th><th width="38%">{{ $fr ? 'Nature des recettes' : 'نوع المداخيل' }}</th>
            <th width="11%">{{ $fr ? "Réalisé $annee_n1" : "المحققة $annee_n1" }}</th><th width="11%">{{ $fr ? "Proposé $year" : "المقترحة $year" }}</th>
            <th width="11%">{{ $fr ? "Réalisé $year" : "المحققة $year" }}</th><th width="7%">{{ $fr ? 'Taux' : 'النسبة' }}</th><th width="12%">{{ $fr ? 'Écart' : 'الفارق' }}</th>
        </tr></thead>
        <tbody>
            @foreach ($domaines as $d)
                <tr class="dom"><td colspan="7">{{ $fr ? $d['nom_fr'] : $d['nom_ar'] }}</td></tr>
                @foreach ($d['lignes'] as $l)
                    <tr><td class="center">{{ $l->chapter }}</td>
                        <td>{{ $fr ? ($l->label_fr ?: $l->label) : ($l->label_ar ?: $l->label) }}@if ($fr && $l->label_ar) <span class="muted" dir="rtl">· {{ $l->label_ar }}</span>@endif</td>
                        <td class="num">{{ Format::money($l->previous_actual) }}</td><td class="num">{{ Format::money($l->proposed) }}</td><td class="num">{{ Format::money($l->actual) }}</td>
                        <td class="num">{{ number_format($l->rate, 1, ',', ' ') }} %</td><td class="num {{ $l->gap < 0 ? 'red' : '' }}">{{ Format::money($l->gap) }}</td></tr>
                @endforeach
                <tr class="sub"><td colspan="2">{{ $fr ? 'Sous-total' : 'المجموع' }} — {{ $fr ? $d['nom_fr'] : $d['nom_ar'] }}</td>
                    <td class="num">{{ Format::money($d['total_n1']) }}</td><td class="num">{{ Format::money($d['total_propose']) }}</td><td class="num">{{ Format::money($d['total_realise']) }}</td>
                    <td class="num">{{ number_format($d['total_taux'], 1, ',', ' ') }} %</td><td class="num">{{ Format::money($d['total_ecart']) }}</td></tr>
            @endforeach
            <tr class="grand"><td colspan="2">{{ $fr ? 'TOTAL GÉNÉRAL — budget global communal' : 'المجموع العام — الميزانية الإجمالية للجماعة' }}</td>
                <td class="num">{{ Format::money($grand_total['total_n1']) }}</td><td class="num">{{ Format::money($grand_total['total_propose']) }}</td><td class="num">{{ Format::money($grand_total['total_realise']) }}</td>
                <td class="num">{{ number_format($grand_total['total_taux'], 1, ',', ' ') }} %</td><td class="num">{{ Format::money($grand_total['total_ecart']) }}</td></tr>
        </tbody>
    </table>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">شسيع المداخيل الجماعية</span></td><td>Le président du conseil<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr></table>
</body></html>
