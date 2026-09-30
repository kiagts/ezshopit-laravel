# Prompt 50C — Refund Financial Orchestration

## Prompt 50C Summary

Extends the existing refund domain with historical component calculation, original-funding destinations, atomic internal restoration, pending external processing, seller compensation entries and inspected-return completion. Existing uncommitted work is preserved. No commit, reset, discard, database reset, or new payment gateway was performed.

REFUND FINANCIAL ORCHESTRATION: COMPLETE. All final automated checks pass; successful internal restoration and pending external obligations remain distinct.

## Existing Financial Architecture Reused

Audited Refund/RefundAllocation and their original migrations; CreateRefund/ProcessRefund/MarkRefundSucceeded/MarkRefundFailed/RecalculatePaymentRefundStatus; Payment/PaymentAttempt; Order/OrderItem/VendorOrder and promotion, tax, shipping and tender snapshots; ReturnRequest/ReturnItem/ReturnWorkflowService; GiftCard/GiftCardTransaction/GiftCardService; Wallet/WalletTransaction/WalletService; commission recognition/calculation/adjustment actions; vendor sale/refund/commission ledger actions, VendorEarningsService and payout balance/actions; CancelOrder and inventory inspection/restoration. Read docs/architecture.md and docs/prompt-50b-report.md.

Key findings: existing RefundAllocation describes commerce attribution, so payment destinations use a linked table rather than replacing it. Payment.amount is the external remainder, not the total order funding. Existing item discount snapshots are usable; older orders lack item tax. Existing signed vendor ledger balances support post-payout liabilities. Legacy payment adapters remain isolated from new pending-provider orchestration.

## Files Created

- app/Enums/RefundFundingSource.php, RefundFundingStatus.php
- app/Models/RefundFundingAllocation.php
- app/Services/Refunds/RefundCalculator.php, RefundService.php, ExternalRefundGateway.php, PendingExternalRefundGateway.php
- app/Http/Controllers/Admin/ReturnRefundController.php
- database/migrations/2026_09_16_000800_orchestrate_refund_funding.php
- database/seeders/RefundWorkflowSeeder.php
- resources/views/admin/refunds/return.blade.php
- resources/views/components/refund-breakdown.blade.php
- tests/Feature/Refunds/RefundOrchestrationTest.php
- docs/prompt-50c-report.md

Paths in this report are relative to C:/laragon/www/multi-vendor.

## Files Modified

- app/Enums/RefundStatus.php
- app/Models/Refund.php, RefundAllocation.php, OrderItem.php, ReturnRequest.php
- app/Services/Refunds/RefundFinancials.php
- app/Services/GiftCardService.php
- app/Services/Returns/ReturnWorkflowService.php
- app/Services/Cart/EvaluateCart.php and app/Actions/Orders/PlaceOrder.php (retain line tax snapshots)
- app/Actions/Refunds/CreateRefund.php, ProcessRefund.php, MarkRefundSucceeded.php, MarkRefundFailed.php, RecalculatePaymentRefundStatus.php
- app/Actions/Commissions/ApplyRefundCommissionAdjustment.php, RecognizeVendorOrderCommission.php
- app/Actions/VendorLedger/PostVendorRefundDebit.php
- app/Providers/AppServiceProvider.php
- app/Http/Controllers/Admin/RefundController.php, Admin/OrderController.php, Account/OrderController.php, Returns/ReturnController.php
- app/Http/Requests/Admin/CreateRefundRequest.php
- database/seeders/DatabaseSeeder.php; routes/web.php
- resources/views/admin/refunds/index.blade.php, show.blade.php
- resources/views/account/orders/_details.blade.php, vendor/orders/show.blade.php, returns/show.blade.php
- tests/Feature/Payments/RefundFoundationTest.php, PaymentFoundationTest.php; tests/Feature/VendorLedger/VendorLedgerTest.php
- docs/architecture.md

## Database Changes

One additive migration adds optional return/vendor-order links, idempotency key/hash, processed actor and economics timestamp to existing refunds; historical components/return-item link to commerce allocations; nullable tax_total to order_items; and refund_funding_allocations with source, currency, amount, status, unique keys and optional PaymentAttempt/transaction/provider references. Original rows remain intact.

Actual application connection: **mysql**, database **ezshop**. Automated feature tests use **SQLite :memory:** from phpunit.xml. Existing PHP Xdebug log-path warnings are environment noise.

## Refund Domain

An optional ReturnRequest reference supports inspected-return and independent admin refunds. Existing Refund number and enum are reused; succeeded means completed internal restoration. partially_processed explicitly preserves pending external obligations. Customer ownership and currency derive from the original Order, avoiding duplicate customer/currency fields.

