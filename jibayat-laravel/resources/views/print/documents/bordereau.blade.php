@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Bordereau de versement au percepteur (TGR)', 'titleAr' => 'جدول دفع المداخيل إلى قباضة الخزينة العامة للمملكة'])
    <table width="100%" class="meta"><tr>
        <td>N° bordereau : <strong>{{ $remittance->number }}</strong></td>
        <td class="center">Date de versement : {{ $remittance->remitted_on?->format('d/m/Y') }}</td>
        <td class="center">{{ $remittance->books->count() }} carnet(s)</td>
        <td class="num">Montant versé : <strong>{{ Format::money($remittance->total_amount) }} DH</strong></td>
    </tr></table>
    <table class="data">
        <thead>
            <tr><th width="4%">#</th><th width="13%">N° carnet</th><th width="18%">Désignation</th><th width="18%">Employé</th><th width="14%">Service</th>
                <th width="13%">Série</th><th width="10%">Versé (DH)</th><th width="10%">Écart (DH)</th></tr>
        </thead>
        <tbody>
            @foreach ($remittance->books as $b)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td><td>{{ $b->number }}</td><td>{{ $b->stampValue?->label }}</td>
                    <td>{{ $b->employee?->last_name }} {{ $b->employee?->first_name }}</td><td>{{ $b->service?->name ?? '—' }}</td>
                    <td class="center">{{ $b->first_number }} → {{ $b->last_number }}</td>
                    <td class="num">{{ Format::money($b->amount_paid) }}</td>
                    <td class="num {{ (float) $b->difference < 0 ? 'red' : '' }}">{{ (float) $b->difference > 0 ? '+' : '' }}{{ Format::money($b->difference) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="6">Total général du versement</td><td class="num">{{ Format::money($remittance->total_amount) }}</td>
            <td class="num">{{ Format::money($remittance->books->sum('difference')) }}</td></tr></tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le percepteur (TGR)<br><span dir="rtl">القابض</span></td></tr>
    </table>
</body></html>
