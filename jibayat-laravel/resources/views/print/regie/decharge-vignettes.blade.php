@php use App\Support\Format; @endphp
<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Décharge de vignettes', 'titleAr' => 'إبراء ذمة — طوابع', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <table width="100%" class="meta"><tr><td>Fonctionnaire : <strong>{{ trim($employee->last_name.' '.$employee->first_name) }}</strong>@if ($employee->registration_number) — matricule {{ $employee->registration_number }}@endif</td>
        <td>Service : <strong>{{ $employee->service?->name ?? '—' }}</strong></td><td class="num">Remis le : <strong>{{ strtotime($date) ? date('d/m/Y', strtotime($date)) : '—' }}</strong></td></tr></table>
    <table class="data">
        <thead><tr><th width="5%">#</th><th width="18%">N° carnet</th><th>Désignation</th><th width="22%">Numéros</th><th width="16%">Valeur (DH)</th></tr></thead>
        <tbody>@foreach ($books as $b)<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $b->number }}</td><td>{{ $b->stampValue?->label }}</td><td class="center">{{ $b->first_number }} → {{ $b->last_number }}</td>
            <td class="num">{{ Format::money((float) $b->stampValue?->unit_value * (int) $b->stampValue?->units_per_book) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="4">Total : {{ $books->count() }} carnet(s)</td><td class="num">{{ Format::money($books->sum(fn ($b) => (float) $b->stampValue?->unit_value * (int) $b->stampValue?->units_per_book)) }}</td></tr></tfoot>
    </table>
    <p style="margin-top:10px">Je soussigné reconnais avoir reçu les carnets de vignettes ci-dessus et m’engage à en verser la contre-valeur à la régie des recettes.</p>
    <p class="ar" dir="rtl">أشهد أنا الموقع أسفله أنني تسلمت دفاتر الطوابع المبينة أعلاه وألتزم بدفع قيمتها إلى وكالة المداخيل.</p>
    <table width="100%" class="signatures"><tr><td>Le fonctionnaire<br><span dir="rtl">الموظف</span></td><td>Le régisseur des recettes<br><span dir="rtl">وكيل المداخيل</span></td></tr></table>
</body></html>
