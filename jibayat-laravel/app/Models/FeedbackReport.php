<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Signalement ou suggestion d'un utilisateur (Flask : envoi vers une feuille externe ; ici gardé dans l'application). */
class FeedbackReport extends Model
{
    public const TYPES = ['probleme' => 'Problème', 'suggestion' => 'Suggestion', 'question' => 'Question'];

    public const STATUSES = ['nouveau' => 'Nouveau', 'en_cours' => 'En cours', 'traite' => 'Traité'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
