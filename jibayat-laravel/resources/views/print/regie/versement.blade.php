@php use App\Support\Format; use App\Domain\Regie\Services\Ledger; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Bordereau de versement au comptable', 'titleAr' => 'جدول دفع المداخيل إلى المحاسب', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    @if ($r->cancelled_at)<p class="red"><strong>PIÈCE ANNULÉE le {{ $r->cancelled_at->format('d/m/Y') }} : {{ $r->cancellation_reason }}</strong></p>@endif
    <table width="100%" class="meta"><tr><td>N° : <strong>{{ $r->number }}</strong></td><td>Versé le : <strong>{{ $r->remitted_on->format('d/m/Y') }}</strong></td>
        <td>Recettes du {{ $r->period_from?->format('d/m/Y') }} au {{ $r->period_to?->format('d/m/Y') }}</td><td class="num">Mode : {{ Ledger::modeLabel($r->payment_mode) }}@if ($r->bank_reference) — {{ $r->bank_reference }}@endif</td></tr></table>
    <p>Comptable de rattachement : <strong>{{ $r->accountant }}</strong></p>
    <table class="data">
        <thead><tr><th width="16%">Code</th><th>Rubrique</th><th width="20%">Recettes de la période</th></tr></thead>
        <tbody>@foreach ($r->lines ?? [] as $l)<tr><td>{{ $l['code'] }}</td><td>{{ $l['label'] }}</td><td class="num">{{ Format::money($l['amount']) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="2">Recettes de la période</td><td class="num">{{ Format::money(collect($r->lines ?? [])->sum('amount')) }}</td></tr>
            <tr><td colspan="2">Montant versé</td><td class="num">{{ Format::money($r->amount) }}</td></tr></tfoot>
    </table>
    <p style="margin-top:8px">Arrêté le présent bordereau à la somme de : <strong>{{ $words }}</strong>.</p>
    @if ($r->notes)<p class="muted">{{ $r->notes }}</p>@endif
    @if ($r->acknowledged_on)<p class="green">Accusé par le comptable le {{ $r->acknowledged_on->format('d/m/Y') }}@if ($r->acknowledgement_ref) — {{ $r->acknowledgement_ref }}@endif.</p>@endif
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span><br>{{ $regisseur }}</td><td>Reçu par le comptable<br><span dir="rtl">توصل المحاسب</span></td></tr></table>
</body></html>
