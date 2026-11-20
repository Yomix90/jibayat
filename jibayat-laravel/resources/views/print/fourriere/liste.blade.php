@php use App\Support\Format; use App\Domain\Fourriere\Services\ImpoundCalculator; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Registre de la fourrière', 'titleAr' => 'سجل المحجز الجماعي'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $rows->count() }} dossier(s)</p>
    <table class="data">
        <thead>
            <tr><th width="6%">Dossier</th><th width="8%">Dépôt</th><th width="12%">Immatriculation<br>رقم التسجيل</th><th width="14%">Type<br>النوع</th>
                <th width="9%">Entrée<br>تاريخ الإيداع</th><th width="12%">Déposant · motif</th><th width="6%">Jours</th><th width="10%">Montant dû</th>
                <th width="13%">Propriétaire<br>المالك</th><th width="10%">Statut</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($c = $r['case'])
                <tr>
                    <td class="center"><strong>{{ $c->number }}</strong></td>
                    <td class="center">{{ $c->deposit_number }}</td>
                    <td class="center">{{ $c->registration }}</td>
                    <td>{{ $c->vehicle_type }}</td>
                    <td class="center">{{ $c->impounded_on?->format('d/m/Y') }}</td>
                    <td>{{ $c->depositor }}{{ $c->reason ? ' · '.$c->reason : '' }}</td>
                    <td class="center">{{ $r['days'] }}</td>
                    <td class="num">{{ $c->status === 'sortie' ? '—' : Format::money($r['amount']) }}</td>
                    <td>{{ $c->owner_name }}</td>
                    <td>{{ ImpoundCalculator::STATUSES[$c->status] ?? $c->status }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7">Total dû (véhicules au parc)</td><td class="num red">{{ Format::money($rows->where('case.status', '!=', 'sortie')->sum('amount')) }}</td><td colspan="2"></td></tr></tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le responsable de la fourrière<br><span dir="rtl">المسؤول عن المحجز</span></td><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td></tr>
    </table>
</body></html>
