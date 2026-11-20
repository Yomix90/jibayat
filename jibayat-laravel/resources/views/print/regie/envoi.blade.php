<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Bordereau d’envoi au Trésor', 'titleAr' => 'جدول إرسال الوثائق إلى الخزينة', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    @if ($d->cancelled_at)<p class="red"><strong>PIÈCE ANNULÉE le {{ $d->cancelled_at->format('d/m/Y') }} : {{ $d->cancellation_reason }}</strong></p>@endif
    <table width="100%" class="meta"><tr><td>N° : <strong>{{ $d->number }}</strong></td><td>Envoyé le : <strong>{{ $d->sent_on->format('d/m/Y') }}</strong></td><td class="num">Destinataire : <strong>{{ $d->recipient }}</strong></td></tr></table>
    <table class="data">
        <thead><tr><th width="5%">#</th><th>Désignation des pièces</th><th width="22%">Nature</th><th width="10%">Nombre</th></tr></thead>
        <tbody>@foreach ($d->items as $i)<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $i['label'] }}</td><td>{{ $types[$i['type']] ?? $i['type'] }}</td><td class="center">{{ $i['count'] ?? 1 }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="3">Total des pièces</td><td class="center">{{ collect($d->items)->sum(fn ($i) => $i['count'] ?? 1) }}</td></tr></tfoot>
    </table>
    @if ($d->notes)<p class="muted">{{ $d->notes }}</p>@endif
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span><br>{{ $regisseur }}</td>
        <td>Accusé de réception du receveur<br><span dir="rtl">إشعار بالتوصل</span><br>@if ($d->acknowledged_on)Reçu le {{ $d->acknowledged_on->format('d/m/Y') }} par {{ $d->acknowledged_by_name }}@else Reçu le ……………… par ………………@endif</td></tr></table>
</body></html>
