<html><head><meta charset="utf-8">@include('print.partials.styles')</head>
<body>
    @include('print.partials.header', ['title' => 'Boîte d’archive', 'titleAr' => 'علبة الأرشيف', 'serviceFr' => 'Régie des recettes', 'serviceAr' => 'وكالة المداخيل'])
    <div style="border:2px solid #1E2A3A; padding:14px; margin:10px 0; text-align:center">
        <div style="font-size:26pt; font-weight:bold">{{ $box->code }}</div>
        <div style="font-size:14pt">{{ $box->label }}</div>
        <div class="muted">Exercice {{ $box->year ?? '—' }}@if ($box->location) · {{ $box->location }}@endif · {{ $box->documents->count() }} pièce(s)</div>
    </div>
    @if ($box->description)<p>{{ $box->description }}</p>@endif
    <table class="data">
        <thead><tr><th width="5%">#</th><th width="20%">N° registre</th><th>Pièce</th><th width="16%">Imprimée le</th><th width="10%">Scan</th></tr></thead>
        <tbody>@foreach ($box->documents->sortBy('number') as $d)<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $d->number }}</td><td>{{ $d->title }}</td><td class="center">{{ $d->first_printed_at->format('d/m/Y') }}</td><td class="center">{{ $d->scan_path ? 'oui' : 'non' }}</td></tr>@endforeach</tbody>
    </table>
</body></html>
