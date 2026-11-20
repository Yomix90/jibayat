@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Registre des baux — Location des locaux', 'titleAr' => 'سجل عقود كراء المحلات الجماعية'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $rows->count() }} bail(s)@if ($filters) · Filtres : {{ $filters }}@endif</p>

    <table class="data">
        <thead>
            <tr>
                <th width="3%">N°</th>
                <th width="7%">N° bail<br>رقم العقد</th>
                <th width="11%">Local<br>المحل</th>
                <th width="10%">Secteur<br>القطاع</th>
                <th width="20%">Locataire<br>المكتري</th>
                <th width="10%">CIN / Tél<br>ب.ت.و / الهاتف</th>
                <th width="8%">Loyer<br>السومة</th>
                <th width="19%">Situation<br>الوضعية</th>
                <th width="12%">Observations<br>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $r)
                @php($l = $r['lease'])
                <tr>
                    <td class="center muted">{{ $loop->iteration }}</td>
                    <td class="center"><strong>{{ $l->number }}</strong></td>
                    <td>{{ $l->shop?->number ?? $l->premises_ref ?? '—' }}</td>
                    <td>{{ $l->leaseSector?->label ?? '—' }}</td>
                    <td>{{ $l->taxpayer?->displayName() }}@if ($l->taxpayer?->displayNameAr())<br><span dir="rtl">{{ $l->taxpayer->displayNameAr() }}</span>@endif</td>
                    <td class="center">{{ $l->taxpayer?->cin }}<br>{{ $l->taxpayer?->phone }}</td>
                    <td class="num">{{ Format::money($r['rent']) }}</td>
                    <td>
                        @if ($r['nb_non_paye'] === 0)
                            <span class="green">À jour</span>
                        @else
                            {{ $r['periode'] }}<br>
                            <span class="red">Dû : {{ Format::money($r['total_du']) }} DH</span>
                            @if ($r['total_en_attente'] > 0)<br><span class="gold">dont en attente : {{ Format::money($r['total_en_attente']) }}</span>@endif
                        @endif
                    </td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total</td>
                <td class="num">{{ Format::money($rows->sum('rent')) }}</td>
                <td class="red">{{ Format::money($rows->sum('total_du')) }} DH dus</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
