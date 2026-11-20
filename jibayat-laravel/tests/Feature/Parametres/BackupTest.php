<?php

use App\Domain\Systeme\BackupService;
use App\Livewire\Parametres;
use App\Models\Taxpayer;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    config(['jibayat.backup.mysqldump' => null]); // export PHP : pas de dépendance au binaire dans les tests
    $this->actingAs(userWithRole('administrateur'));
});

test('manual backup produces a gzip SQL dump with structure and data, retention, copy folder, download, deletion', function () {
    Taxpayer::create(['number' => 'CTB-B1', 'last_name' => "O'Brien", 'last_name_ar' => 'العلوي', 'is_active' => true]);
    $copy = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jibayat_copy_'.uniqid();
    Livewire::test(Parametres\Sauvegardes::class)->set('settings.keep', '2')->set('settings.copy_dir', $copy)->call('saveSettings')->assertHasNoErrors()
        ->call('backupNow');
    $list = app(BackupService::class)->list();
    expect($list)->toHaveCount(1);
    $sql = gzdecode(file_get_contents(app(BackupService::class)->path($list[0]['name'])));
    expect($sql)->toContain('CREATE TABLE `taxpayers`')->toContain("O\\'Brien")->toContain('العلوي')
        ->and(file_exists($copy.DIRECTORY_SEPARATOR.$list[0]['name']))->toBeTrue();

    $this->travel(1)->seconds();
    app(BackupService::class)->create('manuel');
    $this->travel(1)->seconds();
    app(BackupService::class)->create('auto');
    expect(app(BackupService::class)->list())->toHaveCount(2);

    $name = app(BackupService::class)->list()[0]['name'];
    $this->get(route('parametres.sauvegarde-telecharger', $name))->assertOk();
    $this->get(route('parametres.sauvegarde-telecharger', '../../.env'))->assertNotFound();
    Livewire::test(Parametres\Sauvegardes::class)->assertSee($name)->call('delete', $name);
    expect(app(BackupService::class)->list())->toHaveCount(1);
    $this->artisan('jibayat:backup --type=auto --if-enabled')->assertSuccessful();
    File::deleteDirectory($copy);
});

test('a non administrator cannot back up or download', function () {
    $this->actingAs(userWithRole('regisseur'));
    Livewire::test(Parametres\Sauvegardes::class)->call('backupNow')->assertForbidden();
});
