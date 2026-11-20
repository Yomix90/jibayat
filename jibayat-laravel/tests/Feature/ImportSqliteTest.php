<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Import\SqliteImporter;
use App\Domain\Tnb\Models\Parcel;
use App\Models\Taxpayer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\FlaskFixture;

beforeEach(function () {
    (new DatabaseSeeder)->run();
    $this->sqlite = FlaskFixture::create();
});

afterEach(function () {
    @unlink($this->sqlite);
});

function tableReport(array $report, string $source): array
{
    return collect($report['tables'])->firstWhere('source', $source);
}

test('every Flask row is imported, with no gap and matching control sums', function () {
    $report = (new SqliteImporter($this->sqlite))->run();

    foreach ($report['tables'] as $t) {
        expect($t['gap'])->toBe(0, "écart sur {$t['source']}")
            ->and($t['rejected'])->toBe(0, "rejets sur {$t['source']} : ".implode(' | ', $t['rejections']));
        foreach ($t['sums'] as $s) {
            expect($s['ok'])->toBeTrue("somme {$t['source']}.{$s['column']} : {$s['source']} ≠ {$s['target']}");
        }
    }
    expect($report['unknown'])->toBe([])
        ->and($report['ok'])->toBeTrue()
        ->and(Taxpayer::count())->toBe(2)
        ->and(TaxDeclaration::count())->toBe(4)
        ->and(PaymentSlip::sum('amount'))->toEqual('12613.00')
        ->and(DB::table('impound_settings')->count())->toBe(2); // doublon Flask conservé
});

test('a second run updates instead of duplicating', function () {
    (new SqliteImporter($this->sqlite))->run();
    $counts = collect(['taxpayers', 'parcels', 'tax_declarations', 'payment_slips', 'tax_declaration_periods', 'activity_log'])
        ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

    $report = (new SqliteImporter($this->sqlite))->run();

    expect(tableReport($report, 'contribuables'))->toMatchArray(['inserted' => 0, 'updated' => 2, 'gap' => 0]);
    foreach ($counts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count, "doublons dans $table");
    }
});

test('a dry run writes nothing', function () {
    $before = Taxpayer::count();

    $report = (new SqliteImporter($this->sqlite))->run(dryRun: true);

    expect($report['dry_run'])->toBeTrue()
        ->and(tableReport($report, 'contribuables')['inserted'])->toBe(2)
        ->and(Taxpayer::count())->toBe($before);
});

test('amounts, dates and Arabic text are converted faithfully', function () {
    (new SqliteImporter($this->sqlite))->run();

    $decl = TaxDeclaration::where('number', 'DCL202600001')->first();
    expect($decl->principal)->toBe('9010.00')
        ->and($decl->surcharge)->toBe('450.50')
        ->and($decl->total)->toBe('11713.00')
        ->and($decl->due_on->toDateString())->toBe('2025-02-28')
        ->and($decl->paid_on->toDateString())->toBe('2026-01-20');

    expect(Taxpayer::where('number', 'CTB-1')->value('last_name_ar'))->toBe('العلوي')
        ->and(DB::table('tariffs')->where('code', 'TNB-B')->value('value'))->toBe('8.5000')
        ->and(DB::table('parcel_co_owners')->value('share_percent'))->toBe('33.3333')
        ->and(PaymentSlip::where('slip_number', 'BV-100')->value('receipt_number'))->toBe('Q-555');
});

test('declarations are linked to their taxed object and Location months become periods', function () {
    (new SqliteImporter($this->sqlite))->run();

    $tnb = TaxDeclaration::where('number', 'DCL202600001')->first();
    expect($tnb->taxable)->toBeInstanceOf(Parcel::class)
        ->and($tnb->taxable->land_title)->toBe('TF-12/55');

    $loc = TaxDeclaration::where('number', 'LOC-DECL202600001')->first();
    expect($loc->taxable_type)->toBe('lease')
        ->and($loc->periods()->orderBy('period')->pluck('period')->all())->toBe(['2025-01', '2025-02', '2025-03']);
});

test('cancelled Flask declarations are kept and flagged as cancelled', function () {
    (new SqliteImporter($this->sqlite))->run();

    $decl = TaxDeclaration::where('number', 'DCL-TDB202600003')->first();
    expect($decl)->not->toBeNull()
        ->and($decl->status)->toBe('annule')
        ->and($decl->isCancelled())->toBeTrue()
        ->and($decl->cancellation_reason)->toContain('Flask');
});

test('orphan references and unreadable dates are reported, not hidden', function () {
    $report = (new SqliteImporter($this->sqlite))->run();

    expect(implode(' ', tableReport($report, 'tarifs')['warnings']))->toContain('orpheline')
        ->and(implode(' ', tableReport($report, 'declarations')['warnings']))->toContain('objet taxé introuvable')
        ->and(implode(' ', tableReport($report, 'utilisateurs')['warnings']))->toContain('date illisible');
    expect(TaxDeclaration::where('number', 'DCL-STA202600004')->value('taxable_id'))->toBeNull();
});

test('users keep their identity, get the matching role and must reset their password', function () {
    $report = (new SqliteImporter($this->sqlite))->run();

    $seededAdmin = User::where('username', 'admin')->first();
    expect($seededAdmin->legacy_id)->toBe(1)
        ->and($seededAdmin->must_change_password)->toBeFalse(); // compte existant : mot de passe conservé

    $hassan = User::where('username', 'hassan')->first();
    expect($hassan->hasRole('regisseur'))->toBeTrue()
        ->and($hassan->must_change_password)->toBeTrue()
        ->and($hassan->email)->toBeNull(); // e-mail déjà pris par admin dans Flask

    $stagiaire = User::where('username', 'stagiaire')->first();
    expect($stagiaire->is_active)->toBeFalse()
        ->and($stagiaire->roles)->toBeEmpty()
        ->and(implode(' ', tableReport($report, 'utilisateurs')['warnings']))->toContain('role_maison');

    expect(DB::table('payment_slips')->where('slip_number', 'BV-100')->value('cashier_id'))->toBe($seededAdmin->id);
});

test('Flask user logs are copied into the activity log once', function () {
    (new SqliteImporter($this->sqlite))->run();
    (new SqliteImporter($this->sqlite))->run();

    expect(DB::table('activity_log')->where('log_name', 'flask')->count())->toBe(1);
});

test('skipped technical and legacy tables are listed with their row counts', function () {
    $report = (new SqliteImporter($this->sqlite))->run();

    $skipped = collect($report['skipped'])->keyBy('source');
    expect($skipped['schema_meta']['rows'])->toBe(1)
        ->and($skipped['roles']['rows'])->toBe(3)
        ->and($skipped)->toHaveKey('tnb_terrains');
});

test('an encrypted or invalid file is refused with a clear message', function () {
    $fake = tempnam(sys_get_temp_dir(), 'enc');
    file_put_contents($fake, random_bytes(64));

    expect(fn () => (new SqliteImporter($fake))->run())->toThrow(RuntimeException::class, 'export_sqlite_plain');
    @unlink($fake);
});

test('the artisan command prints the report and fails when there are gaps', function () {
    $this->artisan('jibayat:import-sqlite', ['path' => $this->sqlite])
        ->expectsOutputToContain('Import terminé')
        ->assertExitCode(0);

    $this->artisan('jibayat:import-sqlite', ['path' => 'introuvable.db'])->assertExitCode(1);
});
