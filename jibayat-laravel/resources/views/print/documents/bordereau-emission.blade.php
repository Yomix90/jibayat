@php
    use App\Support\Format;
    $c = \App\Models\Municipality::first();
    $x = 'xxxxxxxxxx';
@endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .be-title { text-align: center; font-size: 14pt; font-weight: bold; color: #1E2A3A; margin: 10px 0 2px; }
    .be-sub { text-align: center; font-size: 9pt; margin-bottom: 10px; }
    table.rub td { border: 0.4pt solid #CCCCCC; padding: 4px 6px; font-size: 9pt; }
    table.be { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.be th { background: #1E2A3A; color: #fff; font-size: 8pt; padding: 5px; border-bottom: 1.5pt solid #C9A13B; }
    table.be td { font-size: 8.5pt; padding: 4px 6px; border: 0.4pt solid #E6E8EC; }
    table.be td.lbl { font-weight: bold; }
    table.be tr.total td { font-weight: bold; border-top: 1.5pt solid #C9A13B; }
    table.grand td { font-weight: bold; font-size: 10pt; padding: 6px; }
    .navy { background: #1E2A3A; color: #fff; }
    .sigbox { border: 1pt solid #1E2A3A; background: #F2F3F5; text-align: center; font-weight: bold; padding: 8px; }
</style></head>
<body>
@foreach ($slips as $be)
    @if (! $loop->first)<pagebreak />@endif
    @include('print.partials.header', ['title' => "Bordereau d'émission", 'titleAr' => 'جدول الإصدار'])
    <div class="be-title">BORDEREAU D'EMISSION N° {{ $be['numero_bordereau'] }}</div>
    <div class="be-sub">Ordres de recettes (1) &nbsp;&nbsp;&nbsp; Titres d'annulation (1) — {{ \App\Domain\Documents\Services\IssueSlipService::MONTHS[$be['mois']] }} {{ $be['annee'] }}</div>

    <p><strong>RUBRIQUE BUDGÉTAIRE</strong></p>
    @php($code = (string) $be['code_budgetaire'])
    <table class="rub" style="border-collapse: collapse"><tr>
        <td width="30">1</td><td width="110">Partie section</td><td width="100">Chap : {{ substr($code, 0, 2) }}</td><td width="100">Art : {{ substr($code, 2, 2) }}</td><td width="100">{{ substr($code, 4) }}</td>
    </tr></table>
    <p><strong>Intitulé :</strong> {{ $be['intitule'] }} <span class="muted">({{ $be['rubrique'] }})</span></p>
    <p>(1) Ordres de recettes N° .........................................<br>(1) Titre d'annulation N° .........................................</p>

    <table class="be">
        <thead><tr><th width="36%"></th><th width="20%">Montant de la rubrique</th><th width="22%">Montant total des émissions collectives</th><th width="22%"></th></tr></thead>
        <tbody>
            <tr><td class="lbl">Report des antérieurs</td><td class="num">{{ Format::money($be['report_anterieurs']) }}</td><td class="num">{{ Format::money($be['report_global']) }}</td><td></td></tr>
            <tr><td class="lbl">Montant du présent bordereau</td><td class="num">{{ Format::money($be['montant_present']) }}</td><td class="num">{{ Format::money($be['total_present_global']) }}</td><td></td></tr>
            <tr class="total"><td>TOTAL</td><td class="num">{{ Format::money($be['total']) }}</td><td class="num">{{ Format::money($be['report_global'] + $be['total_present_global']) }}</td><td></td></tr>
        </tbody>
    </table>

    @php($p1 = $be['premiere_partie_global'])
    @php($p2 = $be['deuxieme_partie_global'])
    <table class="be">
        <thead><tr><th width="36%">Libellé</th><th width="20%">Montant</th><th width="22%">1ère partie</th><th width="22%">2ème partie</th></tr></thead>
        <tbody>
            <tr><td class="lbl">Montant brut du présent bordereau</td><td class="num">{{ Format::money($be['total']) }}</td><td class="center">{{ $x }}</td><td class="center">{{ $x }}</td></tr>
            <tr><td class="lbl">Montant net des antérieurs</td><td class="center">{{ $x }}</td><td class="num">{{ Format::money($p1) }}</td><td class="center">{{ $x }}</td></tr>
            <tr><td class="lbl">TITRE REJETS</td><td>........................</td><td></td><td></td></tr>
            @for ($i = 0; $i < 5; $i++)<tr><td>N° ....................................</td><td>........................</td><td></td><td></td></tr>@endfor
            <tr><td class="lbl">Total rejeté</td><td>........................</td><td></td><td></td></tr>
            <tr><td class="lbl">Montant net admis</td><td class="num">{{ Format::money($be['total']) }}</td><td class="num">{{ Format::money($be['total_present_global']) }}</td><td class="num">{{ $p2 === null ? '' : Format::money($p2) }}</td></tr>
            <tr><td class="lbl">Total général admis</td><td class="center">{{ $x }}</td><td class="num">{{ Format::money(($p1 ?? 0) + $be['total_present_global']) }}</td><td></td></tr>
        </tbody>
    </table>

    <table class="grand" width="100%" style="border: 1.5pt solid #C9A13B; margin-top: 8px; border-collapse: collapse"><tr>
        <td width="36%" style="text-align:right">TOTAL GÉNÉRAL ADMIS</td><td width="42%" class="navy" style="text-align:center">{{ Format::money(($p1 ?? 0) + $be['total_present_global']) }} DH</td><td width="22%"></td>
    </tr></table>

    <table width="100%" style="margin-top: 18px"><tr>
        <td width="45%" style="font-weight:bold">À {{ mb_strtoupper(str_replace('Commune ', '', $c?->name ?? '')) }} LE ................</td>
        <td width="10%"></td>
        <td width="45%" class="sigbox">Vu pour confirmation de la prise en charge<br><span style="font-size:8pt">(1) Receveur des finances</span><br><br><br></td>
    </tr></table>
    <p class="muted" style="font-size:7.5pt">(1) Barrer la mention inutile</p>
@endforeach
</body></html>
