<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse les mots de passe trop courants (liste reprise de Flask : helpers.WEAK_PASSWORDS).
 */
class NotCommonPassword implements ValidationRule
{
    public const WEAK = [
        'admin123', 'password', 'azerty', 'azerty123', '123456', '12345678', 'qwerty',
        'admin', 'administrateur', 'jibayat', 'jibayat123', 'commune', 'commune123',
        'abc123', 'motdepasse', 'p@ssw0rd', 'test123', 'superadmin',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array(mb_strtolower((string) $value), self::WEAK, true)) {
            $fail('Ce mot de passe est trop courant et facile à deviner. Choisissez-en un plus robuste.');
        }
    }
}
