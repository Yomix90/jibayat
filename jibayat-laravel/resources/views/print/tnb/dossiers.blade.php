@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Dossiers TNB — Terrains non bâtis', 'titleAr' => 'ملفات الرسم على الأراضي الحضرية غير المبنية'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ count($dossiers) }} dossier(s)@if ($filters) · Filtres : {{ $filters }}@endif</p>

    <table class="data">
        <thead>
            <tr>
                <th width="5%">Dossier<br>الملف</th>
                <th width="20%">Redevable<br>الملزم</th>
                <th width="9%">CIN / Tél<br>ب.ت.و / الهاتف</th>
                <th width="6%">Terrain<br>البقعة</th>
                <th width="22%">Situation du terrain<br>موقع البقعة</th>
                <th width="7%">Superficie<br>المساحة</th>
                <th width="4%">Zone</th>
                <th width="15%">Années dues<br>السنوات المستحقة</th>
                <th width="12%">Reste dû (DH)<br>الباقي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dossiers as $d)
                @foreach ($d['parcels'] as $r)
                    @php($p = $r['parcel'])
                    <tr>
                        @if ($loop->first)
                            <td class="center" rowspan="{{ count($d['parcels']) }}"><strong>{{ $d['file']->number }}</strong></td>
                            <td rowspan="{{ count($d['parcels']) }}">{{ $d['taxpayer']->displayName() }}@if ($d['taxpayer']->displayNameAr())<br><span dir="rtl">{{ $d['taxpayer']->displayNameAr() }}</span>@endif</td>
                            <td class="center" rowspan="{{ count($d['parcels']) }}">{{ $d['taxpayer']->cin }}<br>{{ $d['taxpayer']->phone }}</td>
                        @endif
                        <td class="center">{{ $p->number }}</td>
                        <td>{{ $p->locationLabel() }}</td>
                        <td class="num">{{ Format::money($p->area) }} m²</td>
                        <td class="center">{{ $p->zone }}</td>
                        <td>@if ($r['nb_non_paye'] === 0)<span class="green">À jour</span>@else{{ $r['period'] }}@endif</td>
                        <td class="num">@if ($r['total_due'] > 0)<span class="red">{{ Format::money($r['total_due']) }}</span>@if ($r['total_pending'] > 0)<br><span class="gold">dont en attente {{ Format::money($r['total_pending']) }}</span>@endif @else — @endif</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="8">Total général</td><td class="num red">{{ Format::money($kpi['amount_due']) }}</td></tr>
        </tfoot>
    </table>

    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
