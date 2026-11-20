@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'État mensuel des recettes (Modèle 10)', 'titleAr' => 'البيان الشهري للمداخيل (النموذج 10)', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Mois : <strong>{{ Ledger::MONTHS[$month] }} {{ $year }}</strong></td><td>Régisseur : <strong>{{ $regisseur ?: '—' }}</strong></td>
        <td class="num">{{ $state['closed'] ? 'Mois clôturé' : 'État provisoire (mois non clôturé)' }}</td></tr></table>
    <table class="data">
        <thead><tr><th width="13%">Code</th><th>Rubrique</th><th width="8%">Pièces</th><th width="15%">Report</th><th width="15%">Mois</th><th width="15%">Cumul</th></tr></thead>
        <tbody>
            @foreach ($state['rows'] as $r)
                <tr><td>{{ $r['code'] }}</td><td>{{ $r['label'] }}@if ($r['label_ar'] ?? null)<br><span dir="rtl">{{ $r['label_ar'] }}</span>@endif</td><td class="center">{{ $r['pieces'] }}</td>
                    <td class="num">{{ Format::money($r['previous']) }}</td><td class="num">{{ Format::money($r['month']) }}</td><td class="num">{{ Format::money($r['cumulative']) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">Total</td><td class="num">{{ Format::money($state['total_previous']) }}</td><td class="num">{{ Format::money($state['total_month']) }}</td><td class="num">{{ Format::money($state['total_cumulative']) }}</td></tr></tfoot>
    </table>
    <p style="margin-top:8px">Arrêté le présent état à la somme de : <strong>{{ $words }}</strong> pour le mois.</p>
    @if ($state['ministry_excluded'] > 0)<p class="muted">Virements du Ministère du mois, hors caisse et non repris : {{ Format::money($state['ministry_excluded']) }} DH.</p>@endif
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le comptable<br><span dir="rtl">المحاسب</span></td><td>L’ordonnateur<br><span dir="rtl">الآمر بالصرف</span></td></tr></table>
</body></html>
