@php use App\Support\Format; use App\Domain\Boissons\Services\BeverageCalculator; $t = $vehicle->taxpayer; $communeAr = $commune?->name_ar ?? $commune?->name ?? 'الجماعة'; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')
<style>
    .letter { font-size: 10.5pt; line-height: 1.6; }
    .rtl { direction: rtl; text-align: right; }
    .box { border: 0.6pt solid #E6E8EC; padding: 6px 10px; background: #F7F8FA; }
    .total { font-size: 13pt; font-weight: bold; color: #B91C1C; }
</style></head>
<body>
    @include('print.partials.header', ['title' => 'Avis de non-paiement — Stationnement & TPV', 'titleAr' => 'إشعار بأداء واجبات الوقوف والرسم على النقل العمومي للمسافرين'])

    <div class="letter">
        <table width="100%" class="rtl" dir="rtl"><tr>
            <td class="ar">عـدد : <span dir="ltr">{{ $number }}</span></td>
            <td class="ar">التاريخ : {{ now()->format('d/m/Y') }}</td>
            <td class="ar">رقم المركبة : <span dir="ltr">{{ $vehicle->registration }}</span></td>
        </tr></table>

        <div class="rtl" dir="rtl">
            <p><strong>رئيس المجلس الجماعي لـ {{ $communeAr }}</strong></p>
            <p>إلـــــى</p>
            <div class="box">
                {{ ($t?->company_name || $t?->person_type === 'morale') ? 'الشركة' : 'السيد(ة)' }} : {{ $t?->company_name_ar ?? $t?->displayNameAr() ?? $t?->displayName() }}<br>
                المركبة : {{ $vehicle->type }} — رقم التسجيل : <span dir="ltr">{{ $vehicle->registration }}</span>
            </div>
        </div>

        <table width="100%" class="data" style="margin-top:8px">
            <tr><td width="25%">Redevable / Exploitant</td><td><strong>{{ $t?->displayName() }}</strong></td><td width="20%">CIN / Identifiant</td><td>{{ $t?->cin ?? $t?->rc ?? '—' }}</td></tr>
            <tr><td>N° d'immatriculation</td><td><strong>{{ $vehicle->registration }}</strong></td><td>Type de véhicule</td><td>{{ $vehicle->type }}</td></tr>
        </table>

        <p>Il ressort du contrôle régulier des registres de la régie des recettes et de la fiscalité communale que vous ne vous êtes pas acquitté(e)
            des droits de stationnement et de la taxe sur le transport public de voyageurs (TPV) dus au titre de l'exploitation de votre véhicule susvisé
            pour la période {{ $period }} (<span dir="rtl">{{ $periodAr }}</span>) :</p>

        <table class="data">
            <thead><tr><th>Trimestre</th><th>Échéance</th><th>Stationnement (DH)</th><th>TPV (DH)</th><th>Total (DH)</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr>
                        <td class="center">T{{ $l['trimestre'] }} {{ $l['annee'] }}</td>
                        <td class="center">{{ \Illuminate\Support\Carbon::parse($l['echeance'])->format('d/m/Y') }}</td>
                        <td class="num">{{ Format::money($l['calc']['sta']) }}</td>
                        <td class="num">{{ Format::money($l['calc']['tpv']) }}</td>
                        <td class="num"><strong>{{ Format::money($l['calc']['total']) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>MONTANT TOTAL RESTANT DÛ : <span class="total">{{ Format::money($total) }} DH</span>
            — <span dir="rtl">المجموع الإجمالي الواجب أداؤه فوراً</span></p>
        <p>Par conséquent, vous êtes mis(e) en demeure de vous présenter auprès des services de la recette communale dans un délai n'excédant pas
            30 jours à compter de la notification du présent avis afin de régler ladite somme. Passé ce délai, des poursuites seront engagées
            conformément aux dispositions de la loi 47-06 relative à la fiscalité des collectivités territoriales.</p>
        <p>Fait à {{ $commune?->name ?? '…' }}, le {{ now()->format('d/m/Y') }}</p>
        <p style="text-align:right"><strong>Pour le Président du Conseil Communal</strong><br>L'Ordonnateur / Le Régisseur</p>
        <p class="muted" style="font-size:8pt">Réglementation : articles 65-72 de la loi n° 47-06 relative à la fiscalité des collectivités territoriales · Dossier : {{ $vehicle->registration }}</p>
    </div>
</body></html>
