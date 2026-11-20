<?php

namespace App\Livewire\Aide;

use App\Livewire\AppPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;

/**
 * À propos — Flask /a-propos et /api/systeme/check-update : version, nouveautés (CHANGELOG.md),
 * vérification d'une version plus récente (adresse JIBAYAT_UPDATE_URL, format GitHub « releases/latest »).
 * La mise à jour elle-même se fait sur le serveur (voir le guide de déploiement).
 */
#[Title('À propos')]
class APropos extends AppPage
{
    public const ROUTE = 'parametres.a-propos';

    public ?array $update = null;

    public function check(): void
    {
        $url = config('jibayat.update_url');
        if (! $url) {
            $this->update = ['ok' => false, 'message' => 'Vérification non configurée (JIBAYAT_UPDATE_URL).'];

            return;
        }
        try {
            $r = Http::timeout(8)->acceptJson()->withHeaders(['User-Agent' => 'Jibayat'])->get($url)->throw()->json();
        } catch (\Throwable) {
            $this->update = ['ok' => false, 'message' => 'Serveur des mises à jour injoignable.'];

            return;
        }
        $remote = ltrim((string) ($r['tag_name'] ?? $r['version'] ?? ''), 'vV');
        $local = config('jibayat.version');
        $this->update = ['ok' => true, 'remote' => $remote, 'has_update' => $remote !== '' && version_compare($remote, preg_replace('/-.*$/', '', $local), '>'),
            'name' => $r['name'] ?? $remote, 'notes' => Str::limit((string) ($r['body'] ?? ''), 1500), 'url' => $r['html_url'] ?? null];
    }

    public function render()
    {
        $changelog = is_file(base_path('CHANGELOG.md')) ? Str::markdown(file_get_contents(base_path('CHANGELOG.md')), ['html_input' => 'strip']) : '';

        return view('livewire.aide.a-propos', ['changelog' => $changelog, 'version' => config('jibayat.version'), 'commune' => \App\Models\Municipality::first()]);
    }
}
