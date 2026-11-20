<?php

namespace App\Http\Controllers\Declarations;

use App\Domain\Declarations\Models\AssessmentDeclaration;
use App\Domain\Declarations\Services\DeclarationService;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Support\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Formulaires officiels des déclarations d'assiette : prérempli (déclaration enregistrée) ou vierge. */
class PrintController extends Controller
{
    public function declaration(AssessmentDeclaration $declaration): Response
    {
        $declaration->load(['taxpayer', 'declarable', 'agent']);

        return Pdf::render('print.declarations.formulaire', [
            'd' => $declaration, 'module' => $declaration->module, 'type' => $declaration->type,
            't' => DeclarationService::TYPES[$declaration->module][$declaration->type], 'm' => DeclarationService::MODULES[$declaration->module],
            'taxpayer' => $declaration->taxpayer, 'object' => $declaration->declarable, 'commune' => Municipality::first(),
        ], "Declaration_{$declaration->number}.pdf");
    }

    public function blank(string $module, string $type): Response
    {
        abort_unless(isset(DeclarationService::TYPES[$module][$type]), 404);

        return Pdf::render('print.declarations.formulaire', [
            'd' => null, 'module' => $module, 'type' => $type, 't' => DeclarationService::TYPES[$module][$type], 'm' => DeclarationService::MODULES[$module],
            'taxpayer' => null, 'object' => null, 'commune' => Municipality::first(),
        ], "Formulaire_{$module}_{$type}.pdf");
    }
}
