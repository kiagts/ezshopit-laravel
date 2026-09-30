<?php

namespace App\Http\Controllers;

use App\Enums\CommerceDocumentType;
use App\Enums\RefundStatus;
use App\Models\CommerceDocument;
use App\Models\Order;
use App\Models\Refund;
use App\Models\VendorOrder;
use App\Policies\CommerceDocumentPolicy;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;

class CommerceDocumentController extends Controller
{
    public function __construct(private DocumentService $documents, private DocumentRenderer $renderer, private CommerceDocumentPolicy $policy) {}

    public function invoice(Request $request, Order $order)
    {
        abort_unless($this->policy->order($request->user(), $order), 403);

        return $this->download($this->documents->issue(CommerceDocumentType::OrderInvoice, $order));
    }

    public function print(Request $request, Order $order)
    {
        abort_unless($this->policy->order($request->user(), $order), 403);

        return response($this->renderer->html($this->documents->issue(CommerceDocumentType::OrderSummary, $order)))->withHeaders(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'"]);
    }

    public function seller(Request $request, VendorOrder $vendorOrder)
    {
        abort_unless($this->policy->seller($request->user(), $vendorOrder), 403);

        return $this->download($this->documents->issue(CommerceDocumentType::VendorOrder, $vendorOrder));
    }

    public function refund(Request $request, Refund $refund)
    {
        abort_unless($this->policy->refund($request->user(), $refund), 403);

        return $this->download($refund->status === RefundStatus::Succeeded ? $this->documents->issue(CommerceDocumentType::CreditNote, $refund) : $this->documents->pendingStatement($refund));
    }

    private function download(CommerceDocument $document)
    {
        return response($this->renderer->pdf($document))->withHeaders(['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]/', '-', $document->document_number).'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
