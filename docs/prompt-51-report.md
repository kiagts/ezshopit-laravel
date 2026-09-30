# Prompt 51 — Transactional Notifications

Verification completed: 2026-09-24. Existing work preserved; no resets, discarded changes, or commits. Prompt 52 is not implemented.

## Prompt 51 Summary

Extended the existing Laravel notification system with event-driven orchestration, operational lifecycle coverage, mandatory/optional preferences, recipient locale, branded RTL-capable email, durable dispatch snapshots and channel-level retry protection. Existing inboxes, bells, route names and notification classes were retained.

## Existing Architecture Reused

Audited docs/architecture.md and docs/prompt-50-final-report.md; the notification migration, MarketplaceNotification subclasses, MarketplaceNotifier, SendUniqueNotification, AdminRecipientResolver, User/Notifiable, role inbox/read controller and views, notification seeder/tests, mail/queue configuration, Actions and return/refund/dispute/wallet/gift-card services. Prior notifications already covered orders, payments, fulfillment, approvals, reviews and payouts. Missing pieces were preferences, current operational domains and reliable per-channel retries.

## Files Created

- app/Enums/NotificationCategory.php
- app/Events/CommerceNotificationRequested.php and FinancialNotificationRequested.php
- app/Services/Notifications/CommerceNotificationListener.php, LifecycleNotificationListener.php, NotificationPreferences.php
- app/Notifications/Channels/IdempotentDatabaseChannel.php and IdempotentMailChannel.php
- app/Notifications/Lifecycle/*Notification.php (controlled lifecycle subclasses)
- app/Providers/TransactionalNotificationServiceProvider.php
- app/Http/Controllers/NotificationPreferenceController.php
- app/Console/Commands/RetryNotificationDispatches.php
- database/migrations/2026_09_23_000100_extend_transactional_notifications.php
- resources/views/notifications/preferences.blade.php
- resources/views/emails/transactional.blade.php and transactional-text.blade.php
- lang/en/notifications.php, lang/ur/notifications.php, lang/ar/notifications.php
- tests/Feature/Notifications/TransactionalNotificationTest.php and NotificationDeliveryTest.php
- docs/prompt-51-report.md

## Files Modified

MarketplaceNotifier, SendUniqueNotification, MarketplaceNotification; existing ReturnStatusChanged/DisputeActivity events and their workflow emission points; RefundService, WalletService, GiftCardService; DisputeController failure reporting; NotificationController URL validation; bootstrap/providers.php; routes/web.php and console.php; notification list partial; DatabaseSeeder/NotificationSeeder; NotificationFoundationTest; docs/architecture.md. Build artifacts regenerated. Commerce calculations were not changed.

## Database Changes

One additive migration creates notification_preferences (unique user/category), adds users.notification_locale, and adds replay payload/status/channel timestamps/safe error class to notification_dispatches. Existing notification history remains intact. Local driver **mysql**, database **ezshop**. Tests use SQLite in memory.

## Domain Event Architecture

The complete class-by-class mapping is in the **Transactional Notifications (Prompt 51)** section of docs/architecture.md. Actual flows:

- CommerceNotificationRequested -> CommerceNotificationListener -> existing Orders/Payments, Vendors, Products, Reviews, Refunds and Payouts notification subclasses -> owning customer/vendor or actionable admin recipients.
- ReturnStatusChanged -> LifecycleNotificationListener.returning -> ReturnRequested/Approved/Rejected/Received/Inspected/Cancelled/RefundPendingNotification -> customer, relevant seller, admin when refund review is required.
- DisputeActivity -> LifecycleNotificationListener.dispute -> DisputeOpened/Message/ResponseRequired/Resolved/ClosedNotification -> participants, excluding message author; assigned admin or admins for actionable activity.
- FinancialNotificationRequested -> LifecycleNotificationListener.financial -> RefundProcessing/ExternalPending/ImpactNotification, WalletCredit/DebitNotification, GiftCardIssuedNotification, SystemFinancialFailureNotification; completed refunds reuse RefundSucceededNotification.
- SendUniqueNotification -> Laravel queued notification -> database/mail channels. Channels follow each existing event's mail eligibility and the required/optional policy. No commerce Action contains provider-specific mail calls.

## Notification Categories

account, vendor, product, order, payment, fulfillment, return, refund, dispute, wallet, gift_card, payout, system. Severity is restricted to info/success/warning/action_required. Financial failures use required account policy.

## Customer Notifications

Order placement with historical total/currency; authoritative payment confirmed/failed; seller shipment/delivery; cancellation; return request/approval/rejection/receipt/inspection/cancellation/refund review; refund processing/completed/provider pending; dispute opening, required response, other participant response, resolution/closure; administrative wallet credit/debit; associated gift-card issuance. Refund completion includes actual safe funding destination labels and amount. No gift-card secret is emailed or stored in notification data.

## Vendor Notifications

Application approved/rejected/suspended/reactivated; product approval/rejection; new actionable seller order with item quantity and seller total; own cancellation; own return requested/cancelled; own dispute opened/response required/resolved/messages; refund financial impact; payout requested/approved/processing/paid/rejected. New online orders wait for CanFulfillVendorOrder eligibility; authoritative payment confirmation triggers the same deduplicated seller alert. COD remains actionable according to the existing service.

## Admin Notifications

Vendor application, product submission, payment review, payout approval request, return requiring refund review, dispute opened/reply (assigned admin when present), external refund pending, important financial failure. No alert is emitted for every assignment or internal review change. The current payout lifecycle has rejection rather than a provider-failed status; no fake provider failure state was added.

## Database / In-App Notifications

Standard Laravel UUID notifications and existing inbox architecture remain. Minimal payloads contain category/type/title/message/action URL/action label/severity/source key. Complete Eloquent models are never written to notification data. No retention deletion was added.

## Customer Notification UI

Existing /account/notifications retained; preferences added at /account/notifications/preferences. Pagination, unread filtering and actions remain.

## Vendor Notification UI

Existing /vendor/notifications retained, with own preferences. Vendor notifications stay accessible under existing role routing and are scoped to their User identity.

## Admin Notification UI

Existing /admin/notifications retained, with the same preference UI. Actionable admin items link to protected operational pages.

## Unread Indicators

Existing customer/vendor/admin indicators retained. Each bell uses an indexed recipient count and no more than five latest notifications; it does not load unbounded history. No WebSockets or per-notification relation loop was added.

## Read Operations

Mark one/all and open stay scoped to the authenticated user's relationship. Cross-user operations return not found. Internal action links remain subject to destination ownership/role authorization. External, protocol-relative, newline and backslash redirects are rejected.

## Notification Preferences

**Mandatory transactional:** account, vendor, order, payment, return, refund, dispute, wallet, gift_card, payout. Required in-app and eligible email cannot be disabled through request tampering.

**Optional transactional:** product, fulfillment, system. Users can disable in-app and/or eligible mail for these. Existing database-only event mappings remain database-only. Preferences do not introduce marketing subscriptions or unsupported channel controls. Locale is explicitly selected as English, Urdu or Arabic.

## Email Architecture

Native queued Laravel MailMessage, custom responsive HTML and plain-text views, safe escaped content, primary action, reference/status and support context. Tests fake mail; no real email is sent as a test.

## Email Branding

SettingsService supplies marketplace name and support email; application name is the fallback. Current settings have no configurable logo field, so email uses text branding. APP_URL and named routes generate links, without hard-coded local hostnames.

## Queue Architecture

Uses configured QUEUE_CONNECTION; local value is database. Notifications implement ShouldQueue with five attempts and increasing backoff. Dispatch snapshots make enqueue failures recoverable. notifications:retry requeues pending/failed dispatches, scheduled every minute without overlap. Production needs supervised workers, scheduler, finite SMTP timeout, worker timeout below retry_after, and failed-job/dispatch monitoring.

## After-Commit Behavior

Commerce event publication and notification dispatch run after commit. Return/dispute events carry stable history/message versions. A rolled-back operation emits no committed notification. Authoritative financial status is checked before payment/refund completion alerts.

## Email Failure Independence

Dispatch/provider failures are caught and logged safely after commerce commits. Tests simulate transport failure and verify the order remains while the inbox notification persists and dispatch is retryable. Refund/dispute orchestration failures log only record ID and exception class; applicable admin alerts contain no exception details.

## Duplicate Protection

Existing unique recipient/source-key ledger retained. Each dispatch stores a replayable notification constructor snapshot. Database and mail channel handlers lock that dispatch and record separate sent timestamps. Replayed events cannot create a second logical dispatch; retried jobs skip completed channels. Source keys include business entity/event and return-history/dispute-message version, preserving distinct orders/states/messages. Native failed jobs remain recoverable through Laravel.

SMTP cannot guarantee exactly-once receipt: provider acceptance followed by a process crash before storing the receipt may cause a duplicate on retry. The implementation prevents normal event/job replay duplicates; provider idempotency would be needed to close that external acknowledgement gap.

## Multi-Vendor Isolation

Recipients come from the specific VendorOrder.vendor.user. Customer order totals are never used in seller notification amounts. Vendor refund alerts contain only that seller's adjustment and reference. No broadcast to marketplace sellers.

## Privacy & Security

No wallet balances, gift-card codes/hashes, full models, payment credentials, internal inspection notes, stack traces or other seller data in payloads. Return rejection text is drawn only from existing participant-visible history. Dispute authors are not alerted about their own reply. CSRF/role protection remains active on forms. New preference inputs and categories are whitelisted and bounded.

## Currency Handling

Amounts use original Order/Refund/Payout/Wallet/GiftCard currency through Money. Notification delivery does not use the current storefront currency or convert historical amounts.

## Localization / RTL

Explicit recipient notification_locale overrides configured app.locale; neither country nor currency implies language. Core titles/email chrome/preferences use translation keys in en/ur/ar. Untranslated longer transactional sentences fall back to English. HTML mail has matching lang and RTL direction for Arabic/Urdu; notification rows use automatic text direction and existing layout RTL support. Escaping/RTL email structure is tested. No manual browser/device or email-client screenshot audit is claimed.

## Future Channels

SMS: NOT INTEGRATED. WhatsApp: NOT INTEGRATED. Mobile Push: NOT INTEGRATED. Laravel channels provide the extension point; no paid provider or marketing platform was introduced.

## Demo Data

Existing representative customer order/refund, vendor order/payout and admin moderation/payout examples retained without deleting read history. Added real operational dispute links for participants and pending-provider refund alerts for admins. Repeated database seeds preserve notification IDs, data, read state and dispatch counts.

## Dedicated Notification Tests

**29 passed, 127 assertions, 0 failures/errors/skips; 7.68s.** Includes mail rendering/fakes, actual database queue push/pop/job processing, enqueue/transport failure independence, retries, channel receipts, preferences, locale/RTL, event recipients, financial privacy and action authorization.

## Returns Regression

**30 passed, 121 assertions; 18.85s.**

## Refunds Regression

**17 passed, 77 assertions; 19.84s.**

## Disputes Regression

**24 passed, 111 assertions; 28.60s.**

## Other Commerce Regression

Orders/checkout, payments, wallet, gift cards, commission, vendor ledger, payouts, product/vendor approvals are included in the new full suite below. Existing financial semantics and historical records were preserved.

## Full Marketplace Regression

**480 passed, 3,214 assertions, 0 failures, 0 errors, 0 skipped; 124.75s.** Fresh php artisan test --compact run after the actionable vendor-order guard and latest notification changes.

## Migration

php artisan migrate --force: PASS; safe additive 2026_09_23_000100 migration. No destructive migration command.

## Repeated Seeder

php artisan db:seed --force twice: PASS. A third verification run returned repeat_seed_notifications_unchanged=true and repeat_seed_dispatch_count_unchanged=true. Existing exhausted historical demo return was reported and preserved.

## Routes

php artisan route:list: PASS. Notification-specific list contains 18 routes: list, open, mark one, mark all, preferences GET/PUT for each account/vendor/admin namespace. Full route output saved to storage/logs/prompt51-routes.log.

## Optimize

php artisan optimize:clear: PASS.

## Production Build

npm.cmd run build: PASS, 59 modules, 9.80s. Initial sandbox run hit existing esbuild spawn EPERM; approved execution outside the sandbox succeeded. No application workaround was added.

## Live Email Delivery

**NOT VERIFIED — PRODUCTION MAIL PROVIDER NOT CONFIGURED.** Local MAIL_MAILER is log. Template rendering, mail fakes and queue delivery are verified; no production SMTP claim is made.

## Queue Verification

Configured local database connection confirmed. Tests inserted two native queued notification jobs, processed them through Laravel's database queue, and verified one inbox notification plus mail-channel receipt with Mail::fake. Separate Queue::fake test verifies recipient locale. Failure/retry tests verify commerce independence and no duplicate inbox entry. Production worker/scheduler requirements are documented above and in architecture.md.

## 90-Item Acceptance Checklist

Each result reflects automated integration checks and inspected implementation; PASS does not claim a live SMTP or manual browser test.

| # | Acceptance item | Result |
|---|---|---|
| 1 | Existing notification architecture audited before implementation | PASS |
| 2 | Existing domain events reused where possible | PASS |
| 3 | No provider-specific mail in business services | PASS |
| 4 | Database notification architecture works | PASS |
| 5 | Customer order placed notification | PASS |
| 6 | Correct vendor receives new actionable vendor order | PASS |
| 7 | Multi-vendor notification isolation | PASS |
| 8 | Authoritative payment confirmed notification | PASS |
| 9 | Payment failure without uncontrolled spam | PASS |
| 10 | Shipped notification | PASS |
| 11 | Delivered notification | PASS |
| 12 | Order cancellation notification | PASS |
| 13 | Return requested customer notification | PASS |
| 14 | Return requested vendor notification | PASS |
| 15 | Return approved notification | PASS |
| 16 | Return rejected notification | PASS |
| 17 | Return received notification | PASS |
| 18 | Refund processing notification | PASS |
| 19 | Refund completed notification | PASS |
| 20 | External pending refund does not claim completion | PASS |
| 21 | Dispute opened customer notification | PASS |
| 22 | Dispute opened vendor notification | PASS |
| 23 | Dispute opened admin notification | PASS |
| 24 | Awaiting-customer notification | PASS |
| 25 | Awaiting-vendor notification | PASS |
| 26 | Dispute message notification | PASS |
| 27 | Message author not self-notified | PASS |
| 28 | Dispute resolution notification | PASS |
| 29 | Wallet admin credit notification | PASS |
| 30 | Wallet admin debit notification | PASS |
| 31 | Vendor approval notification | PASS |
| 32 | Vendor rejection notification | PASS |
| 33 | Product approval notification | PASS |
| 34 | Product rejection notification | PASS |
| 35 | Payout approved notification | PASS |
| 36 | Payout paid notification | PASS |
| 37 | Payout rejection notification (no provider-failed state exists) | PASS |
| 38 | Admin actionable notifications | PASS |
| 39 | Role-safe database payloads | PASS |
| 40 | No full gift-card secrets exposed | PASS |
| 41 | No customer wallet information exposed to vendor | PASS |
| 42 | No other vendor data exposed | PASS |
| 43 | Customer notification list | PASS |
| 44 | Vendor notification list | PASS |
| 45 | Admin notification list | PASS |
| 46 | Customer unread indicator | PASS |
| 47 | Vendor unread indicator | PASS |
| 48 | Admin unread indicator | PASS |
| 49 | Mark-one-read | PASS |
| 50 | Mark-all-read | PASS |
| 51 | Cross-user read manipulation blocked | PASS |
| 52 | Customer preferences | PASS |
| 53 | Vendor preferences | PASS |
| 54 | Required transactional policy enforced | PASS |
| 55 | Optional email preference respected | PASS |
| 56 | Unsupported channels not shown as working | PASS |
| 57 | Mail rendering | PASS |
| 58 | Queue-ready mail delivery | PASS |
| 59 | Commerce independent of SMTP success | PASS |
| 60 | Configuration-driven queue connection | PASS |
| 61 | Links use application routing/configuration | PASS |
| 62 | Action URLs do not bypass authorization | PASS |
| 63 | Duplicate logical notification protection | PASS |
| 64 | Distinct lifecycle notifications remain distinct | PASS |
| 65 | Historical currency preserved | PASS |
| 66 | Localization architecture used | PASS |
| 67 | Recipient locale supported | PASS |
| 68 | RTL notification structure valid | PASS |
| 69 | Demo customer notifications | PASS |
| 70 | Demo vendor notifications | PASS |
| 71 | Demo admin notifications | PASS |
| 72 | Repeated seeding safe | PASS |
| 73 | Returns regression | PASS |
| 74 | Refunds regression | PASS |
| 75 | Disputes regression | PASS |
| 76 | Orders regression | PASS |
| 77 | Payments regression | PASS |
| 78 | Wallet regression | PASS |
| 79 | Gift-card regression | PASS |
| 80 | Commission regression | PASS |
| 81 | Vendor ledger regression | PASS |
| 82 | Payout regression | PASS |
| 83 | Vendor/product approval regression | PASS |
| 84 | Full marketplace regression | PASS |
| 85 | Additive migration | PASS |
| 86 | Routes | PASS |
| 87 | Optimize/cache clearing | PASS |
| 88 | Production frontend build | PASS |
| 89 | Production SMTP limitation truthfully documented | PASS |
| 90 | Production queue requirements documented | PASS |

## Known Limitations

- Production SMTP/provider delivery is not verified; local transport is log.
- Exactly-once external email cannot be guaranteed across provider acceptance/process-crash boundaries without provider idempotency.
- Long-form event text has English fallback; core locale/RTL rendering and translation architecture are implemented, not a claim of complete Urdu/Arabic copy translation.
- Unexpected listener preparation failures are safely logged for investigation; replay snapshots cover dispatches that were successfully persisted. Historical legacy dispatches are preserved without replay payloads.

## Prompt 51 Status

**TRANSACTIONAL NOTIFICATIONS: COMPLETE**

## Next Recommended Module

Prompt 52 — Invoice & Order Documents. Not implemented.
