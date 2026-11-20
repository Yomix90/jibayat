<?php

namespace App\Domain\Regie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Boîte d'archive (rangement des pièces imprimées). */
class ArchiveBox extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PrintedDocument::class);
    }
}
