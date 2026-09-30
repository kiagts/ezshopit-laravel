# Prompt 52 — Invoice & Order Documents

Verification date: 2026-09-25. Existing uncommitted work preserved. No commits, resets, database rebuilds, payment-provider integration, settlement module or Prompt 53 implementation.

## Prompt 52 Summary

Implemented authenticated customer invoices, printable order summaries, isolated seller documents and completed-refund credit notes. Added stable immutable records, concurrency-safe issuance, configured issuer/recipient snapshots, integer currency formatting, private server-side PDF downloads and role-specific UI links. External pending refunds receive a truthful processing statement.

## Existing Architecture Reused

Audited docs/architecture.md and docs/prompt-50-final-report.md, routes/web.php, composer.json/lock, config/filesystems.php, Order, OrderItem, VendorOrder, Payment, Refund, RefundAllocation, RefundFundingAllocation, Vendor, User, Currency, Money, SettingsService/SettingsRegistry, PlaceOrder, refund orchestration, notification locale/links, existing account/vendor/admin order/refund views, factories and DatabaseSeeder. No invoice/PDF foundation existed. The new document service references existing commerce; it is not a second financial engine.

## Files Created

- app/Enums/CommerceDocumentType.php
- app/Models/CommerceDocument.php; app/Models/Builders/CommerceDocumentBuilder.php
- app/Policies/CommerceDocumentPolicy.php
- app/Services/Documents/DocumentService.php, DocumentSnapshot.php, DocumentRenderer.php
- app/Http/Controllers/CommerceDocumentController.php
- config/documents.php
- database/migrations/2026_09_24_000100_create_commerce_documents.php
- database/seeders/CommerceDocumentSeeder.php
- resources/views/documents/commerce.blade.php
- lang/en/documents.php, lang/ar/documents.php, lang/ur/documents.php
- tests/Feature/Documents/CommerceDocumentTest.php
- docs/prompt-52-report.md

## Files Modified

composer.json/lock; .env.example; app/Support/Money.php; routes/web.php; database/seeders/DatabaseSeeder.php; resources/views/account/orders/show.blade.php and _details.blade.php; resources/views/vendor/orders/show.blade.php; resources/views/admin/orders/show.blade.php and admin/refunds/show.blade.php; docs/architecture.md. Earlier unrelated working-tree changes are preserved and are not attributed to Prompt 52.

## Database Changes

One additive commerce_documents table with source FKs, unique document_number and logical_key, type, customer/vendor association, issue time, locale, currency/precision and three JSON snapshots. Source FKs restrict deletion. Actual development driver/database: **MySQL / ezshop**. Tests use isolated SQLite :memory: as configured by phpunit.xml. No migrate:fresh/db:wipe was used.

## Document Types

order_invoice, order_summary, vendor_order, credit_note. An externally pending refund statement is explicitly non-formal and non-persisted; it is not inserted as a completed credit note.

## Document Persistence

A first authorized request issues the record; subsequent requests return the same identity and frozen content. Financially relevant display data is duplicated minimally because existing Order/OrderItem records are not application-enforced immutable. Complete models, provider secrets and financial internals are not stored. No update/delete UI exists; model hooks and builder block Eloquent update/delete. Privileged raw SQL remains outside application-level immutability enforcement.

## Document Numbering

INV/ORD/SEL/CRN + issue year + source ID padded to eight digits. Logical identity is type:source-id. Issuance transaction locks the parent Order FOR UPDATE, checks existing logical identity, then inserts under two database unique constraints; deadlocks retry up to three times. No MAX+1 or random per-download identity. MySQL two-process first-issuance verification returned **document ID 41 / ORD-2026-00000083** from both workers; exactly one persisted summary. Logs: storage/logs/prompt52-race-first.json and prompt52-race-second.json.

## Issuer Model

**Configurable; marketplace-issued by default.** Seller documents use their vendor issuer. Setting DOCUMENT_ISSUER=vendor supports single-vendor customer invoices; multi-vendor aggregate invoices require marketplace mode. Credit notes retain original invoice issuer. These are commercial records, with no unsupported statutory-invoice claim.

## Historical Snapshot Strategy

OrderItem supplies historical names, SKU, variant, quantities, prices, discounts and tax; Order/VendorOrder supply their recorded totals and shipping. Recipient uses Order shipping name/address. Issuer identity and displayed lines/status freeze on first issuance. Current catalog/rule/FX data is never used to recalculate. Legacy issuer/vendor identity was not saved at checkout, so it can only be frozen from current verified data at first issue, not reconstructed retroactively.

## Customer Invoice

Download invoice from account order details. Shows issuer, recipient, order/invoice references and dates, status/payment at issue, grouped lines, subtotal, discounts, shipping, tax, total and separate funding allocation. Customer ownership is checked before issuance.

