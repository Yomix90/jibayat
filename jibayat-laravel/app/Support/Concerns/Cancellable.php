<?php

namespace App\Support\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Pièces officielles immuables (CLAUDE.md §2.5) : jamais supprimées, seulement annulées
 * avec un motif, un auteur et une date ; elles apparaissent ensuite dans « Pièces annulées ».
 */
trait Cancellable
{
    public static function bootCancellable(): void
    {
        static::deleting(function () {
            throw new LogicException('Une pièce officielle ne se supprime pas : utilisez cancel() avec un motif.');
        });
    }

    public function cancel(User $by, string $reason): static
    {
        if ($this->isCancelled()) {
            throw new LogicException('Cette pièce est déjà annulée.');
        }
        if (trim($reason) === '') {
            throw new LogicException("Le motif d'annulation est obligatoire.");
        }

        $this->forceFill([
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'cancellation_reason' => $reason,
        ]);
        if (array_key_exists('status', $this->getAttributes())) {
            $this->status = 'annule';
        }
        $this->save();

        activity('pieces')->causedBy($by)->performedOn($this)
            ->withProperties(['motif' => $reason])
            ->log('Annulation');

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }
}
