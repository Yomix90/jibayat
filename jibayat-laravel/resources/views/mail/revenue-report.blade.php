@php use App\Support\Format; $r = $report; $c = \App\Models\Municipality::first(); @endphp
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Rapport des recettes</title></head>
<body style="margin:0;background:#F2F3F5;font-family:Arial,Helvetica,sans-serif;color:#1F2933">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #E6E8EC">
    <tr><td style="background:#1E2A3A;color:#fff;padding:20px 24px">
        <div style="font-size:12px;color:#C9A13B;font-weight:bold;letter-spacing:.08em">JIBAYAT · {{ mb_strtoupper($c?->name ?? 'Commune') }}</div>
        <div style="font-size:20px;font-weight:bold;margin-top:4px">Rapport des recettes</div>
        <div style="font-size:13px;color:#cbd5e1;margin-top:2px">{{ $frequency }} · du {{ $r['from']->format('d/m/Y') }} au {{ $r['to']->format('d/m/Y') }}</div>
    </td></tr>
    <tr><td style="padding:20px 24px">
        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px">
            <tr><td style="background:#F2F3F5;border-radius:8px"><div style="font-size:12px;color:#6B7280">Encaissé</div><div style="font-size:20px;font-weight:bold;color:#16A34A">{{ Format::money($r['paid_total']) }} DH</div><div style="font-size:12px;color:#6B7280">{{ $r['paid_n'] }} bulletin(s)</div></td>
                <td style="width:8px"></td>
                <td style="background:#F2F3F5;border-radius:8px"><div style="font-size:12px;color:#6B7280">Émis</div><div style="font-size:20px;font-weight:bold">{{ Format::money($r['declared_total']) }} DH</div><div style="font-size:12px;color:#6B7280">{{ $r['declared_n'] }} déclaration(s)</div></td></tr>
        </table>
        <h3 style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;margin:20px 0 8px">Encaissements par taxe</h3>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px">
            @forelse ($r['paid'] as $p)
                <tr style="border-bottom:1px solid #E6E8EC"><td>{{ $p['label'] }}</td><td align="right" style="color:#6B7280">{{ $p['n'] }}</td><td align="right" style="font-weight:bold">{{ Format::money($p['total']) }} DH</td></tr>
            @empty
                <tr><td style="color:#6B7280">Aucun encaissement sur la période.</td></tr>
            @endforelse
        </table>
        <h3 style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;margin:20px 0 8px">À suivre</h3>
        <p style="font-size:13px;margin:4px 0">Bulletins en attente de validation : <strong>{{ $r['pending_n'] }}</strong> ({{ Format::money($r['pending_total']) }} DH)</p>
        <p style="font-size:13px;margin:4px 0">Reste à recouvrer (toutes taxes) : <strong style="color:#B91C1C">{{ Format::money($r['remaining']) }} DH</strong></p>
        <p style="margin-top:20px"><a href="{{ route('home') }}" style="background:#C9A13B;color:#1E2A3A;text-decoration:none;font-weight:bold;padding:10px 18px;border-radius:999px;display:inline-block">Ouvrir Jibayat</a></p>
    </td></tr>
    <tr><td style="padding:12px 24px;font-size:11px;color:#6B7280;border-top:1px solid #E6E8EC">Envoi automatique. Pour ne plus le recevoir, modifiez vos préférences dans Paramètres ➜ Rapports par e-mail.</td></tr>
</table>
</td></tr></table>
</body></html>
