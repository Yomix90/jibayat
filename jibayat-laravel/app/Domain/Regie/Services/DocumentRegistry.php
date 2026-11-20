<?php

namespace App\Domain\Regie\Services;

use App\Domain\Regie\Models\ArchiveBox;
use App\Domain\Regie\Models\PrintedDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registre des documents imprimés : chaque pièce PDF (vue + nom de fichier) reçoit un n° « DOC{année}{n°} »
 * et un QR de contrôle imprimé en pied de page. Une réimpression de la même pièce garde son n° (compteur
 * d'impressions). Le scanner lit le QR (ou le code tapé) et dit si la feuille fait foi ; le scan papier
 * peut être joint, et la pièce rangée dans une boîte d'archive.
 */
class DocumentRegistry
{
    public const TYPES = [
        'print.regie' => 'Régie des recettes', 'print.caisse' => 'Caisse & recouvrement', 'print.documents' => 'États & documents',
        'print.declarations' => 'Déclarations d’assiette', 'print.tnb' => 'TNB', 'print.boissons' => 'Débits de boissons',
        'print.transport' => 'Transport', 'print.locations' => 'Locations', 'print.souk' => 'Souk',
        'print.domaine-public' => 'Domaine public', 'print.fourriere' => 'Fourrière',
    ];

    public static function family(string $view): string
    {
        foreach (self::TYPES as $prefix => $label) {
            if (str_starts_with($view, $prefix.'.')) {
                return $label;
            }
        }

        return 'Autres';
    }

    public function register(string $view, string $filename): PrintedDocument
    {
        $fingerprint = hash('sha256', $view.'|'.$filename);

        return DB::transaction(function () use ($view, $filename, $fingerprint) {
            $doc = PrintedDocument::where('fingerprint', $fingerprint)->lockForUpdate()->first();
            if ($doc) {
                $doc->update(['print_count' => $doc->print_count + 1, 'last_printed_at' => now()]);

                return $doc;
            }

            return PrintedDocument::create([
                'number' => app(\App\Domain\Caisse\Services\NumberGenerator::class)->next('printed_documents', 'number', 'DOC'),
                'token' => strtoupper(Str::random(6)), 'fingerprint' => $fingerprint, 'type' => $view,
                'title' => preg_replace('/\.pdf$/i', '', str_replace(['_', '-'], ' ', $filename)),
                'url' => request()?->fullUrl(), 'printed_by' => auth()->id(), 'first_printed_at' => now(), 'last_printed_at' => now(),
            ]);
        });
    }

    /** Lecture d'un code « DOC…-JETON » (ou de l'URL du QR) : la pièce est-elle authentique ? */
    public function verify(string $input): array
    {
        $input = trim($input);
        if (preg_match('~code=([A-Z0-9]+-[A-Z0-9]+)~i', $input, $m)) {
            $input = urldecode($m[1]);
        }
        if (! preg_match('/^(DOC\d{9})(?:-([A-Z0-9]{6}))?$/i', $input, $m)) {
            return ['status' => 'invalid', 'document' => null];
        }
        $doc = PrintedDocument::with(['printer', 'box'])->where('number', strtoupper($m[1]))->first();
        if (! $doc) {
            return ['status' => 'unknown', 'document' => null];
        }
        if (isset($m[2]) && strtoupper($m[2]) !== $doc->token) {
            return ['status' => 'forged', 'document' => $doc];
        }

        return ['status' => isset($m[2]) ? 'valid' : 'number_only', 'document' => $doc];
    }

    public function attachScan(PrintedDocument $doc, UploadedFile $file, User $by): void
    {
        $path = $file->storeAs('scans/'.$doc->first_printed_at->format('Y'), $doc->number.'.'.strtolower($file->getClientOriginalExtension() ?: 'pdf'), 'local');
        if ($doc->scan_path && $doc->scan_path !== $path) {
            Storage::disk('local')->delete($doc->scan_path);
        }
        $doc->update(['scan_path' => $path, 'scanned_at' => now(), 'scanned_by' => $by->id]);
        activity('regie')->causedBy($by)->performedOn($doc)->log('Scan joint au registre');
    }

    public function file(array $ids, ?int $boxId, User $by): int
    {
        if ($boxId && ArchiveBox::whereKey($boxId)->value('is_closed')) {
            throw ValidationException::withMessages(['box' => 'Cette boîte est fermée.']);
        }
        $n = PrintedDocument::whereIn('id', $ids)->update(['archive_box_id' => $boxId]);
        activity('regie')->causedBy($by)->withProperties(['boite' => $boxId, 'pieces' => $n])->log('Rangement en boîte d’archive');

        return $n;
    }

    /** Contrôle du registre : imprimées, scannées, rangées ; par famille. */
    public function control(): array
    {
        $docs = PrintedDocument::get(['type', 'scan_path', 'archive_box_id', 'first_printed_at']);

        return [
            'total' => $docs->count(), 'scanned' => $docs->whereNotNull('scan_path')->count(), 'filed' => $docs->whereNotNull('archive_box_id')->count(),
            'late' => $docs->whereNull('scan_path')->filter(fn ($d) => $d->first_printed_at->lt(now()->subDays(30)))->count(),
            'families' => $docs->groupBy(fn ($d) => self::family($d->type))->map(fn ($g, $k) => ['family' => $k, 'total' => $g->count(),
                'scanned' => $g->whereNotNull('scan_path')->count(), 'filed' => $g->whereNotNull('archive_box_id')->count()])->sortKeys()->values()->all(),
        ];
    }
}