## Multi-Vendor Invoice Handling

Single marketplace invoice groups lines by actual seller order and keeps one authoritative customer total. Vendor names and seller-order references distinguish each group. No commission or ledger fields are present.

## Vendor Order Document

Download from vendor order details; admin also has a seller-document link for each seller order. Only that vendor order, its items, customer shipping snapshot and recorded seller totals appear. No other vendor lines or customer wallet/gift-card funding is included. This is not a settlement/payout statement.

## Printable Order Summary

Own persistent ORD identity; same escaped Blade template and historical snapshots. Private HTML response with nosniff and CSP supports native browser printing without JavaScript.

## Credit Note / Refund Document

Succeeded refunds receive a separate immutable CRN identity referencing the original invoice, order and refund. Actual allocation quantities, merchandise, discount already included, tax, shipping, adjustment and total are shown. Distinct partial refunds create distinct credit notes. Currency mismatch is rejected, not converted.

## Pending External Refund Representation

Economically recognized partially_processed refunds render a STAT processing statement. It explicitly says external provider processing is pending and those funds have not been returned. No completed credit-note record is issued. As a current processing statement it may change when processing progresses. Unapproved/incomplete refunds cannot issue formal credit notes.

## Historical Price Handling

Stored integer unit_price/line_total and canonical OrderItem customerLineTotal(). Initial issuance test changes live catalog product/variant prices and captures queries to demonstrate no current pricing/rule lookup.

## Historical Discount Handling

Stored discount_total/net line amount. Credit-note discounts are recorded allocation discounts already included in net merchandise, not a second deduction. No current promotion evaluation.

## Historical Tax Handling

Recorded item/order/refund allocation tax only. Legacy seller totals can omit item tax; documents preserve that recorded total and explain item tax separately, instead of rewriting commerce. No TaxRule recomputation or legal compliance assertion.

## Historical Shipping Handling

Recorded shipping totals/method names and actual shipping refund allocations; no current shipping quote.

## Gift Card / Wallet Funding Representation

Tender amounts are shown separately from invoice discounts/total. Remaining external payment allocation is not described as proof of payment. Refund destination summaries include recorded source/amount/status where available. No gift-card code/hash, wallet balance or payment credential appears.

## Currency Handling

Historical ISO currency plus frozen minor-unit precision; Money::formatWithPrecision uses integer division/remainder and supports zero-/three-decimal currencies. No floating-point pricing, storefront-currency substitution or FX conversion. Unknown unconfigured currency precision fails clearly. Existing commerce formatter behavior is unchanged.

## PDF Architecture

**mPDF 8.2.7**, installed through Composer, renders A4 PDF bytes server-side. Blade and PDF share one document template. PDF files are generated on demand; private font cache is storage/app/private/document-renderer. Repeated table headings and page-number footers support long documents. No browser-only PDF substitute.

## PDF Security

No public files or guessable unauthenticated links. Authenticated downloads send application/pdf, attachment filename sanitized to safe characters, private/no-store and nosniff. All dynamic Blade values are escaped. No uploaded HTML, remote image fetch, product images, script or arbitrary template path. Optional missing branding images cannot break text branding.

## Authorization / IDOR

Central CommerceDocumentPolicy checks customer order ownership, actual VendorOrder vendor owner or admin role. Route groups additionally enforce exact account/vendor/admin role. Cross-customer, cross-vendor, guest and non-admin tests pass. Authorization precedes document issuance.

## Localization / RTL

Stable locale: original invoice for credit notes; otherwise recipient notification preference, active marketplace default Language, documents.fallback_locale captured from APP_LOCALE. Request language cannot influence it. English/Urdu/Arabic translation keys, font/script selection and RTL layouts work. Untranslated detailed explanatory copy intentionally falls back to English. Renderer restores the prior request locale.

## Business/Tax Configuration

Client must verify DOCUMENT_ISSUER, legal name, business address, tax/VAT registration where applicable, support contact, APP_URL and jurisdiction-specific invoice requirements. Optional fields hide when absent. Existing marketplace settings supply name/contact/address; vendor profile supplies vendor issuer details. No legal identity or registration was fabricated.

## Notification Integration

Existing transactional notification order/refund destinations lead to the screens containing document links. No new duplicate lifecycle event, notification spam or PDF email attachment was added.

## Demo Data

CommerceDocumentSeeder reuses bounded existing customer@example.com commerce, including multi-vendor and completed-refund examples. After expanded seeding: 12 invoices, 12 summaries, 13 seller documents and 3 credit notes (40 total). The concurrency check added one legitimate existing-order summary, bringing total to 41. Repeated seeding kept all counts unchanged. Orders 25, payments 25, refunds 11, wallet transactions 14, gift-card transactions 11, vendor-ledger entries 21 and payouts 3 remained unchanged.

