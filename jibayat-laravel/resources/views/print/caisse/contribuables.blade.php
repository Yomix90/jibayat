@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Répertoire des contribuables', 'titleAr' => 'لائحة الملزمين'])
    <table width="100%" class="meta"><tr>
        <td>{{ count($items) }} contribuable(s)@if ($filters) · filtre : {{ implode(', ', $filters) }}@endif</td>
        <td class="num">Total impayé : <strong>{{ Format::money($total_unpaid) }} DH</strong></td>
    </tr></table>
    <table class="data">
        <thead><tr><th width="4%">#</th><th width="11%">N°</th><th width="22%">Nom / raison sociale</th><th width="15%">الاسم</th><th width="9%">CIN / ICE</th>
            <th width="10%">Téléphone</th><th width="15%">Taxes</th><th width="14%">Impayé (DH)</th></tr></thead>
        <tbody>
            @foreach ($items as $t)
                <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $t->number }}</td><td>{{ $t->displayName() }}</td><td class="ar">{{ $t->displayNameAr() }}</td>
                    <td>{{ $t->cin ?: $t->ice }}</td><td>{{ $t->phone }}</td><td>{{ implode(', ', array_column($t->modules, 'label')) }}</td>
                    <td class="num {{ $t->unpaid > 0 ? 'red' : '' }}">{{ Format::money($t->unpaid) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7">Total</td><td class="num">{{ Format::money($total_unpaid) }}</td></tr></tfoot>
    </table>
</body></html>
