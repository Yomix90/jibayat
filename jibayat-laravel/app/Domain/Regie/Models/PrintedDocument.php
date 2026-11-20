<?php

namespace App\Domain\Regie\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registre des documents imprimés : chaque pièce reçoit un n° et un QR de contrôle. */
class PrintedDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_printed_at' => 'datetime', 'last_printed_at' => 'datetime', 'scanned_at' => 'datetime'];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'archive_box_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    /** Code lu par le scanner : « n°-jeton ». */
    public function code(): string
    {
        return $this->number.'-'.$this->token;
    }
}
