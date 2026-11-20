<?php

namespace App\Domain\Import;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Import rejouable d'une base SQLite Jibayat (Flask, export en clair) vers MySQL.
 *
 * - Idempotent : chaque ligne garde l'id Flask dans « legacy_id » ; un second passage met à jour
 *   au lieu de dupliquer.
 * - Aucune donnée perdue en silence : chaque table produit un compte source / insérées / mises à jour /
 *   rejetées (avec motif), les avertissements (dates illisibles, références orphelines…) et des
 *   contrôles de sommes sur les montants.
 * - La base SQLite n'est lue qu'en SELECT.
 */
class SqliteImporter
{
    protected Connection $legacy;

    /** @var array<string, array<int|string, int>> table Flask ➜ [id Flask ➜ id Laravel] */
    protected array $idMaps = [];

    protected array $report = [];

    public function __construct(protected string $path) {}

    public function run(bool $dryRun = false, ?callable $progress = null): array
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("Fichier SQLite introuvable : {$this->path}");
        }
        $this->assertPlainSqlite();

        config(['database.connections.jibayat_legacy' => [
            'driver' => 'sqlite', 'database' => $this->path, 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('jibayat_legacy');
        $this->legacy = DB::connection('jibayat_legacy');
        DB::connection()->disableQueryLog();

        if (Role::count() === 0) {
            (new RolesAndPermissionsSeeder)->run();
        }

        $sourceTables = collect($this->legacy->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')->all();

        $this->report = [
            'source' => realpath($this->path) ?: $this->path,
            'started_at' => now()->toDateTimeString(),
            'dry_run' => $dryRun,
            'tables' => [],
            'skipped' => [],
            'unknown' => [],
        ];

        $started = DB::transactionLevel();
        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach (LegacyTableMap::tables() as $spec) {
                $progress && $progress($spec['source']);
                $this->report['tables'][] = in_array($spec['source'], $sourceTables, true)
                    ? $this->importTable($spec)
                    : ['source' => $spec['source'], 'target' => $spec['target'], 'missing' => true,
                        'source_count' => 0, 'inserted' => 0, 'updated' => 0, 'rejected' => 0, 'gap' => 0,
                        'warnings' => ['table absente de la base source'], 'rejections' => [], 'sums' => []];
            }
        } finally {
            if ($dryRun) {
                while (DB::transactionLevel() > $started) {
                    DB::rollBack();
                }
            }
        }

        $mapped = collect(LegacyTableMap::tables())->pluck('source')->all();
        foreach ($sourceTables as $table) {
            if (isset(LegacyTableMap::SKIPPED[$table])) {
                $this->report['skipped'][] = [
                    'source' => $table, 'rows' => $this->count($table), 'reason' => LegacyTableMap::SKIPPED[$table],
                ];
            } elseif (! in_array($table, $mapped, true)) {
                $this->report['unknown'][] = ['source' => $table, 'rows' => $this->count($table)];
            }
        }

        $this->report['finished_at'] = now()->toDateTimeString();
        $this->report['ok'] = collect($this->report['tables'])->every(
            fn ($t) => $t['gap'] === 0 && $t['rejected'] === 0 && collect($t['sums'])->every(fn ($s) => $s['ok'])
        ) && $this->report['unknown'] === [];

        return $this->report;
    }

    // ─────────────────────────────────────────────────────────────

    protected function importTable(array $spec): array
    {
        $source = $spec['source'];
        $target = $spec['target'];
        $key = $spec['key'] ?? 'id';
        $targetKey = $spec['target_key'] ?? 'legacy_id';

        $result = ['source' => $source, 'target' => $target, 'missing' => false, 'source_count' => 0,
            'inserted' => 0, 'updated' => 0, 'rejected' => 0, 'gap' => 0, 'warnings' => [], 'rejections' => [], 'sums' => []];

        if (($spec['special'] ?? null) === 'user_logs') {
            return $this->importUserLogs($result);
        }

        $this->idMaps[$source] = DB::table($target)->whereNotNull($targetKey)->pluck('id', $targetKey)->all();
        $warnings = [];

        foreach ($this->legacy->table($source)->orderBy($key)->cursor() as $row) {
            $row = (array) $row;
            $result['source_count']++;
            $legacyKey = $row[$key];

            try {
                $data = $this->transform($spec, $row, $warnings);
                if (($spec['special'] ?? null) === 'users') {
                    $data = $this->prepareUser($row, $data, $warnings);
                }
                if (($spec['special'] ?? null) === 'declarations') {
                    $data = $this->prepareDeclaration($row, $data, $warnings);
                }
                if ($targetKey === 'legacy_id') {
                    $data['legacy_id'] = $legacyKey;
                }
                $data['updated_at'] ??= now();

                $existingId = $this->idMaps[$source][$legacyKey] ?? $this->matchExisting($spec, $data);
                if ($existingId) {
                    unset($data['created_at']);
                    if (($spec['special'] ?? null) === 'users') {
                        unset($data['password'], $data['must_change_password']);
                    }
                    DB::table($target)->where('id', $existingId)->update($data);
                    $newId = $existingId;
                    $result['updated']++;
                } else {
                    $data['created_at'] ??= now();
                    $newId = DB::table($target)->insertGetId($data);
                    $result['inserted']++;
                }
                $this->idMaps[$source][$legacyKey] = $newId;

                if (($spec['special'] ?? null) === 'users') {
                    $this->assignRole($newId, $row, $warnings);
                }
                if (($spec['special'] ?? null) === 'declarations') {
                    $this->syncPeriods($newId, $row);
                }
            } catch (Throwable $e) {
                $result['rejected']++;
                if (count($result['rejections']) < 50) {
                    $result['rejections'][] = "id {$legacyKey} : ".Str::limit($this->cleanMessage($e), 220);
                }
            }
        }

        $imported = DB::table($target)->whereIn($targetKey, array_keys($this->idMaps[$source]))->count();
        $result['gap'] = $result['source_count'] - $imported;
        $result['warnings'] = $this->summarizeWarnings($warnings);
        $result['sums'] = $this->compareSums($spec, $source, $target, $targetKey);

        return $result;
    }

    protected function transform(array $spec, array $row, array &$warnings): array
    {
        $data = [];
        foreach ($spec['columns'] as $targetCol => $def) {
            [$sourceCol, $type] = $def;
            $default = $def[2] ?? null;
            $value = array_key_exists($sourceCol, $row) ? $row[$sourceCol] : null;
            if (is_string($value) && (trim($value) === '' || $value === 'None')) {
                $value = null;
            }
            $data[$targetCol] = $this->cast($value, $type, "{$spec['source']}.{$sourceCol}", $warnings) ?? $default;
        }

        return $data;
    }

    protected function cast(mixed $value, string $type, string $column, array &$warnings): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'user') {
            $type = 'ref:utilisateurs';
        }
        if (str_starts_with($type, 'ref:')) {
            $table = substr($type, 4);
            $id = $this->idMaps[$table][$value] ?? null;
            if ($id === null) {
                $warnings[] = "référence orpheline mise à vide : {$column} ➜ {$table}";
            }

            return $id;
        }

        return match ($type) {
            'str' => Str::limit(trim((string) $value), 250, ''),
            'text', 'json' => (string) $value,
            'int' => is_numeric($value) ? (int) $value : $this->warn($warnings, "nombre illisible : {$column}"),
            'bool' => (bool) (int) $value,
            'dec' => is_numeric($value) ? round((float) $value, 2) : $this->warn($warnings, "montant illisible : {$column}"),
            'rate' => is_numeric($value) ? round((float) $value, 4) : $this->warn($warnings, "taux illisible : {$column}"),
            'date' => $this->parseDate((string) $value, false) ?? $this->warn($warnings, "date illisible mise à vide : {$column}"),
            'datetime' => $this->parseDate((string) $value, true) ?? $this->warn($warnings, "date illisible mise à vide : {$column}"),
            default => throw new RuntimeException("Type inconnu : {$type}"),
        };
    }

    protected function parseDate(string $value, bool $withTime): ?string
    {
        $value = trim($value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y', 'd/m/Y H:i'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($date !== false && $date->format($format) === $value && $date->year > 1900 && $date->year < 2200) {
                return $withTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d');
            }
        }

        return null;
    }

    protected function warn(array &$warnings, string $message): null
    {
        $warnings[] = $message;

        return null;
    }

    /**
     * Rattache une ligne Flask à une ligne Laravel déjà présente sans legacy_id
     * (ex. le compte « admin » créé par le seeder, ou une configuration semée).
     */
    protected function matchExisting(array $spec, array $data): ?int
    {
        $natural = match ($spec['target']) {
            'users' => ['username'],
            'rubrics' => ['module'],
            'calc_parameters' => ['module', 'code'],
            'app_modules' => ['code'],
            'issue_configs' => ['year'],
            default => null,
        };
        if (! $natural) {
            return null;
        }
        $query = DB::table($spec['target']);
        foreach ($natural as $col) {
            if (! isset($data[$col])) {
                return null;
            }
            $query->where($col, $data[$col]);
        }
        if ($spec['target'] !== 'issue_configs') {
            $query->whereNull('legacy_id');
        }

        return $query->value('id');
    }

    // ── Cas particuliers ─────────────────────────────────────────

    protected function prepareUser(array $row, array $data, array &$warnings): array
    {
        $data['username'] = Str::lower($data['username'] ?: 'utilisateur'.$row['id']);
        $data['name'] = trim(($row['nom'] ?? '').' '.($row['prenom'] ?? '')) ?: $data['username'];
        if ($data['email'] && DB::table('users')->where('email', $data['email'])->where(
            fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', '!=', $row['id'])
        )->where('username', '!=', $data['username'])->exists()) {
            $warnings[] = 'e-mail déjà utilisé par un autre compte : laissé vide';
            $data['email'] = null;
        }
        // Les mots de passe Flask (werkzeug scrypt) ne sont pas vérifiables en PHP :
        // mot de passe aléatoire + changement obligatoire ; l'administrateur le réinitialise.
        $data['password'] = Hash::make(Str::random(40));
        $data['must_change_password'] = true;
        $warnings[] = 'mot de passe Flask non transférable : à réinitialiser par l’administrateur';

        return $data;
    }

    protected function assignRole(int $userId, array $row, array &$warnings): void
    {
        $flaskRole = $row['role_id'] ? $this->legacy->table('roles')->where('id', $row['role_id'])->value('nom') : null;
        $role = LegacyTableMap::ROLE_MAP[$flaskRole] ?? null;
        $user = User::find($userId);
        if ($role) {
            $user->syncRoles([$role]);
        } else {
            $warnings[] = 'rôle Flask sans équivalent ('.($flaskRole ?? 'aucun').') : compte sans rôle';
        }
        if (! empty($row['custom_permissions'])) {
            $warnings[] = 'droits sur mesure Flask à reconfigurer';
        }
    }

    protected function prepareDeclaration(array $row, array $data, array &$warnings): array
    {
        [$table, $alias] = LegacyTableMap::TAXABLE_BY_MODULE[$row['module']] ?? [null, null];
        $data['taxable_type'] = $alias;
        $data['taxable_id'] = $table && $row['reference_id'] ? ($this->idMaps[$table][$row['reference_id']] ?? null) : null;
        if ($table && $row['reference_id'] && ! $data['taxable_id']) {
            $warnings[] = "objet taxé introuvable (module {$row['module']}) : rattachement vide";
        }
        if (! $alias) {
            $warnings[] = 'module de déclaration inconnu : '.$row['module'];
        }
        if ($data['status'] === 'annule') {
            $data['cancelled_at'] = $data['created_at'] ?? now();
            $data['cancellation_reason'] = 'Annulée dans l’application Flask (import)';
        }

        return $data;
    }

    /** Location / Souk : mois réglés stockés en texte dans notes (« 2025-01, 2025-02 | note »). */
    protected function syncPeriods(int $declarationId, array $row): void
    {
        if (! in_array($row['module'], ['LOCATION_LOCAUX', 'AFFERMAGE_SOUKS'], true) || empty($row['notes'])) {
            return;
        }
        $months = collect(explode(',', explode('|', $row['notes'])[0]))
            ->map(fn ($m) => trim($m))
            ->filter(fn ($m) => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m))
            ->unique()->values();

        DB::table('tax_declaration_periods')->where('tax_declaration_id', $declarationId)->delete();
        DB::table('tax_declaration_periods')->insert($months->map(fn ($m) => [
            'tax_declaration_id' => $declarationId, 'period' => $m, 'created_at' => now(), 'updated_at' => now(),
        ])->all());
    }

    protected function importUserLogs(array $result): array
    {
        $done = DB::table('activity_log')->where('log_name', 'flask')->pluck('subject_id')->flip()->all();
        foreach ($this->legacy->table('user_logs')->orderBy('id')->cursor() as $row) {
            $result['source_count']++;
            if (isset($done[$row->id])) {
                $result['updated']++;

                continue;
            }
            $causer = $this->idMaps['utilisateurs'][$row->user_id] ?? null;
            $when = $this->parseDate((string) $row->date_action, true) ?? now()->toDateTimeString();
            DB::table('activity_log')->insert([
                'log_name' => 'flask',
                'description' => trim($row->action.($row->description ? ' — '.$row->description : '')),
                'subject_type' => 'flask_user_log',
                'subject_id' => $row->id,
                'causer_type' => $causer ? 'App\\Models\\User' : null,
                'causer_id' => $causer,
                'properties' => json_encode(['ip' => $row->ip_adresse, 'user_agent' => $row->user_agent], JSON_UNESCAPED_UNICODE),
                'created_at' => $when,
                'updated_at' => $when,
            ]);
            $result['inserted']++;
        }
        $imported = DB::table('activity_log')->where('log_name', 'flask')->count();
        $result['gap'] = $result['source_count'] - $imported;

        return $result;
    }

    // ── Contrôles ────────────────────────────────────────────────

    protected function compareSums(array $spec, string $source, string $target, string $targetKey): array
    {
        $sums = [];
        foreach ($spec['sums'] ?? [] as $sourceCol => $targetCol) {
            $src = round((float) collect($this->legacy->table($source)->pluck($sourceCol))
                ->sum(fn ($v) => is_numeric($v) ? round((float) $v, $spec['columns'][$targetCol][1] === 'rate' ? 4 : 2) : 0), 2);
            $dst = round((float) DB::table($target)->whereIn($targetKey, array_keys($this->idMaps[$source]))->sum($targetCol), 2);
            $sums[] = ['column' => $sourceCol, 'source' => $src, 'target' => $dst, 'diff' => round($dst - $src, 2), 'ok' => abs($dst - $src) < 0.005];
        }

        return $sums;
    }

    protected function summarizeWarnings(array $warnings): array
    {
        return collect($warnings)->countBy()->map(fn ($n, $msg) => $n > 1 ? "{$msg} (×{$n})" : $msg)->values()->all();
    }

    protected function count(string $table): int
    {
        return (int) $this->legacy->table($table)->count();
    }

    protected function cleanMessage(Throwable $e): string
    {
        return preg_replace('/\s*\(Connection:.*$/s', '', $e->getMessage());
    }

    protected function assertPlainSqlite(): void
    {
        $header = file_get_contents($this->path, false, null, 0, 16);
        if ($header !== "SQLite format 3\0") {
            throw new RuntimeException(
                'Ce fichier n’est pas une base SQLite en clair (base Flask chiffrée ?). '
                .'Exportez-la d’abord avec tools/export_sqlite_plain.py.'
            );
        }
    }
}
