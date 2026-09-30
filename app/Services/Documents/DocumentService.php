<?php

namespace App\Services\Documents;

use App\Enums\CommerceDocumentType;
use App\Enums\RefundStatus;
use App\Models\CommerceDocument;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\Refund;
use App\Models\VendorOrder;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function __construct(private DocumentSnapshot $snapshot, private SettingsService $settings) {}

    public function issue(CommerceDocumentType $type, Order|VendorOrder|Refund $subject): CommerceDocument
    {
        if (($type === CommerceDocumentType::VendorOrder && ! $subject instanceof VendorOrder) || ($type === CommerceDocumentType::CreditNote && ! $subject instanceof Refund) || (in_array($type, [CommerceDocumentType::OrderInvoice, CommerceDocumentType::OrderSummary], true) && ! $subject instanceof Order)) {
            throw new \InvalidArgumentException('Document source does not match type.');
        }
        $order = $subject instanceof Order ? $subject : $subject->order;

        return DB::transaction(function () use ($type, $subject, $order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $key = $type->value.':'.$subject->id;
            if ($existing = CommerceDocument::where('logical_key', $key)->first()) {
                return $existing;
            }
            $subject = $subject instanceof Order ? $order : $subject->fresh();
            if ($subject instanceof Refund && $subject->status !== RefundStatus::Succeeded) {
                throw ValidationException::withMessages(['document' => 'A credit note requires a completed refund. Pending refunds have a statement only.']);
            }
            $seller = $subject instanceof VendorOrder ? $subject : null;
            $original = $subject instanceof Refund ? $this->issue(CommerceDocumentType::OrderInvoice, $order) : null;
            if ($original && $subject->currency !== $original->currency_code) {
                throw ValidationException::withMessages(['document' => 'Refund and original invoice currencies must match.']);
            }
            $issuerType = $original?->issuer_type ?? ($seller ? 'vendor' : config('documents.issuer', 'marketplace'));
            if (! in_array($issuerType, ['marketplace', 'vendor'], true)) {
                throw ValidationException::withMessages(['document' => 'Configure a supported document issuer.']);
            }
            if ($issuerType === 'vendor' && ! $seller && ! $original) {
                if ($order->vendorOrders()->count() !== 1) {
                    throw ValidationException::withMessages(['document' => 'An aggregate multi-vendor invoice requires marketplace issuer configuration.']);
                }
                $issuerVendor = $order->vendorOrders()->first()->vendor;
            } else {
                $issuerVendor = $seller?->vendor;
            }
            $currency = $original?->currency_code ?? ($subject instanceof Refund ? $subject->currency : $order->currency);
            $minor = $original?->minor_unit ?? Currency::where('code', $currency)->value('minor_unit');
            if ($minor === null) {
                if ($currency !== config('marketplace.currency.code', 'USD')) {
                    throw ValidationException::withMessages(['document' => 'Currency precision must be configured before issuing this document.']);
                }
                $minor = (int) config('marketplace.currency.minor_unit', 2);
            }
            // Request middleware changes app.locale; it must not select an invoice's language.
            $preferred = $seller ? $seller->vendor->user?->notification_locale : $order->user?->notification_locale;
            $locale = $original?->locale ?: ($preferred ?: (Language::where('is_active', true)->where('is_default', true)->value('code') ?: config('documents.fallback_locale', 'en')));
            if (! in_array($locale, ['en', 'ur', 'ar'], true)) {
                $locale = 'en';
            }
            $content = $subject instanceof Refund ? $this->snapshot->refund($subject, $original->document_number) : $this->snapshot->order($order, $seller);

            return CommerceDocument::create([
                'document_type' => $type,
                'document_number' => $type->prefix().'-'.now()->format('Y').'-'.str_pad((string) $subject->id, 8, '0', STR_PAD_LEFT),
                'logical_key' => $key,
                'order_id' => $order->id,
                'vendor_order_id' => $seller?->id,
                'refund_id' => $subject instanceof Refund ? $subject->id : null,
                'customer_id' => $order->user_id,
                'vendor_id' => $seller?->vendor_id,
                'currency_code' => $currency,
                'minor_unit' => $minor,
                'locale' => $locale,
                'issuer_type' => $original?->issuer_type ?? $issuerType,
                'issuer_snapshot' => $original?->issuer_snapshot ?? $this->issuer($issuerType, $issuerVendor),
                'recipient_snapshot' => $original?->recipient_snapshot ?? $this->snapshot->recipient($order),
                'content' => $content,
                'issued_at' => now(),
            ]);
        }, 3);
    }

    public function pendingStatement(Refund $refund): CommerceDocument
    {
        if (! $refund->economicsRecognized() || $refund->status !== RefundStatus::PartiallyProcessed) {
            throw ValidationException::withMessages(['document' => 'This refund has no approved statement.']);
        }
        $invoice = $this->issue(CommerceDocumentType::OrderInvoice, $refund->order);
        if ($refund->currency !== $invoice->currency_code) {
            throw ValidationException::withMessages(['document' => 'Refund and original invoice currencies must match.']);
        }

        return new CommerceDocument(['document_type' => CommerceDocumentType::CreditNote, 'document_number' => 'STAT-'.$refund->refund_number, 'currency_code' => $invoice->currency_code, 'minor_unit' => $invoice->minor_unit, 'locale' => $invoice->locale, 'issuer_type' => $invoice->issuer_type, 'issuer_snapshot' => $invoice->issuer_snapshot, 'recipient_snapshot' => $invoice->recipient_snapshot, 'issued_at' => now(), 'content' => $this->snapshot->refund($refund, $invoice->document_number) + ['pending_statement' => true]]);
    }

    private function issuer(string $type, $vendor): array
    {
        if ($type === 'vendor') {
            return array_filter(['name' => $vendor->business_name ?: $vendor->display_name, 'address' => implode(', ', array_filter([$vendor->address_line_1, $vendor->address_line_2, $vendor->city, $vendor->postal_code, $vendor->country])), 'tax_number' => $vendor->tax_number, 'website' => $vendor->website]);
        }

        return array_filter(['name' => config('documents.legal_name') ?: $this->settings->get('general.marketplace_name', config('app.name')), 'address' => config('documents.business_address') ?: $this->settings->get('contact.business_address'), 'tax_number' => config('documents.tax_number'), 'email' => $this->settings->get('contact.support_email'), 'website' => config('documents.website')]);
    }
}
