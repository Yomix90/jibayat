<?php

use App\Support\Navigation;
use Illuminate\Support\Facades\File;

test('the interface switches to Arabic (right to left) and back, and the choice is kept in the account', function () {
    $user = userWithRole('administrateur');
    $this->actingAs($user)->get(route('tnb.terrains'))->assertOk()->assertSee('dir="ltr"', false)->assertSee('Modules fiscaux');

    $this->post(route('langue', 'ar'))->assertRedirect();
    expect($user->fresh()->locale)->toBe('ar');
    $this->get(route('tnb.terrains'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false)
        ->assertSee('الرسوم الجبائية')->assertSee('الوكالة والوثائق')->assertSee('لوحة القيادة العامة')
        ->assertSee('الملفات والبقع')->assertDontSee('Modules fiscaux');
    $this->get('/')->assertOk()->assertSee('الوضعية حسب الرسم');

    $this->post(route('langue', 'fr'));
    $this->get(route('tnb.terrains'))->assertSee('dir="ltr"', false)->assertSee('Modules fiscaux');
    $this->post(route('langue', 'xx'))->assertNotFound();
});

test('a guest chooses the language on the login page', function () {
    $this->get(route('login'))->assertSee('Se connecter');
    $this->post(route('langue', 'ar'));
    $this->get(route('login'))->assertSee('تسجيل الدخول')->assertSee('dir="rtl"', false);
});

test('every navigation label and every static interface text has an Arabic translation', function () {
    $ar = json_decode(File::get(lang_path('app/ar.json')), true);
    $missing = [];
    foreach (app(Navigation::class)->all() as $space) {
        foreach (array_filter([$space['title'], ...collect($space['sections'])->flatMap(fn ($s) => [$s['title'], ...collect($s['pages'])->flatMap(fn ($p) => [$p['title'], $p['subtitle']])])]) as $label) {
            if (! preg_match('/\p{Arabic}/u', $label) && ! isset($ar[$label])) {
                $missing[] = $label;
            }
        }
    }
    foreach (File::allFiles(resource_path('views')) as $file) {
        if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'print'.DIRECTORY_SEPARATOR) || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'mail'.DIRECTORY_SEPARATOR)) {
            continue;
        }
        preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $m);
        foreach ($m[1] as $key) {
            $key = str_replace("\\'", "'", $key);
            if (__($key, [], 'ar') === $key && ! preg_match('/^(MAIL_\w+|\.env|storage\/app\/private|php artisan .*|D:.*)$/', $key)) {
                $missing[] = $key;
            }
        }
    }
    expect(array_values(array_unique($missing)))->toBe([]);
});
