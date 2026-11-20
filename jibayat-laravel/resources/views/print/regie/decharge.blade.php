@php use App\Support\Format; $dots = '……………………'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Bon de décharge de tickets', 'titleAr' => 'وصل إبراء ذمة — تذاكر', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    @if ($d?->cancelled_at)<p class="red"><strong>PIÈCE ANNULÉE : {{ $d->cancellation_reason }}</strong></p>@endif
    <table width="100%" class="meta"><tr><td>N° : <strong>{{ $d?->number ?? $dots }}</strong></td><td class="num">Date : <strong>{{ $d?->discharge_date->format('d/m/Y') ?? $dots }}</strong></td></tr></table>
    <table class="data" style="margin-top:10px">
        <tr><td width="35%"><strong>Agent bénéficiaire</strong></td><td>{{ $d?->employee ? trim($d->employee->last_name.' '.$d->employee->first_name) : $dots }}@if ($d?->employee?->registration_number) — matricule {{ $d->employee->registration_number }}@endif</td></tr>
        <tr><td><strong>Lieu (souk, marché)</strong></td><td>{{ $d?->place ?: $dots }}</td></tr>
        <tr><td><strong>Nature des tickets</strong></td><td>{{ $d?->value?->label ?? $dots }} — {{ $d ? Format::money($d->value?->unit_value).' DH l’unité' : $dots }}</td></tr>
        <tr><td><strong>Numéros</strong></td><td>{{ $d?->first_number ? 'du '.$d->first_number.' au '.$d->last_number : $dots }}</td></tr>
        <tr><td><strong>Quantité remise</strong></td><td>{{ $d ? number_format($d->quantity, 0, ',', ' ') : $dots }}</td></tr>
        <tr><td><strong>Valeur</strong></td><td>{{ $d ? Format::money($d->quantity * (float) $d->value?->unit_value).' DH' : $dots }}</td></tr>
        @if ($d?->status === 'solde')
            <tr><td><strong>Retour du {{ $d->settled_on?->format('d/m/Y') }}</strong></td><td>{{ $d->returned }} rendu(s) — {{ $d->sold() }} vendu(s) — {{ Format::money($d->amount_paid) }} DH versés</td></tr>
        @else
            <tr><td><strong>Retour</strong></td><td>Rendus : ……………… Vendus : ……………… Montant versé : ………………</td></tr>
        @endif
    </table>
    <p style="margin-top:10px">Je soussigné reconnais avoir reçu les tickets ci-dessus et m’engage à en verser le produit ou à restituer les tickets invendus à la régie.</p>
    <p class="ar" dir="rtl">أشهد أنا الموقع أسفله أنني تسلمت التذاكر المبينة أعلاه وألتزم بدفع مداخيلها أو إرجاع غير المبيع منها إلى الوكالة.</p>
    @if ($d?->corrections)<p class="muted">Modifications : @foreach ($d->corrections as $c){{ $c['le'] }} ({{ $c['par'] }}) : {{ $c['motif'] }}@if (! $loop->last) ; @endif @endforeach</p>@endif
    <table width="100%" class="signatures"><tr><td>L’agent<br><span dir="rtl">العون</span></td><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td></tr></table>
</body></html>
