@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Livre de caisse (Modèle 6)', 'titleAr' => 'سجل الصندوق (النموذج 6)', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Mois : <strong>{{ Ledger::MONTHS[$month] }} {{ $year }}</strong></td><td>Régisseur : <strong>{{ $regisseur ?: '—' }}</strong></td><td class="num">Report : <strong>{{ Format::money($book['carried']) }} DH</strong></td></tr></table>
    <table class="data">
        <thead><tr><th width="11%">Date</th><th width="7%">Pièces</th><th width="14%">Espèces</th><th width="14%">Chèques, virements</th><th width="14%">Total recettes</th><th width="14%">Versements</th><th width="12%">Réf. versement</th><th width="14%">Solde</th></tr></thead>
        <tbody>
            <tr><td colspan="7"><strong>Report du mois précédent</strong></td><td class="num"><strong>{{ Format::money($book['carried']) }}</strong></td></tr>
            @foreach ($book['days'] as $d)
                <tr><td class="center">{{ date('d/m/Y', strtotime($d['date'])) }}</td><td class="center">{{ $d['pieces'] }}</td><td class="num">{{ Format::money($d['cash']) }}</td><td class="num">{{ Format::money($d['other']) }}</td>
                    <td class="num">{{ Format::money($d['receipts']) }}</td><td class="num">{{ $d['remittances'] ? Format::money($d['remittances']) : '' }}</td><td>{{ implode(', ', $d['remittance_refs']) }}</td><td class="num">{{ Format::money($d['balance']) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Totaux du mois</td><td class="num">{{ Format::money($book['total_cash']) }}</td><td class="num">{{ Format::money($book['total_other']) }}</td>
            <td class="num">{{ Format::money($book['total_receipts']) }}</td><td class="num">{{ Format::money($book['total_remittances']) }}</td><td></td><td class="num">{{ Format::money($book['closing']) }}</td></tr></tfoot>
    </table>
    <p style="margin-top:8px">Arrêté le présent livre au solde de <strong>{{ Format::money($book['closing']) }} DH</strong>.</p>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Visa du comptable<br><span dir="rtl">تأشيرة المحاسب</span></td></tr></table>
</body></html>