## Refund Calculation

For each requested item quantity:

- Remaining merchandise = historical customerLineTotal minus prior protected merchandise allocations.
- Remaining tax = historical line tax minus prior protected tax allocations.
- Remaining discount = historical allocated discount minus prior protected discount allocations.
- Remaining seller basis = historical sellerLineTotal minus prior protected seller allocations.
- Partial component = floor(remaining component × requested quantity / remaining quantity).
- Final quantity receives the remaining component exactly.
- Total refund = merchandise + tax + explicitly approved shipping + explicit marketplace adjustment.

Adjustment has zero seller basis; shipping has its historical seller attribution. No financial float arithmetic or FX conversion occurs in the new calculator.

## Historical Price Handling

Uses OrderItem line_total/customerLineTotal and sellerLineTotal. Changing ProductVariant price does not change refund amounts. Current product availability does not substitute a new item.

## Historical Discount Handling

Uses item discount_total, net_line_total and vendor_discount_total. Marketplace-funded discount remains separate from seller basis. Current promotion rules are not consulted.

## Historical Tax Handling

New orders retain each line's tax from the existing checkout evaluation. Older orders with null item tax deterministically allocate remaining historical Order.tax_total over historical net merchandise, sorted by item ID, leaving the last line the exact remainder. Existing known item tax is retained. Current TaxEngine/TaxRule are never called for refund calculation. Historical orders are not rewritten. Missing historical per-class rates cannot be reconstructed; proportional compatibility allocation is documented.

## Rounding Policy

Integer floor of remaining proportions, final-quantity reconciliation, independently for merchandise, discount, tax and seller basis. Existing Brick BigInteger prevents multiplication overflow. Commission uses cumulative original-rate calculation and a final original-commission cap.

## Shipping Refund Policy

Zero by default. Explicit admin shipping is structurally and operationally supported by the existing manual refund form/service, capped at the seller's remaining historical shipping total.

## Partial Quantity Refunds

Supported and tested across three partial quantities with non-divisible tax/discount totals. Return refunds also require inspected refundable quantity and same seller/order ownership.

## Refund Caps

Caps apply to item quantity and economic components, total order value, return quantity, historical shipping, original Gift Card, original wallet and external funding. Actual original internal debits must match tender snapshots. Prior direct source credits and outstanding instructions reduce remaining source capacity. Failed/cancelled reservations release capacity only before accounting effects have posted.

## Refund Allocation Policy

**Gift Card → wallet → external payment.** For original funding 3,000/2,000/5,000, a 4,000 refund allocates 3,000 to Gift Card and 1,000 to wallet. Commerce and funding allocations both reconcile to Refund.amount.

## Gift Card Integration

GiftCardService creates immutable, order/refund-referenced credits to the original card, capped at that card's actual order funding. Idempotent keys reject changed instructions. Disabled/expired cards can receive accounting refunds without becoming usable. Codes are not displayed.

## Wallet Integration

WalletService creates an order/refund-referenced reversal in the original customer's original-currency wallet. Existing balance/cap/immutable-ledger rules remain authoritative. No currency substitution, FX or direct transaction creation by controllers.

## External Refund Boundary

ExternalRefundGateway is bound to PendingExternalRefundGateway. It reserves pending_provider instructions; it does not call a payment provider. External Payment and PaymentAttempt states remain unchanged. New orchestrated refunds cannot use legacy success handling while destinations remain pending.

**NO EXTERNAL PAYMENT REFUND IS MARKED SUCCESSFUL WITHOUT A PROVIDER.**

Legacy provider/webhook regression tests remain for historical refunds. Actual external execution is intentionally unavailable in this scope.

## Refund Atomicity

Processing, internal credits, destination statuses, seller debits, commission adjustments/credits and internal completion occur in one transaction. Injected failure rolls these back. Creation separately reserves a pending instruction that Admin can retry; a failed processing attempt retains that reservation.

## Refund Idempotency

Unique request key plus instruction hash; unique destination key and source tuple; Gift Card/wallet transaction keys; existing seller-ledger source keys; existing one-adjustment-per-allocation logic. Duplicate processing returns the existing completed/pending-provider state. A stale failure callback rechecks economics_posted_at under lock and cannot release already-processed capacity.

## Refund Concurrency Protection

Order/refund/payment/item/source row locks and unique constraints serialize capacity and processing. Tests cover repeat calls, reserved capacity, original-source exhaustion, rollback and stale failure state. **Simultaneous MySQL contention/stress testing was not performed.** SQLite feature tests do not prove MySQL row-lock behavior; local MySQL migration/seeding were verified separately.

## Commission Reversal

