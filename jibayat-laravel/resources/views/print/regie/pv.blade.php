@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    use App\Domain\Regie\Services\MinutesService;
    $type = $r?->type ?? $type;
    $data = $r?->data ?? [];
    $people = $data['people'] ?? [];
    $dots = '……………………';
@endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => $t['fr'], 'titleAr' => $t['ar'], 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    @if ($r?->cancelled_at)<p class="red"><strong>PIÈCE ANNULÉE le {{ $r->cancelled_at->format('d/m/Y') }} : {{ $r->cancellation_reason }}</strong></p>@endif
    <table width="100%" class="meta"><tr><td>N° : <strong>{{ $r?->number ?? $dots }}</strong></td><td>Date : <strong>{{ $r?->report_date->format('d/m/Y') ?? $dots }}</strong></td>
        <td class="num">@if ($t['periodicity'] === 'month' && $r)Mois : <strong>{{ Ledger::MONTHS[$r->period] }} {{ $r->year }}</strong>@elseif ($t['periodicity'] === 'quarter' && $r)Trimestre : <strong>T{{ $r->period }} {{ $r->year }}</strong>@endif</td></tr></table>

    <p>
        @switch($type)
            @case('arrete') L’an {{ $r?->report_date->year ?? $dots }}, le {{ $r?->report_date->format('d/m/Y') ?? $dots }}, nous, {{ $people['regisseur'] ?? $regisseur ?: $dots }}, régisseur des recettes, avons procédé à l’arrêté de la caisse de la régie et constaté ce qui suit : @break
            @case('verification') L’an {{ $r?->report_date->year ?? $dots }}, le {{ $r?->report_date->format('d/m/Y') ?? $dots }}, nous, {{ $people['verifier'] ?? $dots }}, {{ $people['quality'] ?? '' }}, avons procédé à la vérification de la caisse de la régie des recettes tenue par {{ $people['regisseur'] ?? $regisseur ?: $dots }}, et constaté ce qui suit : @break
            @case('recolement') L’an {{ $r?->report_date->year ?? $dots }}, le {{ $r?->report_date->format('d/m/Y') ?? $dots }}, il a été procédé au récolement des tickets et vignettes détenus par la régie des recettes : @break
            @default L’an {{ $r?->report_date->year ?? $dots }}, le {{ $r?->report_date->format('d/m/Y') ?? $dots }}, {{ $people['outgoing'] ?? $dots }}, régisseur sortant, a remis le service de la régie des recettes à {{ $people['incoming'] ?? $dots }}, régisseur entrant, dans l’état suivant :
        @endswitch
    </p>

    @if ($t['cash'])
        <table class="data">
            <thead><tr><th>Billetage</th><th width="16%">Nombre</th><th width="22%">Montant (DH)</th></tr></thead>
            <tbody>
                @foreach (MinutesService::DENOMINATIONS as $den)
                    @php($n = $data['bills'][$den] ?? null)
                    <tr><td>{{ (float) $den >= 20 ? 'Billets' : 'Pièces' }} de {{ $den }} DH</td><td class="center">{{ $blank ? '' : ($n ?: '—') }}</td><td class="num">{{ $blank ? '' : ($n ? Format::money($n * (float) $den) : '') }}</td></tr>
                @endforeach
                <tr><td>Chèques en caisse</td><td></td><td class="num">{{ $blank ? '' : Format::money($data['cheques'] ?? 0) }}</td></tr>
            </tbody>
            <tfoot>
                <tr><td colspan="2">Encaisse constatée</td><td class="num">{{ $blank ? '' : Format::money($r->counted_balance) }}</td></tr>
                <tr><td colspan="2">Solde théorique (livre de caisse)</td><td class="num">{{ $blank ? '' : Format::money($r->theoretical_balance) }}</td></tr>
                <tr><td colspan="2">Écart (excédent + / déficit −)</td><td class="num {{ ! $blank && (float) $r->difference != 0 ? 'red' : '' }}">{{ $blank ? '' : Format::money($r->difference) }}</td></tr>
            </tfoot>
        </table>
    @endif

    @if ($t['stock'])
        @php($rows = $blank ? collect($stock)->map(fn ($v) => ['label' => $v['label'], 'unit_value' => $v['unit_value']])->all() : ($data['stock'] ?? []))
        <table class="data">
            <thead><tr><th>Tickets et vignettes</th><th width="11%">P.U.</th><th width="13%">Théorique</th><th width="13%">Compté</th><th width="11%">Écart</th><th width="15%">Écart (DH)</th></tr></thead>
            <tbody>@foreach ($rows as $v)<tr><td>{{ $v['label'] }}</td><td class="num">{{ Format::money($v['unit_value']) }}</td>
                <td class="num">{{ $blank ? '' : $v['theoretical'] }}</td><td class="num">{{ $blank ? '' : $v['counted'] }}</td><td class="num {{ ! $blank && $v['difference'] ? 'red' : '' }}">{{ $blank ? '' : $v['difference'] }}</td><td class="num">{{ $blank ? '' : Format::money($v['value_difference']) }}</td></tr>@endforeach</tbody>
        </table>
    @endif

    @if ($type === 'remise' && ! $blank && ($data['pending'] ?? []))
        <table class="data"><thead><tr><th>Opérations en suspens remises</th><th width="15%">Nombre</th></tr></thead>
            <tbody>@foreach ($data['pending'] as $l => $n)<tr><td>{{ $l }}</td><td class="center">{{ $n }}</td></tr>@endforeach</tbody></table>
    @endif

    <p style="margin-top:8px"><strong>Observations :</strong> {{ $r?->observations ?: ($blank ? $dots.$dots.$dots : 'Néant.') }}</p>
    <p>En foi de quoi, le présent procès-verbal a été dressé et signé par les parties.</p>
    <table width="100%" class="signatures"><tr>
        @switch($type)
            @case('verification')<td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Le vérificateur<br><span dir="rtl">المراقب</span></td>@break
            @case('remise')<td>Le régisseur sortant<br><span dir="rtl">الوكيل المغادر</span></td><td>Le régisseur entrant<br><span dir="rtl">الوكيل الجديد</span></td><td>L’ordonnateur<br><span dir="rtl">الآمر بالصرف</span></td>@break
            @default<td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td><td>Visa de l’ordonnateur<br><span dir="rtl">تأشيرة الآمر بالصرف</span></td>
        @endswitch
    </tr></table>
</body></html>
