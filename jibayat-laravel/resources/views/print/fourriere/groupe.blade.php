@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Liste des véhicules mis aux enchères', 'titleAr' => 'لائحة المركبات المعروضة للبيع بالمزاد العلني'])
    <table width="100%" class="meta"><tr>
        <td>Groupe : <strong>{{ $group->number }}</strong> — {{ $group->label }}</td>
        <td class="center">Date : {{ $group->auction_on?->format('d/m/Y') ?? '……' }} · Lieu : {{ $group->place ?: '……' }}</td>
        <td class="ar" dir="rtl">ثمن الافتتاح : <span dir="ltr">{{ Format::money($group->opening_price) }}</span> درهم</td>
    </tr></table>
    <table class="data">
        <thead>
            <tr><th width="5%">N°</th><th width="10%">Dossier</th><th width="10%">Dépôt</th><th width="16%">Immatriculation<br>رقم التسجيل</th>
                <th width="20%">Type<br>النوع</th><th width="12%">Entrée<br>تاريخ الإيداع</th><th width="12%">État<br>الحالة</th><th width="15%">Observations<br>ملاحظات</th></tr>
        </thead>
        <tbody>
            @foreach ($group->vehicles as $v)
                @php($c = $v->impoundCase)
                <tr>
                    <td class="center">{{ $v->sort_order }}</td>
                    <td class="center">{{ $c?->number }}</td>
                    <td class="center">{{ $c?->deposit_number }}</td>
                    <td class="center"><strong>{{ $c?->registration }}</strong></td>
                    <td>{{ $c?->vehicle_type }}</td>
                    <td class="center">{{ $c?->impounded_on?->format('d/m/Y') }}</td>
                    <td class="center">{{ $v->condition }}</td>
                    <td>{{ $v->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="meta">{{ $group->vehicles->count() }} véhicule(s) · {{ $group->notes }}</p>
    <table width="100%" class="signatures">
        <tr><td>Le responsable de la fourrière<br><span dir="rtl">المسؤول عن المحجز</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
