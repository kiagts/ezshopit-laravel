<?php

namespace App\Enums;

enum CommerceDocumentType: string
{
    case OrderInvoice = 'order_invoice';
    case OrderSummary = 'order_summary';
    case VendorOrder = 'vendor_order';
    case CreditNote = 'credit_note';

    public function prefix(): string
    {
        return match ($this) {
            self::OrderInvoice => 'INV',self::OrderSummary => 'ORD',self::VendorOrder => 'SEL',self::CreditNote => 'CRN'
        };
    }
}