Uses original commission rate and original basis. Cumulative rounding is capped at original commission; repeated allocations cannot reverse twice. Original snapshot remains unchanged. Pre-recognition refunds reduce the eventual recognized basis under the existing recognition policy. The debit captures commission identity under locks so later reconciliation cannot reverse an amount already netted before recognition; a dedicated regression covers this sequence.

## Vendor Ledger Adjustment

Existing seller refund debits and commission credits are compensating entries. Tax is excluded from seller merchandise basis. Vendor A refunds do not change Vendor B. Posting errors propagate through the orchestration transaction, including errors that the legacy adapter action logs.

## Pre-Payout Treatment

Approved refund liability reduces the signed seller ledger immediately, even when the external destination awaits a provider. Commission credit offsets the attributable commission. This keeps payout eligibility aware of the obligation.

## Post-Payout Treatment

Paid payout records and original sale entries remain unchanged. Refund debit and commission credit create a subsequent seller liability.

## Negative Vendor Liability

Signed balances may become negative. The accounting balance is not clamped; only existing requestable-payout capacity uses a zero floor. Tests preserve raw original commission/sale/payout records and assert a negative balance.

## Return Integration

Only ReturnWorkflowService completes a return, after all inspected refundable quantities have successful linked refunds and no linked financial obligation remains pending. Completion appends return history. External pending leaves refund_pending. RefundService never calls inventory restoration.

## Customer UI

Own order/return views show safe merchandise/tax/shipping/adjustment totals, original currency and Gift Card/wallet/external status. Pending external amounts are described as not yet returned. No privileged processing controls or full Gift Card codes.

## Vendor UI

Owned return/order views show seller-attributed refund effects and commission/ledger impact. No unrelated seller or customer funding details are exposed. Post-payout accounting remains visible through the existing signed earnings ledger.

## Admin UI

Existing filtered list/detail reused, with return/vendor references, original currency, funding destinations, seller debit/commission credit, status/timestamps and reserved-refund retry. Inspected-return preview uses RefundCalculator. Manual item quantities use historical values; explicit shipping/adjustment amounts remain supported. Fully internally funded orders expose the form. Role middleware, service authorization, CSRF and server-side ownership/cap checks protect mutations.

## Demo Data

Four named DEMO-REFUND examples: pending 10,000; completed internal 10,000; completed partial 4,950; split 10,000 with 5,000 external pending. They are explicitly synthetic financial demo orders with original funding snapshots, service-generated stored-value debits/credits, and no fabricated delivery or restock. Split funding uses a named original demo cash receipt, never a fake external refund success. A post-payout liability is covered by tests; no historical payout is rewritten to produce demo data.

Repeated local seeding preserved 10 total refunds, 9 funding allocations, 13 wallet transactions, 11 Gift Card transactions and 48 inventory movements. Existing demo return 6 already has exhausted refund quantity; it is reported and preserved. Stable demo order/request identifiers prevent duplicates. Dedicated seeder tests also verify all four states and repeated-run counts.

## Dedicated Refund Tests

PASS — 17 tests, 77 assertions, zero failures/errors; 6.73 seconds. Log: storage/logs/prompt50c-refund-verified.log.

## Returns Regression

PASS — Returns + Gift Card + Wallet: 54 tests, 277 assertions, zero failures/errors; 53.56 seconds. Log: storage/logs/prompt50c-related-tests.log.

## Gift Card Regression

Covered by the related-domain run and full final marketplace run. Exact related totals are above.

## Wallet Regression

Covered by the related-domain run and full final marketplace run. Exact related totals are above.

## Full Marketplace Regression

PASS — 433 tests, 3,008 assertions; zero failures, errors or skipped tests; 145.50 seconds. XDEBUG_MODE=off was inherited by the test process. Log: storage/logs/prompt50c-full-verified.log.

The first full run had 429 passes and two old fixed-count seeder assertions failing after the four additive examples. Those assertions now separately verify the original seed population and new financial examples. Other earlier failures were invalid fixture columns/currency setup and the intentionally changed external-pending expectation; all were corrected and rerun.

## Migration

PASS — additive migration applied; final migrate --force reported Nothing to migrate.

## Seeder

PASS — repeated db:seed --force succeeds, original history retained, stable identifiers and counts verified. Exhausted existing return is explicitly skipped.

## Routes

PASS — complete route:list captured in storage/logs/prompt50c-routes.log. Refund routes:

- POST admin/orders/{order}/refunds
- GET admin/refunds
- GET admin/refunds/{refund}
- POST admin/refunds/{refund}/process
- GET admin/returns/{return}/refund
- POST admin/returns/{return}/refund

All are in the existing auth/admin route group.

## Optimize

PASS — optimize:clear completed.

