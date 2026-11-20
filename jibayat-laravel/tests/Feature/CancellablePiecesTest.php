<?php

use App\Domain\Caisse\Models\TaxDeclaration;

function declaration(): TaxDeclaration
{
    return TaxDeclaration::create(['number' => 'DCL-T-1', 'module' => 'TNB', 'year' => 2026, 'total' => 500, 'status' => 'emis']);
}

test('an official piece cannot be deleted', function () {
    $decl = declaration();

    expect(fn () => $decl->delete())->toThrow(LogicException::class, 'ne se supprime pas');
    expect(TaxDeclaration::count())->toBe(1);
});

test('cancelling records reason, author and date, and is journaled', function () {
    $user = userWithRole('regisseur');
    $decl = declaration();

    $decl->cancel($user, 'Erreur de saisie de la superficie');

    $decl->refresh();
    expect($decl->status)->toBe('annule')
        ->and($decl->cancelled_by)->toBe($user->id)
        ->and($decl->cancellation_reason)->toBe('Erreur de saisie de la superficie')
        ->and($decl->cancelled_at)->not->toBeNull()
        ->and(TaxDeclaration::cancelled()->count())->toBe(1)
        ->and(TaxDeclaration::notCancelled()->count())->toBe(0);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'pieces', 'description' => 'Annulation', 'causer_id' => $user->id]);
});

test('a reason is required and a piece cannot be cancelled twice', function () {
    $user = userWithRole('regisseur');
    $decl = declaration();

    expect(fn () => $decl->cancel($user, '  '))->toThrow(LogicException::class, 'motif');
    $decl->cancel($user, 'Doublon');
    expect(fn () => $decl->cancel($user, 'Encore'))->toThrow(LogicException::class, 'déjà annulée');
});