## Dedicated Document Tests

**28 passed / 125 assertions / 0 failures / 0 errors / 0 skipped; 29.38 seconds.** Covers access/IDOR, actual PDFs, immutable snapshots/identities, duplicate constraints, historical prices/tax/discount/shipping/currency, split funding, privacy, seller isolation, distinct refunds, pending statements, locale/RTL/XSS and repeatable seeding. Evidence: storage/logs/prompt52-doc-tests.log and prompt52-documents.xml.

## Representative PDF Verification

| Document | Result | Verification |
|---|---|---|
| Standard invoice | PASS | Actual mPDF output, rendered PNG inspected |
| Multi-vendor invoice | PASS | Both seller groups and total inspected |
| Vendor Order document | PASS | Seller-only lines, recorded total and issuer inspected |
| Partial Refund credit note | PASS | Quantity, original reference, recorded net/tax/total inspected |
| RTL document | PASS | Arabic text, mirrored layout and numerical amounts inspected |
| Urdu sample | PASS | Urdu title/product, address and totals inspected |
| Long document | PASS | All six pages inspected; repeated headings, long names/SKUs and footers readable |

Samples/PNGs remain private under storage/app/private/document-qa. Test fixtures and unsaved QA models were used; layout checks did not manufacture financial records. First rendering identified footer/empty-heading/total-emphasis issues; final renderings were re-inspected after corrections.

## Regression Suites

Notifications + Returns + Refunds + Disputes rerun together: **100 passed / 436 assertions / 108.72 seconds**, no failures. Final full-suite class totals: Notifications 29/127; Returns 30/121; Refund orchestration 17/77; Disputes 24/111; Orders/fulfillment 15/196; Checkout 8/73; Payments (including legacy refund foundation) 38/460; Wallet 18/138; Gift Card 6/18; Commission 13/55; Vendor Ledger 10/46; Payouts 12/60; Vendor onboarding 19/115; Product management/architecture/media 39/289. All have zero failures/errors/skips. Counts are tests/assertions. Document tests additionally cover historical tax, currency precision/no-FX, locale stability and RTL; the full suite contains no separately named Tax/Currency/Localization test class. Historical Prompt 51 baseline is not used as current verification.

## Full Marketplace Regression

**508 passed / 3,339 assertions / 0 failures / 0 errors / 0 skipped. Artisan duration: 828.80 seconds; JUnit test time: 822.410092 seconds.** Command: php artisan test --compact --log-junit storage/logs/prompt52-full.xml. Evidence: storage/logs/prompt52-full-tests.log and prompt52-full.xml. The run was slow but completed successfully; no historical baseline is substituted.

## Migration

PASS — php artisan migrate --force applied only the new additive migration on MySQL ezshop.

## Repeated Seeder

PASS — php artisan db:seed --force repeated; post-seed snapshot comparison shows no duplicate documents or financial rows. Corrected an ambiguous refunds.status selector found during verification. Existing exhausted demo return is preserved by its existing seeder guard.

## Routes

PASS — full route:list plus all eight document routes inspected:

| Name | GET route | Role |
|---|---|---|
| account.documents.invoice | /account/orders/{order:order_number}/invoice | customer |
| account.documents.print | /account/orders/{order:order_number}/print | customer |
| account.documents.refund | /account/refunds/{refund:refund_number}/document | customer |
| vendor.documents.seller | /vendor/seller-orders/{vendorOrder:vendor_order_number}/document | vendor |
| admin.documents.invoice | /admin/orders/{order:order_number}/invoice | admin |
| admin.documents.print | /admin/orders/{order:order_number}/print | admin |
| admin.documents.refund | /admin/refunds/{refund:refund_number}/document | admin |
| admin.documents.seller | /admin/seller-orders/{vendorOrder:vendor_order_number}/document | admin |

## Optimize

PASS — php artisan optimize:clear.

## Production Build

PASS — npm.cmd run build: Vite 7.3.6, 59 modules, 15.99 seconds. Sandbox attempt hit known esbuild spawn EPERM; elevated retry passed. Composer's large PDF package download initially timed out; installation passed with a longer download timeout. Existing Xdebug log-path warning is environment noise and no application workaround was added.

## Known Limitations

- Legal issuer/jurisdiction requirements and verified client business details must be supplied before production; no statutory compliance claim.
- Earlier checkout data lacks historical issuer identity and billing address: first issue freezes current issuer and existing historical shipping recipient. It does not invent missing history.
- Legacy seller recorded totals may exclude customer item tax; the document states this explicitly and preserves the original values.
- Urdu/Arabic detailed explanatory copy has English fallback. Both RTL layouts were visually verified.
- MySQL two-process issuance and unique constraints were verified; this is not a claim of unrestricted financial concurrency stress certification.

## Prompt 52 Status

