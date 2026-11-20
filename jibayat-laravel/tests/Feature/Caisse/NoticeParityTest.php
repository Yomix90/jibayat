<?php

use App\Domain\Caisse\Models\NonPaymentNotice;
use App\Domain\Caisse\Models\ReminderLetter;
use App\Domain\Caisse\Services\NoticeService;
use App\Domain\Import\SqliteImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Parité Flask ↔ Laravel pour les avis de non-paiement et lettres de relance. tests/Fixtures/parity/avis.{db,json} sont produits par
| tools/parity/avis_parity.py qui exécute les VRAIES vues Flask (avis, generer_avis, generer_lettre, lettre_imprimer, lettre_approuver, lettre_annuler).
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    (new RolesAndPermissionsSeeder)->run();
    $this->expected = json_decode(file_get_contents(base_path('tests/Fixtures/parity/avis.json')), true);
    expect((new SqliteImporter(base_path('tests/Fixtures/parity/avis.db')))->run()['ok'])->toBeTrue();
    $this->service = app(NoticeService::class);
    $this->user = userWithRole('administrateur');
});

function expectNoticeOverview(array $o, array $e, string $step): void
{
    expect($o['total'])->toEqual((float) $e['total'], $step);
    expect($o['groups']->map(fn ($g) => ['module' => $g['module'], 'total' => $g['total'], 'nb_redevables' => $g['nb_redevables'], 'nb_avis_emis' => $g['nb_avis_emis'],
        'rows' => $g['rows']->map(fn ($r) => [$r->decl_number, $r->notice_number])->all()])->all())
        ->toEqual(array_map(fn ($m) => ['module' => $m['module'], 'total' => (float) $m['total'], 'nb_redevables' => $m['nb_redevables'], 'nb_avis_emis' => $m['nb_avis_emis'],
            'rows' => $m['rows']], $e['modules']), $step);
}

test('notices overview, batch generation and reminder letters match Flask avis.py', function () {
    expectNoticeOverview($this->service->overview(), $this->expected['initial'], 'initial');
    expectNoticeOverview($this->service->overview('TNB'), $this->expected['filtre_tnb'], 'TNB');
    expectNoticeOverview($this->service->overview('', 'emis'), $this->expected['filtre_emis'], 'emis');
    expectNoticeOverview($this->service->overview('', '', '2026'), $this->expected['filtre_2026'], '2026');
    expect($this->service->stats()->map(fn ($s) => [$s->module, (int) $s->nb, (int) $s->nb_redevables, (float) $s->total_du, (int) $s->nb_emis, (float) $s->total_recouvre])->all())
        ->toEqual(array_map(fn ($s) => [$s['module'], $s['nb_impayes'], $s['nb_redevables'], (float) $s['total_du'], $s['nb_emis'], (float) $s['total_recouvre']], $this->expected['initial']['stats']));

    $this->service->generate('TNB', $this->user);
    expectNoticeOverview($this->service->overview(), $this->expected['apres_generer_tnb'], 'générer TNB');
    $this->service->generate(null, $this->user);
    expectNoticeOverview($this->service->overview(), $this->expected['apres_generer_tout'], 'générer tout');

    $letter = $this->service->createLetter('TNB', 'relance', [], $this->user);
    $e = $this->expected['lettre'];
    expect($letter)->number->toBe($e['numero'])->taxpayer_count->toBe($e['nb'])->total_amount->toBe(number_format($e['total'], 2, '.', ''))
        ->and($this->service->letterLines($letter)->map(fn ($l) => [$l->decl_number, $l->notice_number, (float) $l->amount_due])->all())
        ->toEqual(array_map(fn ($d) => [$d[0], $d[1], (float) $d[2]], $e['details']));
    $this->service->approve($letter, $this->user);
    $second = $this->service->createLetter('DEBITS_BOISSONS', 'mise_en_demeure', [], $this->user);
    $this->service->cancelLetter($second, $this->user, 'Erreur de destinataire');

    expectNoticeOverview($this->service->overview(), $this->expected['final'], 'final');
    expect(ReminderLetter::orderBy('id')->get()->map(fn ($l) => [$l->number, $l->status, $l->taxpayer_count, (float) $l->total_amount])->all())
        ->toEqual(array_map(fn ($l) => [$l[0], $l[1], $l[2], (float) $l[3]], collect($this->expected['final']['lettres'])->sortBy(0)->values()->all()));
    expect(NonPaymentNotice::orderBy('number')->get()->mapWithKeys(fn ($n) => [$n->number => $n->reminder_letter_id ? ReminderLetter::find($n->reminder_letter_id)->number : null])->all())
        ->toEqual(collect($this->expected['avis_lettre'])->map(fn ($id) => $id ? 'LTR20260000'.$id : null)->all());
});