## Production Build

PASS — Vite 7.3.6 built 59 modules. Sandbox attempt failed with esbuild spawn EPERM; approved execution outside sandbox succeeded in 10.56 seconds. No application change was made to work around that environment restriction.

## 60-Item Acceptance Report

| # | Acceptance criterion | Result |
|---|---|---|
| 1 | Refund uses historical OrderItem price. | PASS |
| 2 | Current Product price does not affect Refund. | PASS |
| 3 | Historical discount is respected. | PASS |
| 4 | Current Promotion rules do not affect Refund. | PASS |
| 5 | Historical tax is respected. | PASS |
| 6 | Current TaxRule does not affect Refund. | PASS |
| 7 | Partial quantity Refund works. | PASS |
| 8 | Discount rounding reconciles. | PASS |
| 9 | Tax rounding reconciles. | PASS |
| 10 | Shipping Refund defaults to zero. | PASS |
| 11 | Explicit shipping Refund is structurally supported. | PASS |
| 12 | Refund currency equals Order currency. | PASS |
| 13 | Storefront currency change does not affect Refund. | PASS |
| 14 | Return-based Refund respects approved quantity. | PASS |
| 15 | Cumulative item Refund is capped. | PASS |
| 16 | Cumulative Order Refund is capped. | PASS |
| 17 | Duplicate Refund processing is prevented. | PASS |
| 18 | Refund processing is transactional. | PASS |
| 19 | Refund uses locking where required. | PASS |
| 20 | Gift Card Refund uses GiftCardService. | PASS |
| 21 | Gift Card Refund is idempotent. | PASS |
| 22 | Gift Card Refund respects original funding cap. | PASS |
| 23 | Expired Gift Card accounting Refund remains supported. | PASS |
| 24 | Disabled Gift Card accounting Refund remains supported. | PASS |
| 25 | Wallet Refund uses WalletService. | PASS |
| 26 | Wallet Refund is idempotent. | PASS |
| 27 | Wallet Refund uses correct currency. | PASS |
| 28 | Split-funding allocation is deterministic. | PASS |
| 29 | Gift Card allocation never exceeds original funding. | PASS |
| 30 | Wallet allocation never exceeds original funding. | PASS |
| 31 | External allocation never exceeds original funding. | PASS |
| 32 | Internal-only Refund can complete. | PASS |
| 33 | External allocation remains pending provider. | PASS |
| 34 | External money is not falsely marked refunded. | PASS |
| 35 | No payment provider is hard-coded. | PASS |
| 36 | External Refund boundary exists. | PASS |
| 37 | Partial commission reversal is correct. | PASS |
| 38 | Original commission remains immutable. | PASS |
| 39 | Commission cannot be reversed twice. | PASS |
| 40 | Commission reversal cannot exceed original commission. | PASS |
| 41 | Vendor ledger adjustment is created. | PASS |
| 42 | Original Vendor sale entry remains immutable. | PASS |
| 43 | Vendor ledger adjustment is idempotent. | PASS |
| 44 | Vendor A Refund does not affect Vendor B. | PASS |
| 45 | Pre-payout Refund reduces Vendor economics correctly. | PASS |
| 46 | Post-payout Refund does not rewrite historical Payout. | PASS |
| 47 | Post-payout seller liability is recorded. | PASS |
| 48 | Negative seller liability is not silently clamped. | PASS |
| 49 | Refund processing does not restock Inventory. | PASS |
| 50 | Return completion uses ReturnWorkflowService. | PASS |
| 51 | Customer sees safe Refund breakdown. | PASS |
| 52 | Vendor sees appropriate Refund financial impact. | PASS |
| 53 | Admin sees Refund allocations. | PASS |
| 54 | Customer cannot execute privileged Refund processing. | PASS |
| 55 | Vendor cannot directly mutate Refund ledgers. | PASS |
| 56 | Admin authorization is enforced. | PASS |
| 57 | Demo Refund data is financially consistent/repeatable. | PASS |
| 58 | Dedicated Refund suite passes. | PASS |
| 59 | Full marketplace regression passes. | PASS |
| 60 | Production build passes or exact environment limitation is reported. | PASS |

## Issues

No unresolved application failures were found in the final verification. Provider execution remains deliberately pending. Simultaneous MySQL contention and browser visual testing are not claimed; automated role-screen tests, SQLite financial tests and local MySQL migration/seeding are the verified evidence.

## Prompt 50C Status

REFUND FINANCIAL ORCHESTRATION: COMPLETE

## Prompt 50 Remaining Work

Prompt 50D — Dispute Operations & Final Returns/Refunds/Disputes QA. Not implemented in this pass. Prompt 51 notifications were not started.
