{{-- En-tête officiel bilingue commun aux impressions (Flask : components/official_header.html). --}}
@php($c = \App\Models\Municipality::first())
<table class="official-header" width="100%">
    <tr>
        <td width="38%" class="fr">
            ROYAUME DU MAROC<br>
            MINISTÈRE DE L'INTÉRIEUR<br>
            {{ mb_strtoupper($c?->region ?? 'Région') }}<br>
            {{ mb_strtoupper($c?->province ?? 'Province') }}<br>
            <strong>{{ mb_strtoupper($c?->name ?? 'Commune') }}</strong><br>
            <span class="service">{{ $serviceFr ?? 'Service fiscalité & recouvrement' }}</span>
        </td>
        <td width="24%" class="center">
            <div class="doc-title">{{ $title }}</div>
            @isset($titleAr)<div class="doc-title-ar" dir="rtl">{{ $titleAr }}</div>@endisset
        </td>
        <td width="38%" class="ar" dir="rtl">
            المملكة المغربية<br>
            وزارة الداخلية<br>
            {{ $c?->region_ar ?? 'الجهة' }}<br>
            {{ $c?->province_ar ?? 'الإقليم' }}<br>
            <strong>{{ $c?->name_ar ?? 'الجماعة' }}</strong><br>
            <span class="service">{{ $serviceAr ?? 'مصلحة الوعاء والجبايات والتحصيل' }}</span>
        </td>
    </tr>
</table>
