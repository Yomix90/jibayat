@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Bordereau de la taxe spéciale', 'titleAr' => 'جدول الرسم الخاص', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Rubrique : <strong>{{ $rubric->code }} — {{ $rubric->label }}</strong></td><td class="num">Mois : <strong>{{ Ledger::MONTHS[$month] }} {{ $year }}</strong></td></tr></table>
    <table class="data">
        <thead><tr><th width="5%">#</th><th width="12%">Date</th><th width="15%">N° de quittance</th><th>Partie versante</th><th width="11%">Quantité</th><th width="11%">P.U.</th><th width="14%">Montant</th></tr></thead>
        <tbody>@foreach ($entries as $e)<tr><td class="center">{{ $loop->iteration }}</td><td class="center">{{ $e->entry_date->format('d/m/Y') }}</td><td>{{ $e->reference ?: $e->number }}</td><td>{{ $e->payer }}</td>
            <td class="num">{{ $e->quantity !== null ? rtrim(rtrim(number_format((float) $e->quantity, 2, ',', ' '), '0'), ',') : '' }}</td><td class="num">{{ $e->unit_price !== null ? Format::money($e->unit_price) : '' }}</td><td class="num">{{ Format::money($e->amount) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="num">{{ rtrim(rtrim(number_format((float) $entries->sum('quantity'), 2, ',', ' '), '0'), ',') }}</td><td></td><td class="num">{{ Format::money($entries->sum('amount')) }}</td></tr></tfoot>
    </table>
    <p style="margin-top:8px">Arrêté le présent bordereau à la somme de : <strong>{{ $words }}</strong>.</p>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le comptable<br><span dir="rtl">المحاسب</span></td></tr></table>
</body></html>
