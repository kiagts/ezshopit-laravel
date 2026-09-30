<?php

namespace App\Services\Documents;

use App\Models\CommerceDocument;
use App\Support\Money;
use Mpdf\Mpdf;

class DocumentRenderer
{
    public function html(CommerceDocument $document): string
    {
        $previous = app()->getLocale();
        try {
            app()->setLocale($document->locale);

            return view('documents.commerce', ['document' => $document, 'content' => $document->content, 'money' => fn ($amount) => $amount === null ? '-' : Money::formatWithPrecision((int) $amount, $document->currency_code, $document->minor_unit)])->render();
        } finally {
            app()->setLocale($previous);
        }
    }

    public function pdf(CommerceDocument $document): string
    {
        $dir = storage_path('app/private/document-renderer');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdf = new Mpdf(['tempDir' => $dir, 'mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 18, 'autoScriptToLang' => true, 'autoLangToFont' => true, 'default_font' => 'dejavusans']);
        $pdf->SetTitle($document->document_number);
        $pdf->SetAuthor($document->issuer_snapshot['name']);
        if (in_array($document->locale, ['ar', 'ur'], true)) {
            $pdf->SetDirectionality('rtl');
        }
        $pdf->SetHTMLFooter('<div style="font-size:8pt;text-align:center">'.e($document->document_number).' | {PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML($this->html($document));

        return $pdf->Output('', 'S');
    }
}
