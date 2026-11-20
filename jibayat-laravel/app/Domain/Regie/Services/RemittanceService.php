<?php

namespace App\Domain\Regie\Services;

use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Regie\Models\CashRemittance;
use App\Domain\Regie\Models\TreasuryDispatch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bordereaux de versement au comptable de rattachement (sorties de caisse) et bordereaux d'envoi des pièces
 * au Trésor (le receveur accuse réception).
 */
class RemittanceService
{
    public const DISPATCH_TYPES = [
        'versement' => 'Bordereau de versement', 'etat_mensuel' => 'État mensuel des recettes (Modèle 10)',
        'livre_caisse' => 'Livre de caisse (Modèle 6)', 'pv' => 'Procès-verbal', 'emission' => 'Bordereau d’émission', 'autre' => 'Autre pièce',
    ];

    public function __construct(protected NumberGenerator $numbers, protected Ledger $ledger) {}

    public static function accountant(): string
    {
        return (string) Setting::get('regie.comptable', 'Percepteur de rattachement');
    }

    /** Proposition de bordereau : recettes de la période par rubrique (hors virements du Ministère). */
    public function proposal(string $from, string $to): array
    {
        $r = $this->ledger->receipts($from, $to);
        $already = (float) CashRemittance::active()->where('period_from', '>=', $from)->where('period_to', '<=', $to)->sum('amount');

        return [
            'lines' => $r->groupBy(fn ($x) => $x->rubric_id ?? 0)->map(fn ($g) => ['code' => $g->first()->code ?? '—', 'label' => $g->first()->label ?? 'Sans rubrique',
                'amount' => round($g->sum('amount'), 2)])->sortBy('code')->values()->all(),
            'total' => round($r->sum('amount'), 2), 'cash' => round($r->where('mode', 'especes')->sum('amount'), 2),
            'other' => round($r->where('mode', '!=', 'especes')->sum('amount'), 2), 'already' => round($already, 2),
            'balance' => $this->ledger->balanceAt($to),
        ];
    }

    public function create(array $data, User $by): CashRemittance
    {
        $date = (string) $data['remitted_on'];
        MonthLock::assertOpen($date, 'remitted_on');
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Montant invalide.']);
        }
        $balance = $this->ledger->balanceAt($date);
        if ($amount > $balance + 0.001) {
            throw ValidationException::withMessages(['amount' => sprintf('Le versement dépasse le solde de caisse au %s (%s DH).', date('d/m/Y', strtotime($date)), number_format($balance, 2, ',', ' '))]);
        }

        return DB::transaction(function () use ($data, $date, $amount, $by) {
            $from = $data['period_from'] ?: $date;
            $to = $data['period_to'] ?: $date;
            $r = CashRemittance::create([
                'number' => $this->numbers->next('cash_remittances', 'number', 'BVC', (int) substr($date, 0, 4)),
                'remitted_on' => $date, 'period_from' => $from, 'period_to' => $to, 'payment_mode' => $data['payment_mode'] ?? 'especes',
                'amount' => $amount, 'lines' => $this->proposal($from, $to)['lines'], 'accountant' => $data['accountant'] ?: self::accountant(),
                'bank_reference' => $data['bank_reference'] ?: null, 'status' => 'verse', 'notes' => $data['notes'] ?: null, 'created_by' => $by->id,
            ]);
            activity('regie')->causedBy($by)->performedOn($r)->withProperties(['montant' => $amount])->log('Bordereau de versement');

            return $r;
        });
    }

    public function acknowledge(CashRemittance $r, string $on, ?string $ref, User $by): void
    {
        if ($r->isCancelled()) {
            throw ValidationException::withMessages(['ack' => 'Bordereau annulé.']);
        }
        $r->update(['status' => 'accuse', 'acknowledged_on' => $on, 'acknowledgement_ref' => $ref ?: null]);
        activity('regie')->causedBy($by)->performedOn($r)->log('Versement accusé par le comptable');
    }

    public function cancel(CashRemittance $r, User $by, string $reason): void
    {
        MonthLock::assertOpen($r->remitted_on->toDateString(), 'reason');
        $r->cancel($by, $reason);
    }

    /** Pièces proposées pour un envoi au Trésor (pas encore envoyées). */
    public function dispatchCandidates(): array
    {
        $sent = TreasuryDispatch::active()->get()->flatMap(fn ($d) => collect($d->items)->map(fn ($i) => $i['type'].':'.$i['ref']))->flip();
        $out = [];
        foreach (CashRemittance::active()->orderByDesc('remitted_on')->limit(60)->get() as $r) {
            $out[] = ['type' => 'versement', 'ref' => $r->number, 'label' => 'Bordereau de versement du '.$r->remitted_on->format('d/m/Y').' — '.number_format((float) $r->amount, 2, ',', ' ').' DH', 'count' => 1];
        }
        foreach (\App\Domain\Regie\Models\CashClosure::active()->orderByDesc('year')->orderByDesc('month')->limit(24)->get() as $c) {
            $out[] = ['type' => 'etat_mensuel', 'ref' => sprintf('%04d-%02d', $c->year, $c->month), 'label' => 'État mensuel '.Ledger::MONTHS[$c->month].' '.$c->year, 'count' => 1];
            $out[] = ['type' => 'livre_caisse', 'ref' => sprintf('%04d-%02d', $c->year, $c->month), 'label' => 'Livre de caisse '.Ledger::MONTHS[$c->month].' '.$c->year, 'count' => 1];
        }
        foreach (\App\Domain\Regie\Models\CashReport::active()->orderByDesc('report_date')->limit(30)->get() as $p) {
            $out[] = ['type' => 'pv', 'ref' => $p->number, 'label' => (MinutesService::TYPES[$p->type]['fr'] ?? 'PV').' du '.$p->report_date->format('d/m/Y'), 'count' => 1];
        }

        return array_values(array_filter($out, fn ($i) => ! isset($sent[$i['type'].':'.$i['ref']])));
    }

    /** @param list<array{type: string, ref: string, label: string, count?: int}> $items */
    public function dispatch(string $sentOn, ?string $recipient, array $items, ?string $notes, User $by): TreasuryDispatch
    {
        $items = array_values(array_filter($items, fn ($i) => trim((string) ($i['label'] ?? '')) !== ''));
        if (! $items) {
            throw ValidationException::withMessages(['items' => 'Choisissez au moins une pièce à envoyer.']);
        }

        return DB::transaction(function () use ($sentOn, $recipient, $items, $notes, $by) {
            $d = TreasuryDispatch::create([
                'number' => $this->numbers->next('treasury_dispatches', 'number', 'ENV', (int) substr($sentOn, 0, 4)),
                'sent_on' => $sentOn, 'recipient' => $recipient ?: self::accountant(),
                'items' => array_map(fn ($i) => ['type' => $i['type'] ?? 'autre', 'ref' => (string) ($i['ref'] ?? ''), 'label' => trim($i['label']), 'count' => max(1, (int) ($i['count'] ?? 1))], $items),
                'notes' => $notes ?: null, 'created_by' => $by->id,
            ]);
            activity('regie')->causedBy($by)->performedOn($d)->withProperties(['pieces' => count($items)])->log('Envoi de pièces au Trésor');

            return $d;
        });
    }

    public function acknowledgeDispatch(TreasuryDispatch $d, string $on, string $name, User $by): void
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages(['ackName' => 'Indiquez le nom du receveur.']);
        }
        $d->update(['acknowledged_on' => $on, 'acknowledged_by_name' => trim($name)]);
        activity('regie')->causedBy($by)->performedOn($d)->log('Accusé de réception du Trésor');
    }
}
