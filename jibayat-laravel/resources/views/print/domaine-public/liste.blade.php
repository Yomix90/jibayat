@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Occupations du domaine public', 'titleAr' => 'احتلال الملك العام الجماعي'])
    <p class="meta">Édité le {{ now()->format('d/m/Y à H:i') }} · {{ $rows->count() }} occupation(s)</p>
    <table class="data">
        <thead>
            <tr><th width="5%">N°</th><th width="15%">Type<br>النوع</th><th width="17%">Emplacement<br>الموقع</th><th width="18%">Occupant<br>المحتل</th>
                <th width="7%">Surface</th><th width="11%">Redevance</th><th width="11%">Autorisation</th><th width="16%">Reste dû (DH)<br>الباقي</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($o = $r['occupation'])
                <tr>
                    <td class="center"><strong>{{ $o->number }}</strong></td>
                    <td>{{ $o->type }}</td>
                    <td>{{ $o->location }}</td>
                    <td>{{ $o->taxpayer?->displayName() }}@if ($o->taxpayer?->displayNameAr())<br><span dir="rtl">{{ $o->taxpayer->displayNameAr() }}</span>@endif</td>
                    <td class="num">{{ Format::money($o->area) }}</td>
                    <td class="num">{{ Format::money($r['trim_base']) }}/trim.</td>
                    <td class="center">{{ $o->ends_on?->format('d/m/Y') ?? '—' }}@if ($r['est_expire'])<br><span class="muted">expirée</span>@endif</td>
                    <td class="num">@if ($r['montant_impaye'] > 0)<span class="red">{{ Format::money($r['montant_impaye']) }}</span><br><span class="muted">{{ $r['nb_impayes'] }} trim.</span>@else <span class="green">À jour</span> @endif</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7">Total</td><td class="num red">{{ Format::money($rows->sum('montant_impaye')) }}</td></tr></tfoot>
    </table>
    <table width="100%" class="signatures">
        <tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le président du conseil communal<br><span dir="rtl">رئيس المجلس الجماعي</span></td></tr>
    </table>
</body></html>