**INVOICE & ORDER DOCUMENTS: COMPLETE**

## 75-Item Acceptance Checklist

| # | Requirement | Result |
|---|---|---|
| 1 | Existing document infrastructure was audited. | PASS |
| 2 | No duplicate invoice architecture was created. | PASS |
| 3 | Document types are controlled. | PASS |
| 4 | Invoice number is unique. | PASS |
| 5 | Invoice number is immutable. | PASS |
| 6 | Re-download uses same invoice identity. | PASS |
| 7 | Concurrent duplicate logical issuance is protected. | PASS |
| 8 | Customer can download own invoice. | PASS |
| 9 | Customer cannot download another customer's invoice. | PASS |
| 10 | Invoice uses historical Product price. | PASS |
| 11 | Invoice uses historical Product/variant data where snapshotted. | PASS |
| 12 | Invoice uses historical tax. | PASS |
| 13 | Invoice uses historical discounts. | PASS |
| 14 | Invoice uses historical shipping. | PASS |
| 15 | Invoice uses historical currency. | PASS |
| 16 | Invoice performs no current FX conversion. | PASS |
| 17 | Invoice totals reconcile with Order. | PASS |
| 18 | Gift Card is represented as tender, not discount. | PASS |
| 19 | Wallet is represented as tender, not discount. | PASS |
| 20 | Split funding is represented accurately. | PASS |
| 21 | Multi-vendor Customer invoice works. | PASS |
| 22 | Vendor can access own VendorOrder document. | PASS |
| 23 | Vendor cannot access another Vendor's document. | PASS |
| 24 | Vendor document contains no other Vendor commerce. | PASS |
| 25 | Vendor document does not expose Wallet secrets. | PASS |
| 26 | Vendor document does not expose Gift Card secrets. | PASS |
| 27 | Customer invoice does not expose commission. | PASS |
| 28 | Admin document access works. | PASS |
| 29 | Non-Admin cannot access Admin document routes. | PASS |
| 30 | Printable Order summary works. | PASS |
| 31 | PDF generation works. | PASS |
| 32 | PDF response headers are correct. | PASS |
| 33 | Sensitive PDFs are not publicly guessable. | PASS |
| 34 | Download filenames are safe. | PASS |
| 35 | Credit note works for completed Refund. | PASS |
| 36 | Partial Refund credit note works. | PASS |
| 37 | Multiple Refunds produce separate immutable credit notes. | PASS |
| 38 | Credit note uses historical Refund merchandise amount. | PASS |
| 39 | Credit note uses historical Refund tax. | PASS |
| 40 | Credit note uses actual shipping Refund. | PASS |
| 41 | Pending external Refund is not falsely represented as completed. | PASS |
| 42 | Credit note links to original commerce. | PASS |
| 43 | Full Gift Card codes are never exposed. | PASS |
| 44 | Payment secrets are never exposed. | PASS |
| 45 | Issuer information comes from configuration/history. | PASS |
| 46 | Missing legal configuration is not fabricated. | PASS |
| 47 | Issuer snapshot is preserved after issuance. | PASS |
| 48 | Issuer address snapshot is preserved. | PASS |
| 49 | Tax registration snapshot is preserved where configured. | PASS |
| 50 | Customer/recipient history is preserved. | PASS |
| 51 | Vendor issuer architecture remains configurable. | PASS |
| 52 | Marketplace issuer architecture remains configurable. | PASS |
| 53 | Document locale architecture works. | PASS |
| 54 | RTL layout is structurally valid. | PASS |
| 55 | Missing logo does not break generation. | PASS |
| 56 | Missing optional legal fields do not break generation. | PASS |
| 57 | Document generation has no financial side effects. | PASS |
| 58 | Document access does not create duplicate financial records. | PASS |
| 59 | Repeated document download does not alter Order. | PASS |
| 60 | Demo standard invoice works. | PASS |
| 61 | Demo multi-vendor invoice works. | PASS |
| 62 | Demo Vendor document works. | PASS |
| 63 | Demo partial Refund credit note works. | PASS |
| 64 | Repeated seeding is safe. | PASS |
| 65 | Notification regression passes. | PASS |
| 66 | Return regression passes. | PASS |
| 67 | Refund regression passes. | PASS |
| 68 | Dispute regression passes. | PASS |
| 69 | Order/Checkout regression passes. | PASS |
| 70 | Tax/Currency regression passes. | PASS |
| 71 | Wallet/Gift Card regression passes. | PASS |
| 72 | Commission/Vendor Ledger/Payout regression passes. | PASS |
| 73 | Localization regression passes. | PASS |
| 74 | Full marketplace regression passes. | PASS |
| 75 | Production build and representative PDF generation pass. | PASS |


## Next Recommended Module

Prompt 53 — Seller Financial Operations. Not implemented in this task.
