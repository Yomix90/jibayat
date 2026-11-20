<?php

namespace App\Domain\Declarations\Models;

use App\Models\Taxpayer;
use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Déclaration relative à l'assiette (pièce officielle : elle s'annule avec un motif, elle ne se supprime pas). */
class AssessmentDeclaration extends Model
{
    use Cancellable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_on' => 'date', 'filed_on' => 'date', 'deadline' => 'date', 'resume_on' => 'date', 'is_late' => 'boolean',
            'signed' => 'boolean', 'details' => 'array', 'amount' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function declarable(): MorphTo
    {
        return $this->morphTo();
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('cancelled_at');
    }
}
