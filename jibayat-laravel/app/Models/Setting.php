<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Réglage applicatif clé/valeur (remplace config.json de Flask).
 */
class Setting extends Model
{
    use Journaled;

    protected $guarded = ['id'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('jibayat.settings');
    }

    /** @return array<string, string|null> */
    public static function all_(): array
    {
        try {
            return Cache::rememberForever('jibayat.settings', fn () => Schema::hasTable('settings')
                ? static::query()->pluck('value', 'key')->all()
                : []);
        } catch (Throwable) {
            return [];
        }
    }
}
