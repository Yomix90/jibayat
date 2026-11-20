<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Services\AmountInWords;
use App\Domain\Documents\Services\TgrStatementParser;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Relevés GIR / TGR — Flask /regie/tgr : lecture du relevé « Activité mensuelle » exporté de GIR, filtres
 * (rubrique, période, recherche), totaux, montant en lettres, export Excel et impression.
 * Le dernier relevé chargé est conservé pour l'utilisateur (Flask : fichier temporaire en session).
 */
#[Title('Relevés GIR — TGR')]
class RelevesGir extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'documents.releves-gir';

    public $file;

    #[Url(as: 'rubrique')]
    public string $rubric = '';

    #[Url(as: 'debut')]
    public string $from = '';

    #[Url(as: 'fin')]
    public string $to = '';

    #[Url(as: 'q')]
    public string $search = '';

    public static function storedPath(int $userId): ?string
    {
        foreach (['xlsx', 'xls', 'xlsm'] as $ext) {
            if (Storage::exists("tgr/user-$userId.$ext")) {
                return Storage::path("tgr/user-$userId.$ext");
            }
        }

        return null;
    }

    public function upload(): void
    {
        $this->authorizeWrite('paiements.valider');
        $this->validate(['file' => ['required', 'file', 'max:20480', 'extensions:xls,xlsx,xlsm']], [], ['file' => 'relevé']);
        $ext = strtolower($this->file->getClientOriginalExtension());
        try {
            $parsed = (new TgrStatementParser)->parse($this->file->getRealPath());
        } catch (\Throwable $e) {
            $this->addError('file', 'Fichier illisible : ce n’est pas un relevé GIR exploitable.');

            return;
        }
        $id = auth()->id();
        foreach (['xlsx', 'xls', 'xlsm'] as $old) {
            Storage::delete("tgr/user-$id.$old");
        }
        $this->file->storeAs('tgr', "user-$id.$ext");
        $this->reset('file', 'rubric', 'from', 'to', 'search');
        activity('tgr')->causedBy(auth()->user())->withProperties(['operations' => count($parsed['operations'])])->log('Lecture d’un relevé GIR / TGR');
        $this->notify('Import réussi : '.count($parsed['operations']).' opération(s) trouvée(s).', count($parsed['operations']) ? 'success' : 'warning');
    }

    public function resetFilters(): void
    {
        $this->reset('rubric', 'from', 'to', 'search');
    }

    public function render()
    {
        $path = self::storedPath(auth()->id());
        $data = $path ? (new TgrStatementParser)->parse($path) : null;
        $ops = $data ? TgrStatementParser::filter($data['operations'], $this->rubric ?: null, $this->from ?: null, $this->to ?: null, $this->search) : [];
        $total = array_sum(array_column($ops, 'total'));
        $byRubric = collect($ops)->groupBy('libelle_rubrique')->map(fn ($g, $label) => ['label' => $label ?: '—', 'code' => $g->first()['code_rubrique'],
            'n' => $g->count(), 'total' => $g->sum('total')])->sortByDesc('total')->values();

        return view('livewire.documents.releves-gir', [
            'meta' => $data['meta'] ?? null, 'operations' => $ops, 'total' => $total, 'words' => AmountInWords::dirhams($total),
            'rubrics' => $data ? collect($data['operations'])->pluck('libelle_rubrique')->filter()->unique()->sort()->values() : collect(),
            'byRubric' => $byRubric,
            'sums' => ['principal' => array_sum(array_column($ops, 'principal')), 'majoration' => array_sum(array_column($ops, 'majoration')),
                'penalites' => array_sum(array_column($ops, 'penalites')), 'astreintes' => array_sum(array_column($ops, 'astreintes'))],
            'query' => array_filter(['rubrique' => $this->rubric, 'debut' => $this->from, 'fin' => $this->to, 'q' => $this->search]),
        ]);
    }
}
