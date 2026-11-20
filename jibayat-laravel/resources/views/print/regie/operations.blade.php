@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Opérations avec le Trésor (Modèle 7)', 'titleAr' => 'العمليات مع الخزينة (النموذج 7)', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Exercice : <strong>{{ $year }}</strong></td><td>Comptable : <strong>{{ $comptable }}</strong></td><td class="num">Régisseur : <strong>{{ $regisseur ?: '—' }}</strong></td></tr></table>
    <table class="data">
        <thead><tr><th width="11%">Mois</th><th width="12%">Solde au début</th><th width="12%">Recettes</th><th width="12%">Versements</th><th width="12%">Accusés</th><th width="12%">Solde à la fin</th><th>Bordereaux</th></tr></thead>
        <tbody>
            @foreach ($ops['months'] as $m)
                <tr><td>{{ Ledger::MONTHS[$m['month']] }}</td><td class="num">{{ Format::money($m['opening']) }}</td><td class="num">{{ Format::money($m['receipts']) }}</td><td class="num">{{ Format::money($m['remittances']) }}</td>
                    <td class="num">{{ Format::money($m['acknowledged']) }}</td><td class="num">{{ Format::money($m['closing']) }}</td><td>{{ collect($m['items'])->map(fn ($i) => $i->number.' ('.date('d/m', strtotime($i->date)).')')->implode(', ') }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="num">{{ Format::money($ops['total_receipts']) }}</td><td class="num">{{ Format::money($ops['total_remittances']) }}</td><td class="num">{{ Format::money($ops['total_acknowledged']) }}</td><td colspan="2"></td></tr></tfoot>
    </table>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le comptable<br><span dir="rtl">المحاسب</span></td></tr></table>
</body></html>
