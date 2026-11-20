@php use App\Support\Format; use App\Domain\Documents\Services\RegisterService; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'État des pièces annulées', 'titleAr' => 'بيان الوثائق الملغاة'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ count($rows) }} pièce(s)</p>
    <table class="data">
        <thead>
            <tr><th width="9%">Nature</th><th width="13%">N° de pièce</th><th width="15%">Taxe</th><th width="16%">Redevable</th><th width="9%">Montant</th>
                <th width="12%">Annulée le</th><th width="10%">Par</th><th width="16%">Motif</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r->kind }}</td><td>{{ $r->number }}</td><td>{{ RegisterService::MODULES[$r->module]['fr'] ?? ($r->module === 'REGIE' ? 'Régie des recettes' : $r->module) }}</td>
                    <td>{{ RegisterService::taxpayerName($r) }}</td><td class="num">{{ Format::money($r->amount) }}</td>
                    <td class="center">{{ $r->cancelled_at ? \Illuminate\Support\Carbon::parse($r->cancelled_at)->format('d/m/Y H:i') : '—' }}</td>
                    <td>{{ $r->by_name }}</td><td>{{ $r->reason }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Total annulé</td><td class="num">{{ Format::money(array_sum(array_map(fn ($r) => (float) $r->amount, $rows))) }}</td><td colspan="3"></td></tr></tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le contrôle<br><span dir="rtl">المراقبة</span></td></tr>
    </table>
</body></html>
