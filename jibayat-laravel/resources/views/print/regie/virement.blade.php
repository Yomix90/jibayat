@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Autorisation de virement', 'titleAr' => 'إذن بالتحويل', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>N° : <strong>{{ $e->number }}</strong></td><td class="num">Date : <strong>{{ $e->entry_date->format('d/m/Y') }}</strong></td></tr></table>
    <table class="data" style="margin-top:12px">
        <tr><td width="35%"><strong>Partie versante</strong></td><td>{{ $e->payer ?: 'Ministère' }}</td></tr>
        <tr><td><strong>Objet / rubrique</strong></td><td>{{ $e->rubric?->code }} — {{ $e->rubric?->label }}@if ($e->description)<br>{{ $e->description }}@endif</td></tr>
        <tr><td><strong>Référence de l’ordre de virement</strong></td><td>{{ $e->reference }}</td></tr>
        <tr><td><strong>Montant</strong></td><td><strong>{{ Format::money($e->amount) }} DH</strong><br>{{ $words }}</td></tr>
        <tr><td><strong>Compte bénéficiaire (RIB de la commune)</strong></td><td>{{ $rib ?: '……………………………………………' }}</td></tr>
        <tr><td><strong>Comptable assignataire</strong></td><td>{{ $comptable }}</td></tr>
    </table>
    <p style="margin-top:10px">Le régisseur des recettes autorise le versement de la somme ci-dessus par virement au compte de la commune ouvert auprès de la Trésorerie Générale du Royaume. Cette recette ne transite pas par la caisse de la régie.</p>
    <p class="ar" dir="rtl">يأذن وكيل المداخيل بأداء المبلغ أعلاه عن طريق التحويل إلى حساب الجماعة المفتوح لدى الخزينة العامة للمملكة، ولا يمر هذا المدخول عبر صندوق الوكالة.</p>
    <table width="100%" class="signatures"><tr><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span><br>{{ $regisseur }}</td><td>L’ordonnateur<br><span dir="rtl">الآمر بالصرف</span></td></tr></table>
</body></html>
