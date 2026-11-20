<?php

namespace App\Livewire\Regie;

use Livewire\Attributes\Title;

#[Title('Vérification de caisse')]
class PvVerification extends ProcesVerbal
{
    public const ROUTE = 'regie.pv-verification';

    public const TYPE = 'verification';
}
