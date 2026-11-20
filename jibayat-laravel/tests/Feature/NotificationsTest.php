<?php

use App\Domain\Caisse\Models\PaymentSlip;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Systeme\ReportService;
use App\Livewire\NotificationBell;
use App\Livewire\Parametres\RapportsEmail;
use App\Mail\RevenueReport;
use App\Models\Taxpayer;
use App\Notifications\AlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00'));
    $t = Taxpayer::create(['number' => 'CTB1', 'last_name' => 'Alaoui', 'is_active' => true]);
    $d = TaxDeclaration::create(['number' => 'D1', 'module' => 'TNB', 'year' => 2026, 'taxpayer_id' => $t->id, 'total' => 900, 'status' => 'emis', 'declared_on' => '2026-06-10']);
    $s = PaymentSlip::create(['slip_number' => 'BUL1', 'tax_declaration_id' => $d->id, 'taxpayer_id' => $t->id, 'amount' => 900, 'status' => 'en_attente', 'paid_on' => '2026-06-10']);
    $s->forceFill(['created_at' => '2026-06-10 09:00'])->save();
    $d2 = TaxDeclaration::create(['number' => 'D2', 'module' => 'LOCATION_LOCAUX', 'year' => 2026, 'taxpayer_id' => $t->id, 'total' => 840, 'status' => 'paye', 'declared_on' => '2026-06-09']);
    PaymentSlip::create(['slip_number' => 'BUL2', 'tax_declaration_id' => $d2->id, 'taxpayer_id' => $t->id, 'amount' => 840, 'status' => 'paye', 'paid_on' => '2026-06-09']);
});

test('daily alerts reach the bell once per day, only for users allowed to see them', function () {
    $admin = userWithRole('administrateur');
    $collector = userWithRole('collecteur');
    $this->artisan('jibayat:alerts')->assertSuccessful();
    $this->artisan('jibayat:alerts')->assertSuccessful();
    $keys = $admin->fresh()->notifications->pluck('data.key')->sort()->values()->all();
    expect($keys)->toBe(['bulletins_en_attente', 'sauvegarde_ancienne'])
        ->and($collector->fresh()->notifications->pluck('data.key')->all())->not->toContain('bulletins_en_attente', 'sauvegarde_ancienne');

    $this->actingAs($admin);
    $n = $admin->notifications()->where('data->key', 'bulletins_en_attente')->first();
    Livewire::test(NotificationBell::class)->assertSee('1 bulletin(s) en attente depuis plus de 2 jours')->call('open', $n->id)->assertRedirect(route('caisse.bulletins'));
    expect($n->fresh()->read_at)->not->toBeNull();
    Livewire::test(NotificationBell::class)->call('markAll');
    expect($admin->fresh()->unreadNotifications)->toHaveCount(0);
});

test('alerts are also mailed to users who asked for it', function () {
    Notification::fake();
    $admin = userWithRole('administrateur');
    $admin->update(['email' => 'admin@commune.test', 'email_alerts' => true]);
    $this->artisan('jibayat:alerts');
    Notification::assertSentTo($admin, AlertNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));
});

test('revenue reports: period, content and recipients by frequency', function () {
    Mail::fake();
    $admin = userWithRole('administrateur');
    $admin->update(['email' => 'admin@commune.test', 'email_report' => 'hebdomadaire']);
    userWithRole('regisseur')->update(['email' => 'r@commune.test', 'email_report' => 'mensuel']);
    [$from, $to] = ReportService::period('hebdomadaire', CarbonImmutable::parse('2026-06-15'));
    expect($from->toDateString())->toBe('2026-06-08')->and($to->toDateString())->toBe('2026-06-14');
    $r = app(ReportService::class)->build($from, $to);
    expect($r['paid_total'])->toEqual(840.0)->and($r['declared_n'])->toBe(2)->and($r['pending_n'])->toBe(1)->and($r['remaining'])->toEqual(900.0);

    $this->artisan('jibayat:alerts --reports=hebdomadaire')->expectsOutput('1 rapport(s) envoyé(s).');
    Mail::assertSent(RevenueReport::class, fn ($m) => $m->hasTo('admin@commune.test'));
    Mail::assertNotSent(RevenueReport::class, fn ($m) => $m->hasTo('r@commune.test'));
    expect((new RevenueReport($r, 'Test'))->render())->toContain('840,00 DH')->toContain('Location des locaux');

    $this->actingAs($admin);
    Livewire::test(RapportsEmail::class)->assertSee('admin@commune.test')->set("prefs.{$admin->id}.report", 'quotidien')->call('save')->call('sendTest');
    expect($admin->fresh()->email_report)->toBe('quotidien');
    Mail::assertSent(RevenueReport::class, 2);
});
