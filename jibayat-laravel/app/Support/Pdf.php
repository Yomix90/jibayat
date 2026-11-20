<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impressions officielles en PDF A4 avec mPDF (gère l'arabe et le sens droite-à-gauche ;
 * dompdf le gère mal). Les vues sont des Blade dédiées dans resources/views/print.
 */
class Pdf
{
    public static function render(string $view, array $data, string $filename, string $format = 'A4', bool $inline = true): Response
    {
        $tmp = storage_path('app/mpdf');
        File::ensureDirectoryExists($tmp);

        $mpdf = new Mpdf([
            'format' => $format,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 20, 'margin_footer' => 4,
            'tempDir' => $tmp,
        ]);
        $mpdf->SetTitle($filename);
        $mpdf->SetCreator('Jibayat');
        $mpdf->SetHTMLFooter(self::footer($view, $filename));
        $mpdf->WriteHTML(view($view, $data)->render());

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * Pied de page : n° du registre des documents imprimés et QR de contrôle (lu par Régie ➜ Scanner).
     * Si le registre est indisponible, le document s'imprime quand même avec le pied de page simple.
     */
    protected static function footer(string $view, string $filename): string
    {
        $stamp = 'Jibayat · '.now()->format('d/m/Y H:i');
        try {
            $doc = app(\App\Domain\Regie\Services\DocumentRegistry::class)->register($view, $filename);
        } catch (\Throwable $e) {
            report($e);

            return '<table width="100%" style="font-size:7pt;color:#555"><tr><td>'.$stamp.'</td><td align="right">Page {PAGENO} / {nbpg}</td></tr></table>';
        }
        $url = e(route('regie.scanner', ['code' => $doc->code()]));

        return '<table width="100%" style="font-size:7pt;color:#555"><tr>'
            .'<td width="14mm"><barcode code="'.$url.'" type="QR" size="0.45" error="M" disableborder="1" /></td>'
            .'<td>'.$stamp.'<br>Registre des documents n° <strong>'.e($doc->number).'</strong> · contrôle '.e($doc->token).'</td>'
            .'<td align="right">Page {PAGENO} / {nbpg}</td></tr></table>';
    }
}
