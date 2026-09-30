# Marketplace Architecture

This project is a Laravel 12 modular monolith. Laravel owns routing, HTTP lifecycle, views, authorization, queues, jobs, events, and database access. The application should grow feature by feature without introducing microservices, CQRS, event sourcing, or repository interfaces for every model.

## Current Project Audit

- Laravel: 12.68.0
- PHP requirement: `^8.2`
- Local PHP CLI observed during setup: 8.2.3
- Database default: SQLite in local `.env`; production is expected to use MySQL
- Frontend: Blade, Vite, Tailwind CSS 4, Axios
- Vue: not installed; add Vue 3 only when a feature genuinely needs complex client-side reactivity
- Authentication scaffolding: Laravel's default `User` model, `web` guard, users provider, password reset configuration
- Test stack: PHPUnit 11 through Laravel's default `php artisan test`
- Quality tooling available: Laravel Pint, Pail, Sail, Collision

## Application Structure

Use a domain-oriented modular monolith without a custom module framework.

- `app/Http`: controllers, middleware, form requests, and HTTP-specific concerns
- `app/Models`: Eloquent models that represent persisted business data
- `app/Actions`: focused application actions when a use case has meaningful orchestration
- `app/Services`: integrations or cohesive services that do not naturally belong to one model
- `app/Enums`: shared backed enums for states and roles that are actively needed
- `app/Domain`: domain-oriented code as business areas emerge
- `app/Domain/Shared`: cross-domain concepts that are genuinely shared
- `app/Support`: low-level helpers may be added later when there is real reuse

Controllers should stay thin: receive the request, authorize, validate, call an action or service when useful, and return a response. Eloquent should be used directly where appropriate. Do not create empty action, service, repository, DTO, event, listener, job, policy, or value-object layers just to satisfy a pattern.

## Roles And Authorization

Use one `users` table and one session-based `web` guard unless a future requirement proves otherwise. The foundational roles are:

- `admin`
- `vendor`
- `customer`

Role values are represented by `App\Enums\UserRole` and stored on `users.role`. Vendor-specific data should live in future vendor/store tables related to users. Customer-specific data should remain on users unless there is a clear need for a separate customer profile table.

Authentication uses Laravel's session-based `web` guard. Do not create separate admin, vendor, or customer guards.

Public registration always creates a `customer` user. The registration request never accepts role input, and `users.role` is intentionally not mass assignable on the `User` model. Internal code such as seeders may assign roles directly on model instances.

Role-specific access is enforced by the `role` middleware alias registered in `bootstrap/app.php`. Cross-role access returns `403 Forbidden`.

- `/admin`: `auth` plus `role:admin`
- `/vendor`: `auth` plus `role:vendor`
- `/account`: `auth` plus `role:customer`

Successful login and guest access to `/login` or `/register` redirect users through `User::dashboardPath()`:

- `admin` -> `/admin`
- `vendor` -> `/vendor`
- `customer` -> `/account`

Authorization should grow through Laravel policies, gates, middleware, and form requests. Do not create separate authentication systems for admins, vendors, and customers.

## Customer Account

Customer self-service lives under `/account` and uses the same `web` guard plus `role:customer` middleware as the authentication foundation. Admins and vendors receive `403 Forbidden` for customer account routes; guests are redirected to login.

The account area is a Blade/Tailwind experience with a shared account layout, mobile account navigation, and `noindex,nofollow` metadata. It is not a second frontend application and does not introduce Vue or a separate API.

`App\Services\Account\CustomerAccountDashboardService` prepares the dashboard summary so controllers do not accumulate inline queries. The dashboard shows real customer-scoped counts for orders, wishlist items, delivered purchases awaiting review, unread notifications, latest orders, the default delivery address, and recent notifications.

Customer orders are read-only except for existing customer-safe actions such as unpaid order cancellation and card payment retry. Order detail pages render order snapshots, seller group snapshots, shipping snapshots, payment status, refunds visible to the customer, and review actions for delivered items. They do not expose provider references, internal refund operations, vendor earnings, commissions, ledger entries, or admin-only financial data.

Profile completion is intentionally narrow: customers may update only their name and email address. Role, password, moderation state, and financial data are not accepted from the profile request. Password changes live on the security page, require the current password through Laravel's `current_password:web` validation rule, use Laravel password defaults, and keep the current session active.

Wishlist, reviews, addresses, notifications, order history, profile, and security remain scoped to the authenticated customer. Ownership is enforced in controllers, form requests, policies, or model-scoped queries as appropriate; navigation visibility is not treated as authorization.

## Vendor Onboarding And Approval

Vendor identity is split between `users` and `vendors`.

- `users`: authentication identity, credentials, and `UserRole`
- `vendors`: seller/store profile, application state, storefront-facing identity, and admin approval metadata

Each vendor belongs to exactly one vendor-role user through `vendors.user_id`, which is unique. Public seller registration creates both records inside one database transaction. Public customer registration still creates only customer users.

Vendor statuses are represented by `App\Enums\VendorStatus`:

- `draft`: vendor account exists, application can be edited and saved
- `pending`: application submitted and locked while admin reviews it
- `approved`: seller dashboard access is allowed
- `rejected`: rejection reason is visible, vendor can edit and resubmit
- `suspended`: user can sign in and view status, but seller operations are blocked

Valid transitions are centralized in vendor action classes:

- `Draft -> Pending`
- `Rejected -> Pending`
- `Pending -> Approved`
- `Pending -> Rejected`
- `Approved -> Suspended`
- `Suspended -> Approved`

Vendor login redirects through `User::dashboardPath()`:

- approved vendor -> `/vendor`
- draft vendor or missing vendor record -> `/vendor/onboarding`
- pending, rejected, or suspended vendor -> `/vendor/application-status`

The approved seller dashboard uses the `vendor.approved` middleware. Onboarding and status routes require `auth` plus `role:vendor` only, so pre-approval vendors can complete or review their application without reaching seller operations.

Admins review vendors under `/admin/vendors`. Approval, rejection, suspension, and reactivation are controller endpoints backed by focused action classes. Rejection requires a reason.

Vendor branding uploads use Laravel's `public` disk and store relative paths under:

- `vendors/{vendor_id}/logo`
- `vendors/{vendor_id}/banner`

Old logo/banner files are deleted when replaced. Original upload filenames are not trusted.

## Vendor Dashboard And Seller Operations

The Vendor Dashboard is an operational presentation layer over existing seller domains. It does not introduce a duplicate financial, fulfillment, inventory, promotion, or product source of truth. `/vendor` renders through `App\Services\VendorDashboard\VendorDashboardService`, which centralizes summary queries and keeps the dashboard controller thin.

Vendor routes use one `web` guard and `role:vendor`. Draft, pending, rejected, and suspended vendors keep access to onboarding/status, notifications, and payout history routes that are safe for their state. Approved-only routes keep the `vendor.approved` middleware for seller mutations and operational modules such as product management, inventory changes, promotions, fulfillment actions, payout requests, and store profile edits. Suspended vendors are redirected to application status for approved-only areas.

The dashboard metrics are real vendor-scoped data:

- Active Products: the vendor's `Product` rows with `status = active` and `approval_status = approved`.
- Orders Requiring Action: the vendor's own `VendorOrder` rows in pending, confirmed, processing, or ready-to-ship statuses.
- Low Stock Variants: the vendor's inventory rows using the existing `Inventory::lowStock()` rule, where available stock is positive and at or below threshold.
- Available Balance and Pending Balance: values from `VendorEarningsService`, backed by `VendorLedgerEntry` and currency-specific integer minor units.
- Payout reservations and requestable balance: values from `VendorPayoutBalanceService`, so active payout reservations are not recalculated in Blade.

Dashboard links route into the authoritative modules: products, inventory, vendor orders, promotions, reviews, earnings, commissions, payouts, notifications, and store profile. It does not place fulfillment or financial mutation buttons on the overview; those actions remain on the existing detail pages and Actions.

Product attention states are counts for draft, pending approval, rejected, and inactive products. Rejected and pending products link into existing product filters, and product moderation still belongs to the Product workflow.

Vendor order summaries use `VendorOrder` records, not parent order totals. The dashboard shows safe customer names only and avoids full delivery address, customer email, provider references, and cross-seller order data. Full shipping snapshots are only shown on the seller order detail page where fulfillment needs them.

Finance presentation uses Vendor Ledger services. The Vendor Ledger remains authoritative for gross sales, commission, promotion debits, refunds, commission refunds, payouts, available balance, pending balance, and net earnings. Commission pages remain read-only for vendors and display historical/snapshotted commission records.

Reviews are read-only for vendors. The dashboard label is `Average Product Rating`, calculated as a weighted aggregate from product `rating_sum` and `rating_count`; it is intentionally not labeled as a seller/store rating.

Store Profile lets approved vendors edit existing public identity fields such as display name, description, public email, phone, website, logo, and banner. Vendors cannot edit status, approval metadata, or slug through this route. Storefront layout, theme, CSS, JavaScript, fonts, checkout behavior, and marketplace structure remain marketplace-controlled.

Multi-vendor privacy is mandatory across seller operations. Vendors may see only their products, inventory, `VendorOrder` rows, seller order items, refund allocations affecting their seller order, commission records, ledger entries, payouts, promotions, reviews, notifications, and store profile. Request IDs are never trusted as ownership proof; controllers, policies, form requests, and vendor-scoped queries enforce access.

Deferred seller features remain out of scope: seller staff accounts, granular seller permissions, customer messaging, support tickets, returns/RMA, courier integrations, shipping labels, bulk imports, bulk inventory uploads, financial exports, invoices, tax reports, advanced analytics, custom storefront themes, and automatic payouts.

## Admin Dashboard And Marketplace Operations

The Admin Dashboard is an orchestration and observation layer over existing marketplace domains. It does not create a second source of truth for commerce, fulfillment, payments, refunds, commission, seller ledger, payouts, product moderation, vendor moderation, or notifications. `/admin` renders through `App\Services\AdminDashboard\AdminDashboardService`, while grouped sidebar badges use `App\Services\AdminDashboard\AdminNavigationCounterService`.

Admin routes continue to use the single `web` guard plus `role:admin`. Customers and vendors receive `403 Forbidden`, guests are redirected to login, and admin pages include `noindex,nofollow`.

Admin navigation is grouped into:

- Overview: Dashboard.
- Marketplace: Vendors, Products, Categories, Brands, Attributes, Attribute Sets, Inventory, Shipping.
- Commerce: Orders, Payments, Refunds, Promotions, Reviews.
- Finance: Commission Rules, Commissions, Vendor Ledger, Payouts.
- Operations: Notifications.

The dashboard prioritizes current operational state:

- Vendor Approval Queue: `vendors.status = pending`.
- Product Moderation Queue: `products.approval_status = pending`.
- Review Moderation Queue: `product_reviews.status = pending`.
- Payment Review Queue: payments with `requires_review_at` set and `review_resolved_at` still null.
- Refund Queue: refunds in pending, processing, or failed status.
- Payout Queue: payouts in pending, approved, or processing status.

Dashboard summary metrics are intentionally conservative. Approved Vendors counts `vendors.status = approved`; Active Products counts active and approved products; Orders Today counts orders placed today; Gross Order Value sums immutable `orders.grand_total` for the marketplace currency and is not labeled revenue. Commission figures use `VendorOrderCommission`, `CommissionAdjustment`, and Vendor Ledger entries; they are not profit metrics.

Admin lifecycle actions remain explicit. Vendor moderation uses `ApproveVendor`, `RejectVendor`, `SuspendVendor`, and `ReactivateVendor`. Product moderation uses `ApproveProduct` and `RejectProduct`. Review moderation uses review approval/rejection Actions. Payout transitions use the Prompt 28 payout Actions. The admin dashboard links to detail pages and does not add generic status dropdowns, parent order status mutation, ledger editing, or one-click financial mutation from overview cards.

Financial source of truth remains split by domain responsibility:

```text
Orders -> immutable commerce snapshots
Payments / Refunds -> customer money movement
Commission -> marketplace fee snapshots and adjustments
Vendor Ledger -> seller financial truth
Payout -> seller cash-out lifecycle
```

Admin Vendor detail shows compact operational counts and ledger-derived financial context, then links to the dedicated Vendor Financials and Vendor Ledger pages. The Vendor Ledger remains append-only and read-only from Admin UI.

Sensitive data exposure is limited to operational need. Admin payment pages may show provider references and attempt state but never secrets, card data, or webhook signing material. Payout index pages use masked destination context; payout detail may show destination snapshot data needed for manual processing. Customers and other vendors never see payout destination data.

Admin filters are scoped and whitelisted per module. Large admin lists paginate and preserve filters. Search inputs are bounded strings and do not allow dynamic column selection or arbitrary sorting.

Deferred admin features remain out of scope: impersonation, multiple admin role/permission matrix, generic audit logs, support/ticketing, returns/RMA, disputes/chargebacks, courier management, tax engine, accounting exports, invoices, advanced BI, marketplace general ledger, global cross-domain search, and bulk operations.

## Category Management

Categories are marketplace-managed taxonomy records controlled by admins only. Vendors do not own categories and cannot manage them.

The `categories` table uses a self-referencing hierarchy:

- `parent_id = null` means a root category
- `parent_id` references another `categories.id`
- each category belongs to one parent and may have many children

The model supports arbitrary depth. Admin UI should stay practical, and 3-4 levels is a sensible marketplace guideline, but the database does not enforce a hard depth.

Category slugs are globally unique because future public URLs should use:

- `/category/{slug}`

When an admin leaves the slug blank on creation, the system generates one from the name and adds a deterministic suffix on collision, such as `electronics-2`. When editing, changing the name does not change the existing slug unless the admin edits the slug field. Manually entered slugs are normalized and must be unique.

Visibility uses `is_active`. Inactive categories stay in the database. Children are not automatically changed when a parent is disabled; future public catalog visibility should require the category and required ancestors to be active.

Hierarchy safety is enforced during updates:

- a category cannot be its own parent
- a category cannot be moved under one of its descendants

Categories use soft deletes. A category with child categories cannot be deleted, because removing a parent would make future product, navigation, and breadcrumb behavior unsafe. Future product dependencies should extend the same delete-protection action.

Category image and icon uploads use Laravel's `public` disk and store relative paths under:

- `categories/{category_id}/image`
- `categories/{category_id}/icon`

Old image/icon files are removed only after the replacement path has been saved.

The category hierarchy is prepared in the admin controller before rendering. Listing queries eager-load parents and child counts to avoid one query per row.

## Brand Management

Brands are global marketplace data managed by admins. Vendors cannot create, rename, activate, deactivate, or delete brands. Later, vendors will select from existing brands when products are introduced; a separate brand request workflow can be considered if sellers need brands that do not exist yet.

The `brands` table stores:

- brand identity: `name`, `slug`, `description`
- visual identity: `logo_path`
- organization fields: `is_active`, `sort_order`
- SEO fields: `seo_title`, `seo_description`
- soft deletion timestamps

Brand slugs are globally unique and reserved even after soft deletion. Future public brand URLs should use:

- `/brand/{slug}`

When an admin leaves the slug blank on creation, the system generates one from the name and adds a deterministic suffix on collision, such as `apple-2`. When editing, changing the name does not change the slug unless the admin edits the slug field. Manually entered slugs are normalized and must be unique.

Visibility uses `is_active`. Inactive brands remain stored and should be excluded from future public catalog queries where appropriate. Inactivating a brand should not automatically mutate future products.

Logo uploads use Laravel's `public` disk and store relative paths under:

- `brands/{brand_id}/logo`

The existing logo is preserved when no replacement is uploaded. On replacement, the new file is stored and saved before the old file is deleted.

Future product relationships should be:

- `Product belongsTo Brand`
- `Brand hasMany Products`

Do not add those relationships until the product model exists.

## Product Attribute Management

Product attributes are global catalog configuration managed by admins. Vendors cannot create, edit, activate, deactivate, or delete attributes or their predefined values. Later, vendors will consume configured attributes when creating products.

The codebase uses `ProductAttribute` and `ProductAttributeValue` instead of `Attribute` to avoid confusion with PHP attributes and Laravel accessor casts.

The `product_attributes` table stores:

- identity: `name`, stable unique `code`
- behavior: `type`, `input_type`
- marketplace configuration: `is_filterable`, `is_required`, `is_active`, `sort_order`
- soft deletion timestamps

Attribute codes are lowercase, snake_case, globally unique, and intended for internal product configuration, URL query filters, and future integration points. When an admin leaves code blank on creation, it is generated from the name with a deterministic suffix on collision. Editing an attribute name does not change the existing code unless the admin edits the code field.

Attribute types are represented by `App\Enums\ProductAttributeType`:

- `variant`: creates sellable product combinations, such as Color, Size, Storage, or RAM
- `specification`: stores descriptive product information, such as Processor, Material, Screen Size, or Warranty

Input types are represented by `App\Enums\ProductAttributeInputType`:

- `select`: one predefined value
- `multiselect`: multiple predefined values for descriptive specifications
- `text`: free text
- `number`: numeric value
- `boolean`: true/false value

Variant attributes must use the `select` input type. Specification attributes may use any current input type. If a select or multiselect attribute already has predefined values, it cannot be changed to a free-input type until those values are removed. Future product usage should add stricter guards before allowing type or input changes.

The `product_attribute_values` table stores predefined options that belong to one attribute:

- `product_attribute_id`
- `value`
- stable `code` unique within that attribute
- `sort_order`
- `is_active`
- nullable `metadata`
- soft deletion timestamps

Value codes use URL-friendly lowercase slugs such as `midnight-blue`. The same value code may exist under different attributes, but duplicates are blocked within a single attribute. Optional metadata is currently limited to known value metadata such as a color `hex` value for future swatches.

Visibility uses `is_active` on both attributes and values. Inactive records remain stored and should be excluded from future new product setup where appropriate; existing future product data should not be deleted simply because an attribute or value is deactivated.

`is_filterable` marks an attribute as eligible for future storefront filtering. `is_required` is a future product setup rule and should be enforced only after attributes are assigned to a product context.

Do not add product attribute pivot tables yet. Future product integration is expected to introduce concepts such as:

- `Product`
- `Product Variant`
- product variant attribute values
- product specifications

Before full Product CRUD, decide whether attributes should be grouped and assigned through:

- Attribute Sets
- Category Attribute Mapping

## Attribute Sets And Category Catalog Configuration

Attribute sets are reusable product-type configurations. They answer which product attributes should appear for a product context without assigning attributes directly to every category one by one.

The category catalog flow is:

- `Category`
- belongs to one nullable `ProductAttributeSet`
- which contains ordered `ProductAttribute` assignments
- which later drive product variant and specification forms

The `product_attribute_sets` table stores:

- identity: `name`, stable unique `code`, `description`
- organization: `is_active`, `sort_order`
- soft deletion timestamps

The `product_attribute_set_attributes` pivot table stores the contextual configuration for attributes inside a set:

- `product_attribute_set_id`
- `product_attribute_id`
- `is_required`
- `sort_order`

An attribute can appear only once per set, enforced by a unique pivot constraint. The same attribute can appear in many sets.

`ProductAttribute.type`, `ProductAttribute.input_type`, and `ProductAttribute.is_filterable` remain global attribute properties. Attribute sets do not override variant/specification type or input type.

Required behavior is contextual once sets are involved:

- `ProductAttribute.is_required` is a global/default template value for admins.
- `product_attribute_set_attributes.is_required` is the authoritative requirement when product creation uses an attribute through a set.

Set-level `sort_order` is also authoritative for product form and specification display order inside that product context. Global attribute sort order remains useful for generic admin lists.

Categories store nullable `product_attribute_set_id`. A category without a set is valid. Inactive sets remain stored but are not offered for new category assignments; an existing inactive assignment can remain visible and be saved safely. Product creation should preferably target product-bearing leaf categories with explicit attribute set assignments where structured attributes are needed. This leaf-category convention is not enforced yet.

Attribute sets cannot be deleted while assigned to categories. Product attributes cannot be deleted while assigned to any active attribute set, and the previous value-based deletion protection still applies. Removing an attribute from a set is currently safe because products do not exist; future product usage should add dependency checks before unsafe removal.

Soft-deleting an unassigned attribute set leaves its pivot rows in place. This keeps restoration semantics simple and avoids mutating ProductAttribute records.

Global attribute type has an intentional MVP limitation: a single ProductAttribute has one global type. For example, RAM may be variant-producing for laptops but specification-like elsewhere. The current architecture keeps that global rule to avoid scope creep; if real catalog needs require contextual attribute type later, introduce that deliberately rather than hiding it in set overrides.

## Product Core And Variant Architecture

Products are vendor-owned marketplace listings. For this MVP, two vendors selling the same real-world item create separate `Product` records. There is no shared Amazon-style master catalog yet.

Core ownership is:

- `Vendor hasMany Product`
- `Category hasMany Product`
- `Brand hasMany Product`
- `Product belongsTo Vendor`
- `Product belongsTo Category`
- `Product belongsTo Brand` with nullable brand

Product slugs are globally unique and reserved after soft deletion. Future public product URLs can start with `/product/{slug}`. If the marketplace later needs store-scoped URLs, a future path such as `/store/{vendorSlug}/product/{productSlug}` can be introduced deliberately.

The project uses a unified sellable-entity model:

- `Product` stores listing information such as name, category, brand, descriptions, status, and approval metadata.
- `ProductVariant` stores every sellable SKU and price.

Simple products use exactly one default variant with `combination_key = default` and no variant attribute assignments. Variant products use multiple variants, each connected to selected `ProductAttributeValue` rows. Inventory and cart items reference `product_variant_id`; future order items should follow the same rule.

Authoritative pricing lives on `product_variants.base_price` and `product_variants.sale_price` using integer minor units. Product-level price columns are intentionally omitted to avoid duplicated pricing truth.

Authoritative sellable SKU lives on `product_variants.sku` and is globally unique. Product-level SKU is intentionally omitted for this MVP; parent/style SKUs can be added later if the business needs them.

Product lifecycle uses two enums:

- `App\Enums\ProductStatus`: `draft`, `active`, `inactive`, `archived`
- `App\Enums\ProductApprovalStatus`: `draft`, `pending`, `approved`, `rejected`

Vendor publication state and marketplace moderation state are separate. New products start as `status = draft` and `approval_status = draft`.

Future public visibility should require roughly:

- product status is `active`
- approval status is `approved`
- vendor status is `approved`
- category and required ancestors are active

No storefront query is implemented yet.

Variant attribute selections are stored relationally in `product_variant_attribute_values`. The table intentionally stores both `product_attribute_id` and `product_attribute_value_id`; the attribute ID is derivable, but storing it allows a database unique constraint that guarantees one selected value per variant attribute. Application logic verifies that each selected value belongs to that attribute and that the attribute belongs to the product category's Attribute Set.

`ProductVariant.combination_key` protects duplicate combinations per product. For configured variants it is generated from sorted `attribute_id:value_id` pairs, such as `4:21|7:39`, so incoming order does not matter. For simple products it is `default`. The database enforces uniqueness on `product_id + combination_key`.

Product specifications are stored in normalized typed tables:

- `product_specifications` stores one row per product and specification attribute, with typed scalar columns for text, number, and boolean values.
- `product_specification_values` stores selected predefined values for select and multiselect specifications.

This avoids generic JSON attribute storage and keeps future filtering/indexing practical. Physical measurements may use decimal specification values; the integer minor-unit money rule applies to money only.

Category Attribute Sets control valid product attributes. Product creation requires an active leaf category with active ancestors. Variant products require a category with an Attribute Set containing variant attributes. Simple products may use categories with or without an Attribute Set.

Draft product creation may be incomplete in the areas that belong to future submission workflows. `App\Services\ProductSubmissionValidator` checks readiness for the future approval submission step, including approved vendor, leaf category, required specifications, simple default variant shape, variant completeness, SKU presence, valid pricing, and required variant attributes.

Product media lives in `product_images`. A row belongs to a product and may optionally belong to one product variant. Rows with `product_variant_id = null` are the product gallery; rows with a variant ID are variant-specific images. Product gallery images can have one explicit `is_primary` image, enforced by application actions. Variant images do not use `is_primary`; the first image by `sort_order` is the variant default.

Vendor media routes are under `/vendor/products/{product}/images` and require the normal vendor product access stack: `auth`, `role:vendor`, `vendor.approved`, and `ProductPolicy::manage`. Media actions validate that variant images reference a variant from the same product. Pending products and archived products lock all media changes. Media changes on approved products reset the product to draft status and clear approval metadata, requiring reapproval; rejected products remain rejected until resubmitted.

Uploaded product media uses Laravel's configured product media disk, defaults to `public`, stores relative paths under `products/{product_id}/images`, and generates storage filenames instead of trusting user filenames. Public uploads accept only JPG, PNG, and WebP raster images up to the configured size. Current limits are configured in `config/marketplace.php`: eight product gallery images and three images per variant. Demo seed media is developer-owned static artwork and is separate from user upload validation.

The submission validator requires an explicit product-level primary gallery image before a product can be submitted or approved. Variant images are optional.

## Inventory And Stock Management

Inventory belongs to the unified sellable entity: `ProductVariant`. Simple products and variant products use the same stock architecture because simple products already have one default variant.

Each non-deleted `ProductVariant` has one `Inventory` row. The balance stores `on_hand`, `reserved`, and `low_stock_threshold`. Available stock is not persisted; it is calculated consistently as:

- `available = on_hand - reserved`

Application logic prevents negative stock, negative reserved quantity, and `reserved > on_hand`. Vendors cannot manually edit `reserved`; it is reserved for future checkout/order flows.

Stock status is derived from available quantity:

- Out of Stock: `available <= 0`
- Low Stock: `available > 0` and `available <= low_stock_threshold`
- In Stock: positive available stock above the low-stock threshold

If `low_stock_threshold` is `0`, positive available stock is not considered low stock.

All on-hand stock changes create immutable `InventoryMovement` rows in the same database transaction as the balance update. Movement quantity is signed: increases store positive values, decreases store negative values, and adjustments store the difference between the previous and new physical count. Before/after quantities are computed server-side from the locked inventory row, never accepted from browser input.

Manual vendor stock operations are explicit:

- Increase stock
- Decrease stock
- Adjust physical count
- Update low-stock threshold

Threshold updates do not create movement records because they do not change stock quantity. Stock changes use `lockForUpdate()` inside a transaction before validating and updating the balance. Decreases and adjustments may not reduce on-hand stock below reserved stock.

Inventory is operational data, not moderated product content. Approved vendors can manage stock for draft, pending, rejected, and approved products they own. Archived products keep inventory visible but block manual inventory changes. Stock changes do not reset product approval, do not alter `ProductStatus`, and are not required for product submission or admin approval. An approved product may have zero stock; future storefront behavior should show it as unavailable rather than changing listing approval state.

Vendor inventory lives under `/vendor/inventory` and product/variant inventory routes. Product ownership is enforced through `ProductPolicy::manage`, and route actions verify that the variant belongs to the routed product. Admin inventory lives under `/admin/inventory` as a read-only marketplace-wide operational view.

Future checkout will system-manage `reserved`: reserving stock increases reserved quantity, release decreases it, and sale commitment decreases both on-hand and reserved. Future order integration may extend movement types with reservation, release, sale, and return operations. Current inventory is one aggregate balance per `ProductVariant`; multi-warehouse support may later replace or extend this balance layer deliberately.

Product type is immutable after creation. Vendor ownership is immutable through normal product actions. Product category is also protected once variants or specifications exist because category changes can invalidate Attribute Set interpretation. Brand may change through normal product update rules.

Changing an Attribute Set later does not automatically rewrite existing product variants or specifications. Existing stored rows remain the product's current configuration; future product edit flows must validate carefully before removing or changing set assignments used by products.

Products and variants use soft deletes. Soft-deleting a product soft-deletes its variants. Variant SKUs and product slugs remain reserved because future orders and imports must not silently point to a different sellable item.

Future orders must snapshot historical details at order time rather than depending on live product records. Order items should snapshot product name, variant description, SKU, unit price, vendor, and selected attributes.

## Vendor Product Management And Moderation

Approved vendors manage their own products under `/vendor/products`. These routes require `auth`, `role:vendor`, and `vendor.approved`. Product ownership is enforced by `ProductPolicy::manage`; a vendor cannot view, update, submit, activate, deactivate, or archive another vendor's products even if they know the ID.

The vendor workflow is:

- create or edit a draft
- submit a complete product for review
- wait while the product is `pending`
- receive admin approval or rejection
- edit and resubmit rejected products

Product status and approval status have separate responsibilities:

- `ProductStatus` is the vendor publication intent: `draft`, `active`, `inactive`, `archived`
- `ProductApprovalStatus` is marketplace moderation: `draft`, `pending`, `approved`, `rejected`

Submitting a ready product sets `status = active` and `approval_status = pending`. Admin approval sets `approval_status = approved` and records `approved_at` / `approved_by`. Admin rejection sets `approval_status = rejected`, returns `status = draft`, and records the rejection reason and admin metadata. Activation and deactivation are explicit vendor actions for approved products only and do not reset approval. Archiving sets `status = archived` and keeps the record available for future historical references.

Pending products are locked from vendor edits until moderation resolves them. Approved product content edits intentionally reset `approval_status = draft`, `status = draft`, and clear approval metadata because changed listing content requires reapproval. Rejected products remain rejected while edited, preserving the rejection context until the vendor explicitly resubmits.

The product form is category-aware. Vendors first select an active leaf category whose ancestors are active. `/vendor/catalog/categories/{category}/configuration` returns the minimal Attribute Set configuration needed by the form: variant attributes, specification attributes, set-level required flags, sort order, and active predefined values. JavaScript uses this only for UX; server actions revalidate category, attribute, value, SKU, price, and combination integrity.

Simple products present one seller-facing SKU and price pair. Internally, that persists as a single default `ProductVariant` with `combination_key = default`.

Variant products let vendors choose offered values for category variant attributes. The UI generates cartesian combinations, but `SyncProductVariants` regenerates combination keys server-side, matches existing variants by `combination_key`, updates retained combinations, creates new combinations, soft-deletes removed combinations, and maintains exactly one default variant. This preserves `ProductVariant` IDs for unchanged combinations, which is important for inventory, carts, and future orders.

Specifications are synchronized through normalized `ProductSpecification` rows and `product_specification_values`; arbitrary specification JSON is not persisted. Inputs are rendered from the selected category's Attribute Set and validated by attribute input type.

Decimal price inputs are parsed at the HTTP boundary by `App\Support\Money` and stored as integer minor units. Controllers and views should use the same helper for formatting and input values instead of multiplying floats.

Admin moderation lives under `/admin/products` and `/admin/products/{product}`. Approval and rejection are owned by focused action classes; vendor requests cannot mass assign approval metadata such as `approved_by`, `approved_at`, `rejection_reason`, or `is_featured`.

Checkout, orders, promotions, commissions, and payouts remain separate future modules.

## Money Convention

Use integer minor units for money throughout application code and the database.

Examples:

- Store USD 19.99 as `1999`
- Store PKR 1500 as `150000` when using a 2-decimal minor unit convention
- Use signed or unsigned `bigInteger` columns depending on whether negative values are valid for that table

Never use floating point values for financial calculations. Formatting for display should happen at the presentation boundary. The default currency and minor unit live in `config/marketplace.php`.

This convention applies to product prices, discounts, taxes, shipping, commissions, vendor earnings, payouts, and refunds.

## Database Conventions

- Use foreign keys for relational business data.
- Add indexes for lookup and filtering columns.
- Add unique constraints where the business rules require uniqueness.
- Use integer minor-unit columns for money.
- Use timestamps on business tables unless there is a strong reason not to.
- Use soft deletes only where the business workflow requires recoverability or historical visibility.
- Avoid arbitrary JSON for relational business data. Use JSON only for flexible document-like data such as provider payloads, metadata snapshots, or UI configuration.

## Route Organization

- `routes/web.php`: public/customer-facing web routes and general Laravel web routes
- `routes/vendor.php`: vendor workspace routes under `/vendor` with `vendor.` route names, including approved-vendor product management
- `routes/admin.php`: admin operations routes under `/admin` with `admin.` route names, including catalog management and product moderation

The additional route files are loaded from `bootstrap/app.php`. Do not add functional module routes until the corresponding marketplace feature is being built.

## Public Storefront Foundation

The public homepage is served from `/` through `App\Http\Controllers\Storefront\HomeController` and the named `home` route. The storefront uses the existing Blade/Tailwind design system with reusable components under `resources/views/components/storefront`.

Public product visibility is centralized on `Product::publiclyVisible()` and currently requires:

- product `status = active`
- product `approval_status = approved`
- approved vendor
- active category and active practical ancestors
- at least one active variant

Inventory does not remove an otherwise public product from storefront results. Product cards derive a simple public state from loaded variant inventory:

- low stock
- out of stock
- in stock, usually unlabeled to keep the card quiet

Exact `on_hand`, `reserved`, and available quantities are operational data and must not be shown on public storefront cards. Prices come from active variants using `Product::storefrontPrice()` and are formatted through `App\Support\Money`.

The public header is marketplace-aware but intentionally limited. It provides real links for home, authentication, account dashboards, seller application, wishlist for customers, and cart for shoppers. Search, deals, best sellers, brands, and future discovery items remain visual placeholders only until their modules exist; they must not link to `#` or fake URLs.

Homepage sections are fed by real data only:

- active root categories and active first-level children
- approved active products from approved sellers
- approved vendor profiles
- static trust and seller call-to-action content

Product detail pages, category listings, search results, cart, wishlist, promotions, checkout, reviews, delivery promises, and sales analytics are handled by later focused modules.

## Product Discovery Engine

Public category browsing and search share `App\Services\Storefront\ProductDiscoveryService`. Controllers under `App\Http\Controllers\Storefront` validate public context, normalize the request through `ProductDiscoveryRequest`, and delegate filtering, sorting, filter metadata, and pagination to the service.

The public discovery routes are:

- `/category/{category:slug}` named `storefront.categories.show`
- `/search` named `storefront.search`

`/category/{slug}` is the canonical category URL. Query-string category IDs are not used as public browsing URLs.

Discovery reuses `Product::publiclyVisible()` as the central product visibility rule. Category pages additionally reject inactive, soft-deleted, or inactive-ancestor categories with `404`.

Discovery state is represented by GET parameters so filtered pages are shareable and bookmarkable. Example:

```text
/category/electronics-mobile-phones-smartphones?brand[]=apple&price_min=500&attributes[color][]=black&attributes[storage][]=256gb&in_stock=1&sort=price_asc
```

Supported public filters are:

- category, on search pages
- brand
- price min/max in major units, converted through `App\Support\Money`
- availability through inventory-derived in-stock state
- category-specific dynamic attributes

Dynamic filters follow the catalog configuration path:

```text
Category
    -> ProductAttributeSet
    -> filterable active ProductAttributes
    -> relevant active ProductAttributeValues
```

Leaf categories display filterable attributes from their assigned Attribute Set. Parent categories do not merge every descendant Attribute Set into one large sidebar; they show child category navigation plus global filters. This keeps public browsing context-aware instead of exposing unrelated controls.

Filter semantics are standard faceted search:

- same attribute values use OR semantics, such as Color = Black or White
- different attributes use AND semantics, such as Color = Black and Storage = 256GB
- variant attribute filters must match the same active sellable variant, preventing false positives from split combinations
- specification filters use normalized `product_specifications` and `product_specification_values`

Price filtering and price sorting use active `ProductVariant` effective price:

```text
effective price = sale_price ?? base_price
```

For products with multiple variants, price sorting uses the minimum active variant effective price as the product's public sortable price. Out-of-stock products remain discoverable by default and are excluded only when `in_stock=1` is selected. The in-stock filter checks for at least one active variant whose inventory has positive available quantity. Public UI never exposes exact `on_hand`, `reserved`, or available counts.

Search currently uses parameterized Eloquent `LIKE` queries over product name, short description, brand name, vendor/store name, and active variant SKU. Empty search does not execute a broad wildcard product query. The architecture can later move to Meilisearch, Typesense, Elasticsearch, Algolia, or another search index without changing the public URL contract.

## Public Vendor Storefront

Approved vendors receive one marketplace-controlled public storefront at:

```text
/store/{vendor:slug}
```

The route is named `storefront.stores.show`. Draft, pending, rejected, suspended, or unavailable vendors return `404`; public visitors should not learn internal moderation status from storefront URLs.

Storefront pages expose only public seller identity:

- display name and slug
- description
- logo and banner paths
- broad marketplace seller label
- created date when used as selling-since context

They must not expose tax numbers, registration numbers, private business address, private email, private phone, approval metadata, rejection reasons, internal notes, or moderation fields. Vendor uploads are rendered as content only; sellers cannot provide custom CSS, JavaScript, HTML, templates, or theme colors.

Store product browsing reuses `App\Services\Storefront\ProductDiscoveryService` with a mandatory vendor scope. The same central public visibility rule still applies:

```text
public product rules
    +
vendor_id = current store vendor
```

Store search uses the same product name, short description, brand, vendor/store, and active variant SKU matching as global discovery, but it never returns products from another vendor. Store filter URLs use the existing discovery parameters, including `q`, `category`, `brand[]`, `price_min`, `price_max`, `attributes[...]`, `in_stock`, and `sort`.

Store categories are derived from the vendor's public catalog with a grouped category query, not one count query per category. Category chips keep visitors inside the store, for example:

```text
/store/techverse?category=smartphones
```

Dynamic attribute filters appear only when the store context resolves to one public leaf category represented by that vendor's public products. Browsing the whole store or a parent category shows global filters only: category, brand, price, availability, and sort.

Seller navigation is now:

```text
Homepage StoreCard
    -> Vendor Store
    -> Product Detail
    -> Vendor Store
```

The cart is marketplace-wide. A customer cart may contain items from Vendor A, Vendor B, and Vendor C, while later order architecture can split fulfillment by vendor. Vendor storefront work does not implement checkout, orders, reviews, chat, following, coupons, or seller-specific policies.

## Wishlist

Wishlist is a customer-owned saved product relationship:

```text
Customer
    -> WishlistItem
    -> Product
```

`wishlist_items` stores `user_id`, `product_id`, and timestamps only. It does not store `product_variant_id`, quantity, notes, price snapshots, vendor snapshots, or notification preferences. A unique `user_id + product_id` constraint makes saving the same product idempotent.

Wishlist is available only to authenticated customers through `auth` plus `role:customer` routes:

- `/account/wishlist` named `account.wishlist.index`
- `POST /wishlist/{product}` named `wishlist.store`
- `DELETE /wishlist/{product}` named `wishlist.destroy`

Guests are redirected to login by normal web middleware. Vendors and admins do not receive customer wishlist access.

New saves are allowed only for products that currently pass `Product::publiclyVisible()`. Out-of-stock public products can be saved because wishlist does not reserve inventory. Saving or removing a wishlist item must not alter `on_hand`, `reserved`, inventory movements, product approval state, product status, or vendor moderation.

Previously saved products may remain in `wishlist_items` after they become inactive, archived, pending, rejected, hidden by category state, or hidden by vendor state. The account wishlist page checks public visibility in a batched query and renders unavailable saved rows as a generic state without product name, seller state, rejection reason, or other private details.

Product cards accept precomputed wishlist state. Controllers and services pass batched `wishlistedProductIds` into homepage, category, search, vendor store, and related-product views; the card component does not query `WishlistItem` per product. The public header receives the customer wishlist count through one view composer query per request.

Wishlist and cart intentionally use different sellable scopes:

```text
Wishlist -> Product
Cart     -> ProductVariant
```

A saved product goes through product detail and variant selection before it can become a cart line. Wishlist may later support price-drop or back-in-stock alerts, but it does not implement notifications, analytics, sharing, folders, reviews, checkout, or orders.

## Shopping Cart

The cart is a marketplace-wide shopping container. A single cart may contain products from multiple approved vendors; vendor-specific fulfillment will be split later during checkout/order creation, not at cart time.

Cart ownership supports two shopper states:

- guests own a cart through a random session token stored in `cart_token`
- authenticated customers own one persistent cart through `carts.user_id`

Vendors and admins are not shoppers for cart purposes. Cart routes use the `shopper` middleware, which allows guests and customer-role users while returning `403 Forbidden` for authenticated vendors and admins.

Cart lines reference `product_variant_id`, never a product ID, because `ProductVariant` is the sellable entity that owns SKU, current price, selected attributes, and inventory. Duplicate variant additions increment the existing line quantity, subject to `marketplace.cart.max_quantity_per_item`.

Cart pricing is live and intentionally not snapshotted. Item displays and subtotals use the variant's current effective price:

```text
effective price = sale_price ?? base_price
```

The cart does not reserve or deduct inventory. Add/update requests validate against current available stock, but persisted cart lines remain lightweight. The cart page derives current line validity each time it renders:

- product unavailable
- variant unavailable
- out of stock
- insufficient stock
- valid

Unavailable product rows remain in the cart until the shopper removes them, but the page hides private or non-public product details and excludes unavailable lines from the active subtotal. Out-of-stock or insufficient-stock lines remain visible with current public product details and clear issue messaging.

Guest carts merge into the authenticated customer's cart after successful login or customer registration. Matching variant rows are combined and capped by the configured per-line maximum. Merge does not mutate inventory, product approval state, vendor state, or price data.

Current cart routes are:

- `/cart` named `cart.index`
- `POST /cart/items` named `cart.items.store`
- `PATCH /cart/items/{cartItem}` named `cart.items.update`
- `DELETE /cart/items/{cartItem}` named `cart.items.destroy`
- `DELETE /cart` named `cart.clear`

Shipping, taxes, coupons, payments, order splitting, reservations, and order records are future modules.

## Customer Address Book

Customer delivery addresses are private customer-owned records:

```text
User (customer)
    -> CustomerAddress
```

Addresses are stored in `customer_addresses` and are related to `users.id`. Address ownership is always derived from the authenticated customer; browser requests never provide or override `user_id`. Account address routes require `auth` plus `role:customer`, and cross-customer address access returns `404` to avoid revealing whether another customer's address exists.

A customer may save multiple addresses, up to `marketplace.checkout.max_customer_addresses`. Normal application actions maintain at most one default address:

- the first saved address automatically becomes default
- explicitly setting an address as default clears other defaults
- deleting the default address promotes the most recently updated remaining address
- deleting the last address leaves no default

Addresses are mutable operational customer data and do not use soft deletes. Future orders must snapshot delivery address details into immutable order-owned fields at order creation time; historical fulfillment must not depend on a mutable `CustomerAddress` row.

The address model is internationally flexible without introducing a country database module. `country_code` stores ISO 3166-1 alpha-2 uppercase codes validated against `marketplace.checkout.supported_countries`. State/region and postal code remain flexible strings because requirements vary by country.

## Checkout Foundation

Checkout currently implements only the first customer checkout foundation:

```text
Cart
    -> EvaluateCart
    -> authenticated Customer
    -> CustomerAddress
    -> Checkout Session
```

Guests may keep carts, but checkout requires an authenticated customer. A guest who visits `/checkout` is redirected to login through Laravel's intended URL flow. After customer login or registration, the existing guest-to-customer cart merge runs before checkout evaluates the merged customer cart.

Checkout reuses `App\Services\Cart\EvaluateCart` as the cart validation source. Every checkout GET and address selection/create request re-evaluates the current cart. Checkout redirects back to `/cart` when the cart is empty or when any line has a blocking issue such as unavailable product, unavailable variant, out of stock, or insufficient stock. It does not trust a previous page load.

The checkout session currently stores only:

```text
checkout.address_id
```

The selected address ID is resolved through the authenticated customer's own addresses. If the session address no longer belongs to the customer or no longer exists, the key is cleared and selection falls back to the default address, then the first available address. Selecting an address for checkout does not change the customer's saved default address.

Checkout review shows the current cart grouped by vendor and displays exact `ProductVariant` rows, current effective prices, SKU, quantities, selected delivery methods, and seller grouping. Quantities are not editable in checkout; shoppers return to `/cart` to change cart contents.

Checkout does not reserve or deduct inventory and does not create inventory movements. It also does not create orders, order items, vendor orders, address snapshots, payment records, taxes, or coupons.

Future checkout flow should remain:

```text
Cart
    -> Checkout
    -> Delivery Address
    -> Vendor Shipping Groups
    -> Shipping Selection
    -> Final Validation
    -> Inventory Reservation
    -> Order Creation
    -> Payment
```

Shipping must be designed per vendor or fulfillment group. A multi-vendor cart may require separate origins, methods, costs, and delivery promises, so future architecture must not assume one global shipping method or one global shipping charge.

## Shipping Foundation

Shipping methods are vendor-owned configuration:

```text
Vendor
    -> ShippingMethod
    -> ShippingMethodCountry
```

`ShippingMethod` belongs to one vendor. Vendors manage their own methods under `/vendor/shipping-methods`, and ownership checks prevent one vendor from editing or deleting another vendor's methods. Approved vendors only can manage shipping methods through the existing vendor dashboard protection. Admins receive a read-only oversight index at `/admin/shipping-methods`; admin editing is intentionally not part of this module.

The MVP method type is:

```text
flat_rate
```

Flat rates and free-shipping thresholds are stored as integer minor units in the marketplace currency. Future types such as table-rate, weight-based, courier-calculated, or distance-based shipping can be introduced behind the existing method type enum and calculator, but no strategy framework is added while only flat rate exists.

Country coverage is explicit and uses the same ISO alpha-2 supported country configuration as checkout addresses:

```text
ships_to_all_supported_countries = true
    -> available for any configured checkout country

ships_to_all_supported_countries = false
    -> available only for rows in shipping_method_countries
```

Inactive methods are never available in checkout, but their coverage rows may remain stored. Hard deletion is currently acceptable because orders do not exist yet; future orders must snapshot method details.

Checkout treats shipping as vendor-scoped:

```text
Checkout
    -> Vendor A -> ShippingMethod A -> Shipping Charge A
    -> Vendor B -> ShippingMethod B -> Shipping Charge B
```

Shipping eligibility uses the selected `CustomerAddress.country_code`, not the user's profile or vendor profile. Checkout loads active methods for all current cart vendor IDs in one query with country coverage, groups them in memory by vendor, and calculates rates from the already-computed cart vendor subtotal. Blade receives prepared view data only.

Default checkout selection is deterministic and non-destructive:

1. keep the valid session-selected method for that vendor
2. otherwise select the first valid method by `sort_order ASC, id ASC`

The checkout session stores IDs only:

```text
checkout.address_id
checkout.shipping_methods[vendor_id] = shipping_method_id
```

It stores no prices, method names, product IDs, vendor names, or calculated totals. Every checkout render and shipping update revalidates selections against the current cart, current vendor groups, selected address country, active methods, and coverage. Stale selections for removed vendors, deleted methods, inactive methods, or address-incompatible methods are cleared or replaced by a valid deterministic default. If a vendor group has no valid method, checkout displays a delivery-unavailable state for that seller group.

Free shipping thresholds apply only to that vendor's merchandise subtotal:

```text
Vendor shipping charge =
    0 when vendor_subtotal >= method.free_shipping_threshold
    otherwise method.flat_rate
```

The whole-cart subtotal cannot make another vendor's shipping free.

Checkout totals now include:

```text
current_total = items_subtotal + shipping_total
```

Taxes, coupons, payment fees, discounts, and final payable amount are not implemented yet. Current total is a truthful foundation total for merchandise plus selected delivery charges only.

Shipping selection does not reserve stock, deduct stock, or create `InventoryMovement` rows. Future order creation must snapshot shipping method name, code, charge, delivery estimate, and delivery address per vendor fulfillment/order group. Courier-calculated shipping will also need vendor or warehouse origins plus product or variant weight/dimensions; those are deliberately outside the flat-rate foundation.

## Order Foundation

Orders introduce the first commercial transaction boundary:

```text
Order
    -> VendorOrder
        -> OrderItem
            -> InventoryReservation(active)
```

`Order` is the customer-level marketplace purchase. It stores the public `order_number`, customer, marketplace status, payment status, currency, merchandise subtotal, shipping total, grand total, placement time, and delivery address snapshot. The public order number is generated server-side in the form `ORD-YYYYMMDD-RANDOM`, has a unique database constraint, and is separate from the sequential database ID.

`VendorOrder` is the seller fulfillment boundary under the parent order. One marketplace order can contain multiple vendor orders. Each vendor order stores the vendor, seller subtotal, seller shipping total, seller grand total, seller status, child `vendor_order_number`, lifecycle timestamps, cancellation metadata, and shipping method snapshot. Each seller portion moves independently; parent `Order.status` is derived from all vendor orders and is not directly mutated by vendors.

`OrderItem` is the immutable purchased variant snapshot. It stores nullable traceability references to `product_id` and `product_variant_id`, but historical rendering uses only snapshots:

- product name
- variant description
- SKU
- selected variant attributes
- quantity
- unit price
- line total

The central rule is:

```text
Cart     -> live catalog data
Checkout -> revalidated live catalog, address, and shipping data
Order    -> immutable historical snapshots
```

Orders snapshot product name, SKU, variant attributes, effective price, shipping method, shipping charge, delivery estimate, delivery address, and currency. Later product, variant, shipping method, or address edits must not alter existing order history.

All order money uses integer minor units:

```text
order.grand_total = order.items_subtotal + order.shipping_total
vendor_order.grand_total = vendor_order.items_subtotal + vendor_order.shipping_total
```

The parent totals must equal the sum of vendor order totals. No tax, coupon, payment fee, commission, payout, refund, or fulfillment math exists in this module.

Order placement is handled by a single transaction-focused action. The browser submits only `idempotency_key`; it does not submit totals, prices, vendor groups, address snapshots, shipping rates, or item data. Inside the database transaction the action:

1. revalidates the authenticated customer cart
2. resolves the selected customer address
3. revalidates selected active shipping methods and country coverage
4. recalculates item prices and per-vendor shipping
5. locks required inventory rows with `SELECT ... FOR UPDATE`
6. rechecks available stock after locking
7. creates the parent order
8. creates one vendor order per seller
9. creates immutable order item snapshots
10. increments `inventories.reserved`
11. creates one active inventory reservation per order item
12. deletes the exact purchased cart item IDs

Inventory rows are locked in deterministic `product_variant_id ASC` order to reduce deadlock risk and avoid locking unrelated inventory rows.

Inventory reservation semantics:

```text
on_hand = physical stock
reserved = allocated stock
available = on_hand - reserved
```

Order placement performs:

```text
reserved += ordered_quantity
```

It does not reduce `on_hand`. Physical deduction and reservation commit belong to a later fulfillment module.

`InventoryReservation` records are the lifecycle foundation for reserved stock. Current placement creates only `active` reservations. Future modules can move reservations:

```text
active -> committed
active -> released
```

Commit converts a reservation into actual stock deduction during seller shipment. Release returns availability for pre-shipment seller cancellation. Failed payment expiry, customer cancellation, refund, and return rules remain future work.

Checkout idempotency stores a secure session token tied to cart composition, selected address, and selected shipping methods. Repeated `POST /checkout/place-order` submissions with the same key reuse the existing order instead of creating duplicate orders or reservations. After successful order placement, checkout address, shipping selections, signature, and idempotency key are cleared. Failed placement leaves useful checkout state and cart items intact.

Current payment boundary:

```text
Place Order
    -> pending Order
    -> pending payment_status
    -> active InventoryReservation
```

No payment is faked. Payment records and Cash on Delivery behavior are handled by the payment foundation below, while reservation expiry/release policy for unpaid terminal failures remains future work.

## Payment Foundation

Payment is separate from Order:

```text
Order
    -> Payment
        -> PaymentAttempt
```

`Order` remains the commercial purchase and contains immutable marketplace snapshots. `Payment` represents the marketplace-level money collection state for the parent order. `PaymentAttempt` records individual collection attempts so future online gateways can retry without overwriting history.

The current MVP supports one primary payment per marketplace order:

```text
Marketplace Order
    -> one Payment
    -> many VendorOrders
```

Payments are not created per vendor order. Customers pay the marketplace order total, and future commission, seller earnings, and payouts will derive from `VendorOrder` and `OrderItem` commercial snapshots after the appropriate payment state exists.

Provider independence rules:

- `orders` contains no Stripe, PayPal, wallet, or bank gateway columns
- provider references are stored generically on `payments.provider_reference` and `payment_attempts.provider_reference`
- provider-specific behavior lives behind `App\Services\Payments\PaymentProvider`
- `Order` and Eloquent models do not call payment SDKs or external APIs

The enabled payment methods are configured in `config/payments.php`. Checkout receives public-safe method presentation data from `AvailablePaymentMethods` and currently exposes:

```text
cash_on_delivery
card
```

The `card` method is available only when Stripe is enabled, publishable and secret keys exist, and the marketplace currency is supported.

Cash on Delivery lifecycle:

```text
Place Order
    -> Order.status = pending
    -> Payment.method = cash_on_delivery
    -> Payment.provider = cash_on_delivery
    -> Payment.status = pending
    -> PaymentAttempt #1 = pending
    -> InventoryReservation = active
```

COD is not automatically paid. Money is collected later during fulfillment/delivery, so there is no admin or vendor "mark paid" UI in this module.

Payment amount and currency are copied from the parent order at payment creation:

```text
Payment.amount = Order.grand_total
Payment.currency = Order.currency
```

The browser never submits payment amount or currency. Product, shipping, and address changes after order placement do not mutate the payment snapshot.

Payment status synchronization:

```text
Payment.status
    -> authoritative payment-domain state

Order.payment_status
    -> synchronized order summary
```

Domain actions update both records transactionally. Payment status and order fulfillment status are separate lifecycles: a paid payment does not imply a completed order, and an order workflow state must not be inferred from payment alone.

Checkout stores only the selected method code:

```text
checkout.payment_method = cash_on_delivery|card
```

No payment totals, provider responses, secrets, or sensitive customer payment details are stored in session. The place-order idempotency signature includes cart composition, selected address, selected shipping methods, and selected payment method. Successful order placement clears checkout address, shipping methods, payment method, signature, and idempotency key. Failed placement keeps useful state where safe.

For COD, payment creation happens inside the existing order placement database transaction. If payment or attempt creation fails, the order, vendor orders, order items, inventory reservations, inventory reserved increments, and cart clearing all roll back together.

Online gateway boundary:

```text
DB transaction:
    create Order
    reserve Inventory
    create Payment

After transaction:
    initialize external gateway
    redirect customer when needed

Verified provider callback/webhook:
    transition Payment
    synchronize Order.payment_status
```

External payment API calls must not run while holding inventory row locks. Webhooks verify provider signatures, find payments by provider/reference, handle duplicate events idempotently, and never treat a browser redirect as proof of payment.

Stripe card payment uses the official `stripe/stripe-php` SDK on the server and hosted Stripe.js from `https://js.stripe.com/v3/` in Blade. The app creates PaymentIntents after the order transaction commits:

```text
Checkout submit
    -> DB transaction creates pending Order, VendorOrders, OrderItems, active InventoryReservations, Payment, PaymentAttempt
    -> transaction commits
    -> Stripe PaymentIntent is created/retrieved
    -> client_secret is returned to Stripe.js
    -> Stripe webhook confirms final payment state
```

Card order initial state is intentionally unpaid and fulfillable only after webhook confirmation:

```text
Order.status = pending
Order.payment_status = pending
Payment.method = card
Payment.provider = stripe
Payment.status = pending
PaymentAttempt.status = pending
InventoryReservation.status = active
```

Webhook event receipts are stored in `payment_webhook_events` with a unique `(provider, provider_event_id)` constraint. Stripe success handling validates amount and currency before marking a payment paid. Failed or canceled Stripe attempts update the attempt record, but the overall payment remains pending and retryable; unpaid order cancellation and reservation release are deferred to the pending-payment expiry module.

Customers may retry eligible unpaid card orders from their order detail without creating another Order or InventoryReservation. Admins can see provider references, attempts, failure details, and webhook receipts. Customers and vendors do not see full gateway internals or secrets.

## Online Payment Expiry

Online card payments use a fixed reservation window stored on `payments.expires_at`. The default is configured by `payments.online_payment_expiry_minutes` and can be overridden with `ONLINE_PAYMENT_EXPIRY_MINUTES`; the current default is 30 minutes.

Cash on Delivery is excluded from online payment expiry:

```text
Payment.method = cash_on_delivery
Payment.expires_at = null
```

Card payment lifecycle:

```text
Card Order created
    -> Payment pending
    -> InventoryReservation active
    -> Payment.expires_at reached
    -> Order cancelled
    -> VendorOrders cancelled
    -> InventoryReservation released
    -> Inventory.reserved decreases
```

Retries do not extend `payments.expires_at`. This prevents repeated payment retries from holding stock indefinitely. A future policy may extend expiry for provider-confirmed flows such as long-running 3DS or asynchronous payment processing, but this MVP keeps the original payment window authoritative.

Cancellation is a domain operation handled by `App\Actions\Orders\CancelOrder` with eligibility centralized in `CanCancelOrder`. Customer cancellation is limited to the customer's own unpaid online card orders before shipment. COD customer cancellation, paid order cancellation, automatic refunds, returns, notifications, commissions, and seller earnings are not implemented in the cancellation workflow.

The cancellation transaction:

```text
lock Order
lock Payment
validate unpaid online cancellable state
release active seller reservations using ReleaseInventoryReservationsForVendorOrder
cancel VendorOrders
cancel Payment
cancel parent Order
commit
```

Reservation release changes only allocated stock:

```text
InventoryReservation: active -> released
Inventory.reserved -= reservation.quantity
Inventory.on_hand unchanged
```

No `InventoryMovement` is created because the movement ledger tracks physical `on_hand` changes. Original order totals, seller totals, item prices, payment amount, and payment currency remain unchanged after cancellation.

Cancelled orders do not rebuild the customer's cart. A new purchase must go through the live catalog/cart/checkout path so current prices, availability, and shipping rules are revalidated.

Expired unpaid orders are processed by the scheduled command:

```text
php artisan orders:expire-unpaid
```

It runs every minute through Laravel's scheduler with `withoutOverlapping()`, scans eligible expired online payments in batches, calls the cancellation action per order, reports expired/skipped/failed counts, and logs per-order failures without stopping the whole batch. In multi-server production scheduling, this should be paired with an appropriate shared cache and `onOneServer()` policy if multiple scheduler workers are introduced.

Auto-expiry skips paid payments, processing payments, COD payments, unexpired payments, completed/cancelled orders, and orders with shipped or delivered seller orders. Processing payments require a future reconciliation command if they remain unresolved for too long.

Late Stripe success after local cancellation is treated as an operational financial exception:

```text
Order remains cancelled
VendorOrders remain cancelled
reservations stay released
Payment becomes paid for truthful money state
Payment.requires_review_at is set
Payment.review_reason explains refund review requirement
```

The app does not call Stripe to cancel PaymentIntents during local expiry, and cancellation does not automatically issue refunds. The refund module handles collected-money reversals as explicit admin-controlled financial events.

Sensitive payment data must never be stored:

- card numbers
- CVV
- bank passwords
- provider access tokens
- gateway secrets
- raw provider payloads containing credentials or private payment data

Post-delivery payment behavior, refund automation, commission, and vendor earnings remain separate. Pending unpaid orders may expire, cancel, and release active reservations. Explicit refunds are separate financial records and must not rewrite historical order item prices.

## Order Fulfillment Lifecycle

Vendor fulfillment uses explicit domain transitions through `App\Actions\Orders\Fulfillment\TransitionVendorOrderStatus`. Controllers and forms do not accept an arbitrary target status dropdown. The current seller lifecycle is:

```text
pending -> confirmed -> processing -> ready_to_ship -> shipped -> delivered
```

Seller cancellation is allowed only before shipment:

```text
pending|confirmed|processing|ready_to_ship -> cancelled
```

`shipped`, `delivered`, and `cancelled` are terminal for cancellation in this MVP. Once a seller order has shipped, future post-shipment problems must go through refund and later Returns/RMA workflows instead of reversing the current cancellation path.

Fulfillment endpoints live under the approved vendor area:

```text
POST /vendor/orders/{vendorOrder}/confirm
POST /vendor/orders/{vendorOrder}/processing
POST /vendor/orders/{vendorOrder}/ready-to-ship
POST /vendor/orders/{vendorOrder}/ship
POST /vendor/orders/{vendorOrder}/deliver
POST /vendor/orders/{vendorOrder}/cancel
```

Vendor ownership is checked before mutations and cross-vendor route-model access returns `404` to avoid leaking another seller's order number. Customer and admin roles cannot call vendor fulfillment endpoints. Admin order views remain read-only in this module.

Inventory reservation lifecycle is:

```text
Order placement:
on_hand unchanged
reserved += ordered quantity
InventoryReservation = active

VendorOrder shipped:
on_hand -= reserved quantity
reserved -= reserved quantity
InventoryReservation = committed
InventoryMovement(type = order_fulfillment, quantity < 0) created

Pre-shipment cancellation:
on_hand unchanged
reserved -= reserved quantity
InventoryReservation = released
```

`ready_to_ship` still keeps inventory reserved. `delivered` does not change inventory, because the physical stock deduction already happened at shipment. Reservation commit and release actions run inside database transactions, lock reservation and inventory rows with `lockForUpdate()`, and lock inventory records in deterministic `inventory_id ASC` order for multi-item vendor orders. Shipment is atomic: all reservations commit, all inventory rows update, all movement rows are created, and the vendor order becomes shipped, or none of those changes persist.

`InventoryMovement` remains the immutable ledger for `on_hand` changes. Releasing a reservation does not create a movement because it changes `reserved`, not physical stock. Shipment movements use `order_fulfillment` with a negative quantity, before/after on-hand quantities, the vendor order as the reference, and the vendor user as `created_by`.

Parent order aggregation is centralized in `RecalculateOrderStatus` after every seller transition:

- all vendor orders pending -> `pending`
- all vendor orders confirmed -> `confirmed`
- any active mixed fulfillment state -> `processing`
- all vendor orders delivered/cancelled with at least one delivered -> `completed`
- all vendor orders cancelled -> `cancelled`

This preserves multi-vendor reality. A parent order can remain `processing` while one seller is delivered and another seller is still preparing fulfillment.

Cancellation does not rewrite original commercial snapshots:

```text
orders.* totals
vendor_orders.* totals
order_items.unit_price / line_total
payments.amount
```

Future financial adjustment/refund records should represent cancellation money consequences. Fulfillment also does not mutate `Payment` or `PaymentAttempt`: Cash on Delivery can move through seller fulfillment while payment remains `pending`, card payments must be `paid` before seller fulfillment can advance, and seller delivery is not payment proof.

Current fulfillment unit:

```text
VendorOrder = shipment unit
```

Partial shipments, tracking numbers, couriers, shipment labels, `Shipment`/`ShipmentItem` records, customer cancellation, notifications, returns, refunds, commissions, vendor earnings, and payouts are intentionally deferred.

## Refunds And Financial Adjustments

Refunds are modeled as append-only financial events. They never rewrite original commercial snapshots such as `orders.grand_total`, `vendor_orders.grand_total`, `order_items.unit_price`, `order_items.line_total`, or `payments.amount`.

Current refund structure:

```text
Payment
    -> Refund
        -> RefundAllocation
```

`Refund` belongs to both `Payment` and `Order` for operational lookup, while `Payment` remains the financial parent. One payment may have many refunds. `refund_number` is the public operational identifier; sequential database IDs are not used in admin/customer-facing URLs.

`RefundStatus` is independent from order and payment status:

```text
pending -> processing -> succeeded
pending -> failed
pending -> cancelled
```

Succeeded, failed, and cancelled are terminal. A failed retry is represented by a new refund record, not by mutating the failed refund back to pending.

`App\Services\Refunds\RefundFinancials` centralizes payment summary math:

```text
original_amount = payments.amount
refunded_amount = sum(succeeded refunds)
pending_refund_amount = sum(pending/processing refunds)
refundable_amount = original - refunded - pending
net_paid_amount = original - refunded
```

Pending and processing refunds protect capacity so concurrent admin requests cannot over-refund the same payment. `App\Actions\Refunds\CreateRefund` locks the payment row, validates current capacity, creates a pending refund, and creates normalized allocations inside one transaction. Stripe or other provider calls happen later through `ProcessRefund`, outside the locked transaction.

Refund allocations explain what commercial component a refund is attributed to:

- `item`: tied to an `OrderItem` and its owning `VendorOrder`; quantity-based refunds must equal quantity times original unit price.
- `shipping`: tied to a `VendorOrder` shipping snapshot.
- `adjustment`: an explicit financial adjustment. If it has no `vendor_order_id`, it is marketplace-level.

Allocation totals must equal the refund amount. Item allocations cannot exceed original line totals or original quantity after successful and protected in-flight allocations. Shipping allocations cannot exceed the seller order's original shipping total after successful and protected in-flight allocations. Vendor attribution prepares future commission and ledger work, but it does not yet decide who financially absorbs the refund.

Only admins create refunds in Prompt 25. Customers can see safe refund history for their own orders. Vendors can see only successful allocations attributed to their own seller orders. Provider references, failure details, internal notes, webhook records, and other vendors' allocations are admin-only.

Stripe refunds use the existing payment provider manager plus `RefundablePaymentProvider`. Stripe receives only locally authoritative values: the stored PaymentIntent reference, local refund amount, and minimal metadata. Idempotency uses:

```text
payment-{payment_id}-refund-{refund_id}
```

Refund webhook reconciliation reuses `payment_webhook_events` for provider event deduplication. Verified `refund.*` events mark refunds succeeded/failed/processing through central actions. Duplicate success events are idempotent and stale failure events cannot downgrade a succeeded refund.

Payment refund status is derived from successful refunds:

```text
paid -> partially_refunded -> refunded
```

`orders.payment_status` is synchronized from `payments.status`. Refunds do not change `orders.status` or `vendor_orders.status`.

Refunds do not mutate inventory, reservations, or inventory movements. A refund is money movement; physical return, inspection, restock, and any resulting inventory movement belong to a future Returns/RMA module.

Late Stripe success after cancellation from Prompt 24 is now resolvable by admin refund. The cancelled order remains cancelled, released inventory remains released, and a successful full refund changes the payment to `refunded` and sets `payments.review_resolved_at`.

Deferred financial areas remain separate: automatic refunds, customer refund requests, returns/RMA, chargebacks/disputes, vendor ledger, vendor earnings, payouts, Stripe Connect, notifications, and credit notes.

## Commission Engine

Marketplace commission is percentage-only and stored as integer basis points. Fixed fees, category/product-specific rates, manual commission adjustments, seller balances, ledgers, payouts, and Stripe Connect are intentionally outside Prompt 26.

Commission rule resolution is deliberately small:

```text
active vendor override -> active global default
```

The seeded global default is `10.00%` with `include_shipping=false`. Vendor overrides are managed by admins at `/admin/commission-rules`; rule changes affect only future recognition because every recognized commission stores an immutable snapshot of the applied rule values.

Commissions are recognized per `VendorOrder`, never from the parent order total. A `VendorOrderCommission` is created only when the seller order is delivered and the marketplace payment is collected:

```text
vendor_orders.status = delivered
payments.status in paid, partially_refunded, refunded
```

Unpaid COD, pending card payments, cancelled seller orders, and undelivered seller orders do not recognize commission. Delivery transitions call `RecognizeVendorOrderCommission`, and `php artisan commissions:reconcile` can backfill any missing eligible records in chunks.

The commission snapshot stores:

- applied rule and rate basis points
- whether shipping was commissionable
- original item and shipping seller-order amounts
- recognized commissionable basis after any successful pre-recognition seller refund allocations
- original commission amount and currency
- recognition timestamp

Successful seller-attributed refunds after recognition create signed `CommissionAdjustment` rows. Item refunds reduce commission. Shipping refunds reduce commission only when the commission snapshot included shipping. Pending, failed, cancelled, marketplace-level, and duplicate refund allocations do not affect seller commission. A unique `refund_allocation_id` makes refund webhook retries idempotent.

Admin reporting lives at `/admin/commissions` and `/admin/commissions/{commission}`. Vendors have a read-only scoped list at `/vendor/commissions`. Customers do not see marketplace commission internals.

## Vendor Ledger And Earnings

Vendor earnings are represented by immutable `VendorLedgerEntry` rows. There is no authoritative `vendors.balance` column. Seller balances are currency-specific sums of ledger entries:

```text
positive amount = Vendor credit
negative amount = Vendor debit
total balance = SUM(vendor_ledger_entries.amount)
```

The implemented entry types are:

- `sale`: gross seller commercial value for a delivered paid `VendorOrder`; this includes item subtotal and seller shipping.
- `commission`: marketplace commission debit from the recognized `VendorOrderCommission.original_commission_amount`.
- `refund`: successful seller-attributed `RefundAllocation` debit for item or shipping refunds.
- `commission_refund`: positive credit generated only from an actual `CommissionAdjustment`.

Ledger entries are append-only in application behavior. Controllers do not expose create, update, or delete routes for ledger entries. Corrections should be future compensating entries with explicit audit rules, not edits to old rows.

Every entry has a server-generated unique `source_key` for idempotency:

```text
vendor-order:{id}:sale
vendor-order-commission:{id}:commission
refund-allocation:{id}:refund
commission-adjustment:{id}:commission-refund
```

`available_at` is authoritative for maturity. No mutable pending/available status is stored. Available balance is the sum of entries with `available_at <= now()`, and pending balance is the sum of entries with a future `available_at`. Negative available balances are allowed because refunds, future chargebacks, or future payouts may reduce funds after earlier money became available.

The default vendor earnings hold is configured by `marketplace.vendor_earnings_hold_days`, backed by `VENDOR_EARNINGS_HOLD_DAYS`, and defaults to 7 days. Sale and commission entries share the same hold date, based on `VendorOrder.delivered_at + hold_days`. Existing ledger rows keep their original `available_at` when the configured hold period changes.

Refund and commission-refund entries become available immediately at the refund's `processed_at` timestamp, or `now()` if no processed timestamp is present. Seller-attributed refunds are posted independently from commission recognition, including refunds that occurred before delivery. This preserves the complete economic history:

```text
Sale Credit             +10000
Refund Debit             -2000
Commission Debit          -800
```

The ledger posting flow is recoverable:

```text
VendorOrder delivered
    -> Commission recognized
    -> Sale Credit + Commission Debit posted

Refund succeeded
    -> Refund Debit posted
    -> CommissionAdjustment created when applicable
    -> Commission Refund Credit posted
```

If ledger posting fails after commission or refund truth is saved, the source record remains committed. `php artisan vendor-ledger:reconcile` posts missing sale, commission, refund, and commission-refund entries from authoritative source snapshots using chunked idempotent processing. It does not recalculate commission rules or rewrite history.

Vendor UI exposes read-only balances and ledger activity at `/vendor/earnings`, scoped to the authenticated approved vendor. Admin UI exposes a vendor financial view at `/admin/vendors/{vendor}/financials` and an operational ledger index at `/admin/vendor-ledger`. Customers have no ledger access.

Balances are not combined across currencies. Current demo data is single-currency, but services accept a currency and aggregate one currency at a time. No FX conversion exists.

## Vendor Payouts

Vendor payouts consume available money from the vendor ledger without introducing mutable vendor balance columns. The requestable balance formula is:

```text
requestable_balance = available_vendor_ledger_balance - active_payout_reservations
```

Active payout reservation statuses are:

- `pending`
- `approved`
- `processing`

Paid, rejected, and cancelled payouts are not active reservations. A paid payout is already represented by its final ledger debit, and rejected/cancelled payouts release their reservation automatically by status without compensating ledger entries.

The payout lifecycle is explicit and action-driven:

```text
pending -> approved -> processing -> paid
pending -> rejected
pending -> cancelled
approved -> cancelled
```

There is no arbitrary status update route. Vendors can cancel only pending payouts. Admins approve, reject, start processing, cancel approved payouts before processing, and mark processing payouts paid. Processing payouts cannot be cancelled in this MVP.

`VendorPayout` stores the fixed requested amount, currency, actor IDs, lifecycle timestamps, optional rejection/cancellation reasons, optional external reference, and admin-only internal notes. Payout numbers such as `PAY-YYYYMMDD-XXXXXX` are the operational identifier; database IDs are not used in URLs.

Payout request creation locks the vendor row, recalculates available ledger balance and active reservations, enforces the configured minimum payout (`marketplace.minimum_vendor_payout` / `VENDOR_MINIMUM_PAYOUT`), and creates a pending payout in one transaction. The MVP allows one active payout per vendor per currency.

When a payout is marked paid, the system atomically creates exactly one negative `VendorLedgerEntry` with type `payout` and source key:

```text
vendor-payout:{id}:payout
```

The unique ledger `source_key` plus payout/vendor locking makes duplicate paid requests idempotent. Later refund debits may make the vendor's available ledger balance negative after a previously reserved payout is paid; that is valid and future earnings offset it.

Vendor payout methods currently support bank transfer only. Sensitive receiving-account identifiers (`account_number`, `iban`, `routing_number`) use Laravel encrypted casts and are hidden from model serialization. Vendor and admin lists display masked values. Edit forms do not repopulate full sensitive values; blank replacement fields keep the existing encrypted value.

Each payout stores an encrypted immutable destination snapshot at request time. Editing or deleting the live payout method later does not alter historical payout details. Admin payout detail can show the protected snapshot for manual processing inside the admin area; no public API exposes raw bank data.

Payout processing is operational/manual in Prompt 28. There is no Stripe Connect, bank API, PayPal/Wise/local-wallet integration, payout fee, tax withholding, scheduled payout, notification, or accounting export.

`php artisan vendor-payouts:reconcile` repairs missing payout ledger debits for payouts already marked paid and reports duplicate payout debit discrepancies. It does not mark processing payouts paid or otherwise change payout statuses.

## Coupons And Promotions

Promotions are coupon-code based in this foundation. One cart can hold one applied coupon through `carts.promotion_id`; applying a second valid coupon replaces the first, while an invalid replacement leaves the existing coupon untouched. Applying a coupon does not consume usage. Usage is consumed only when checkout successfully creates an order.

Promotion codes are normalized server-side and represented by `Promotion` with:

- funding source: `marketplace` or `vendor`
- discount type: percentage basis points or fixed minor-unit amount
- optional usage limit and per-customer limit
- optional start/end timestamps
- optional minimum eligible spend and maximum discount
- optional product and category targets

Money remains integer minor units. Percentage discounts are stored as basis points, so 10% is `1000`. The calculation order is:

```text
effective variant price -> merchandise subtotal -> promotion discount -> shipping -> grand total
```

Shipping is not discounted. `orders.items_subtotal`, `vendor_orders.items_subtotal`, and `order_items.line_total` remain pre-discount merchandise values. Discount data is explicit through `discount_total`, `marketplace_discount_total`, `vendor_discount_total`, `net_line_total`, `promotion_redemptions`, and `order_discount_allocations`.

Promotion eligibility is evaluated from the current cart lines. Product and category scopes use OR behavior. Category scopes include the selected category and descendants. Vendor-funded promotions apply only to items owned by the funding vendor. Marketplace-funded promotions may span vendors, subject to product/category scope.

Discount allocations are deterministic and proportional by eligible cart line, using integer math and stable remainder ordering. Checkout revalidates the applied promotion inside the order transaction after locking the promotion row. A successful checkout writes:

```text
Order discount snapshot
VendorOrder discount snapshot
OrderItem discount snapshot
OrderDiscountAllocation rows
PromotionRedemption row
```

These snapshots are immutable historical order data. Later promotion edits do not rewrite placed orders.

Marketplace-funded discounts reduce the customer payable total but do not reduce seller commercial value, commission basis, or vendor ledger sale value. Vendor-funded discounts reduce the seller-attributed merchandise basis and are posted as a separate vendor ledger promotion debit when vendor earnings are recognized:

```text
vendor-order:{id}:promotion
```

The existing `vendor-ledger:reconcile` command replays vendor order earnings idempotently and includes missing vendor promotion debit entries. There is no marketplace subsidy ledger in this prompt.

Refund capacity for discounted order items uses the customer-paid line amount. Refund allocations also store a seller-attributed amount so marketplace-funded discounts can preserve the correct vendor financial reversal basis while vendor-funded discounts do not overstate seller value.

Admin promotion management lives under `/admin/promotions`. Admins can create marketplace-funded coupons and vendor-funded coupons for a selected approved vendor. Vendor promotion management lives under `/vendor/promotions`; vendors can create only vendor-funded coupons for their own catalog. Customer and cross-vendor access is blocked by existing role and ownership checks.

Automatic promotions, coupon stacking, free shipping promotions, BOGO, loyalty, gift cards, referrals, store credit, campaign analytics, approval workflow, marketplace GL/subsidy accounting, and promotion notifications are deferred.

## Verified Reviews And Ratings

Product reviews are tied to verified delivered purchases. A customer can review only an `OrderItem` that belongs to their own order and whose `VendorOrder` is delivered. Refunded delivered purchases remain reviewable because a refund does not erase the historical purchase or delivery evidence.

`ProductReview` stores the reviewing user, product, source order item, integer `rating` from 1-5, optional title, body, moderation status, verified-purchase flag, safe reviewer display name, and a variant snapshot. Public reviewer identity is intentionally limited to a generated display name such as first name plus last initial; emails, order numbers, and private customer details are not shown on storefront review lists.

Duplicate prevention is database-backed:

```text
unique(order_item_id)
unique(user_id, product_id)
```

This means one order item can create at most one review, and one customer can leave at most one review per product even after repeat purchases. Repeat purchase review links redirect the customer to edit their existing product review.

Reviews use `App\Enums\ProductReviewStatus`:

- `pending`: customer-submitted or edited review awaiting moderation
- `approved`: public/published review
- `rejected`: hidden review with moderation feedback

Customers create and edit only their own reviews. Vendor users receive a read-only, vendor-scoped review list for products they own. Admins moderate reviews through explicit approve/reject actions; there is no vendor moderation route and no arbitrary public status assignment.

Product rating aggregates live on `products.rating_sum` and `products.rating_count`. They include approved reviews only and deliberately avoid storing a rounded float average. `Product::averageRating()` derives the display average from those integer columns. When an approved review is edited or rejected, its old contribution is removed and the review returns to pending. `php artisan reviews:rebuild-ratings` can recalculate all product aggregates from approved reviews.

Public product detail pages show approved reviews only, including the verified purchase badge, safe display name, rating distribution, sorting, pagination, and JSON-LD `AggregateRating` only when at least one approved review exists. Product cards show ratings only from real approved aggregate data; they do not display placeholder or fake ratings.

This module rates products, not sellers or stores. Review media, video reviews, vendor replies, helpful votes, reporting/abuse workflows, AI moderation, rating-based discovery ranking, and seller/store ratings are deferred.

## Notifications

Laravel native notifications provide the marketplace notification foundation. `users` remain the only notifiable identity table, so customer, vendor, and admin notifications all route to `User` records. Vendor notifications currently go to the owning vendor user; future multi-user vendor staff routing can extend this recipient resolver without changing notification payloads.

The core reliability rule is:

```text
Business transaction
-> commit
-> notification dispatch
-> Laravel database/mail channels
```

Notification delivery is treated as a side effect. Domain Actions update authoritative business state first, and `App\Services\Notifications\SendUniqueNotification` defers dispatch with `DB::afterCommit()` whenever it is called inside an open transaction. Notification exceptions are logged with safe recipient/source metadata and do not roll back orders, payments, refunds, payouts, reviews, products, or vendor application state.

In-app notifications use Laravel's standard `notifications` table. `notification_dispatches` stores one source key per recipient/event and has a unique constraint on:

```text
notifiable_type
notifiable_id
source_key
```

This prevents duplicate logical notifications from repeated webhooks, retried payout transitions, or repeated domain calls. The source key is also copied into the notification data payload for auditability.

Notification payloads are intentionally small:

```text
type
title
message
action_url
severity
source_key
```

They must not include full Orders, addresses, raw Stripe/provider payloads, card details, banking details, customer contact details, passwords, exception stacks, or vendor-private financial allocation data. `action_url` values are generated as internal relative routes only; the open route marks the notification read and refuses external or malformed redirects. Destination routes still enforce their normal role, policy, and ownership checks.

Implemented channels:

- Database notifications for customer, vendor, and admin notification centers.
- Mail notifications for important transactional events such as order placement, payment success/failure, order shipment/delivery, order cancellation, customer refund success/failure, vendor new order, payout paid/rejected, and vendor account status updates.
- Database-only notifications for lower-noise operational events such as review moderation, product moderation, vendor refund alerts, product review submission, admin payout requests, vendor/product submissions, and payment review alerts.

Queued notifications implement Laravel `ShouldQueue`. The default queue connection is environment-driven through `QUEUE_CONNECTION`; production should run a queue worker such as `php artisan queue:work`. The default mailer is environment-driven through `MAIL_MAILER`; local/demo environments use the log mailer unless configured otherwise.

Notification centers live at:

```text
/account/notifications
/vendor/notifications
/admin/notifications
```

Each center lists newest notifications first, paginates results, supports an unread filter, marks individual notifications read, marks all current-user notifications read, and scopes all reads/opens through the authenticated user's own notification relationship. Customer account navigation, the customer header, the vendor layout, and the admin layout show unread counts.

Multi-vendor semantics are explicit. A vendor order notification references only that seller's `VendorOrder`; it must not expose other sellers in the parent order. Customer shipment and delivery notifications describe the specific seller portion of the order rather than pretending the whole parent order changed if only one seller group moved.

Reconciliation commands such as `commissions:reconcile`, `vendor-ledger:reconcile`, and `vendor-payouts:reconcile` repair derived financial state and do not replay normal customer/vendor/admin lifecycle notifications.

Deferred notification areas remain separate: SMS, WhatsApp, browser push, mobile push, WebSockets, newsletters, marketing campaigns, notification preference matrices, email template CMS, localization, and automated retention/pruning.

## Product Detail

Public product detail pages live at:

```text
/product/{product:slug}
```

The route is named `storefront.products.show` and uses normal slug binding, followed by an explicit public visibility check through `Product::publiclyVisible()`. Non-public products return `404` so public visitors do not learn whether a product is draft, pending, rejected, archived, under an inactive category tree, or owned by an unapproved/suspended vendor.

`App\Services\Storefront\StorefrontProductService` prepares the detail page. It eager-loads only public-facing product data:

- vendor display identity, logo, description, and join date
- brand name
- category ancestry
- product gallery images
- active variants with attribute values, variant images, and inventory
- normalized product specifications
- limited same-category related products

Variant selection data is serialized into public-safe JSON for the Blade page's native JavaScript. The payload includes variant ID, SKU, selected attribute value codes, formatted price HTML, derived stock state, and merged image URLs. It does not include exact `on_hand`, `reserved`, movement history, approval metadata, vendor private fields, or moderation details.

The initial variant selection uses this priority:

1. active default variant with positive available stock
2. first active in-stock variant
3. active default variant even if out of stock
4. first active variant

Variant option compatibility is derived from actual active sellable combinations. The UI disables combinations that do not exist while still allowing an existing out-of-stock combination to be selected and shown as out of stock.

Selected variant price is authoritative on the product detail page. It uses `ProductVariant::storefrontPrice()` with:

```text
effective price = sale_price ?? base_price
```

Selected variant inventory owns the public stock label:

- In Stock
- Low Stock
- Out of Stock

No exact stock quantity is shown publicly.

Product media uses product gallery images by default. When a selected variant has variant-specific images, those images are shown first and product-level gallery images remain as fallback. Missing media is handled with the existing storefront placeholder.

Specifications render from stored normalized `ProductSpecification` rows and use the product's current category Attribute Set order when available. Specification values are formatted by input type: selected values as labels, multiselect values comma-separated, numbers as stored decimals, booleans as Yes/No, and text escaped as plain text.

Product cards now default to `storefront.products.show`, so homepage, category, search, and related-product cards lead into the detail page. The purchase panel submits `product_variant_id` and quantity to the cart after server-side public variant, stock, and role validation.

Color/value-level media mapping is still a future improvement. Current media switching is variant-specific: variant images belong to concrete `ProductVariant` records.

## Homepage CMS & Merchandising

The homepage is backed by a controlled typed-section CMS. It is intentionally not a raw page builder and does not allow arbitrary HTML, CSS, JavaScript, Tailwind classes, SQL, Blade names, or PHP class names in the database.

The current audited homepage sections are:

- Public header, search, account/cart controls, navigation, and footer: static application shell.
- Hero intro with three promotional slider groups: CMS content section, `HomepageSectionType::Hero`.
- Shop by Category: marketplace entity merchandising, `HomepageSectionType::Categories`.
- Promotion grid: CMS campaign content, `HomepageSectionType::PromotionGrid`.
- Featured Products carousel: marketplace product merchandising, `HomepageSectionType::Products`.
- Featured Product Showcase: marketplace product merchandising, `HomepageSectionType::ProductShowcase`.
- Featured Brands: marketplace brand merchandising, `HomepageSectionType::Brands`.
- Featured Stores: marketplace vendor merchandising, `HomepageSectionType::Vendors`.
- Promotional banner slider: CMS campaign content, `HomepageSectionType::BannerSlider`.
- New Arrivals carousel: computed/live product merchandising, `HomepageSectionType::Products` with `source=latest`.
- Trust/service strip: CMS repeated content, `HomepageSectionType::Benefits`.
- Seller CTA / Ready to Grow block: CMS CTA content, `HomepageSectionType::ReadyToGrow`.

Core storage:

```text
HomepageSection
    -> typed settings JSON
    -> HomepageSectionItem for real marketplace entities
    -> HomepageSectionContentItem for repeated campaign/content cards
```

`homepage_sections.key` is a stable unique identifier such as `hero`, `featured-products`, or `ready-to-grow`. `type` is cast to `App\Enums\HomepageSectionType`, and settings are validated through `App\Services\Homepage\HomepageSectionSchema`. Unknown non-empty settings are rejected so a request cannot smuggle renderer configuration, raw markup, or script-like behavior into a section.

Marketplace entity merchandising uses `homepage_section_items` with controlled item types from `App\Enums\HomepageSectionItemType`:

```text
product
category
vendor
brand
```

The database stores references only. Product names, prices, stock state, ratings, category names, brand names, and store names remain live marketplace data. A selected entity does not become public just because Admin selected it for the homepage. Public rendering still applies the existing visibility rules:

- products must pass `Product::publiclyVisible()`
- vendors/stores must be approved
- categories must be active with active ancestry where applicable
- brands must be active

If selected entities later become unavailable, the public homepage skips them. Empty entity sections do not render public shells.

Content sections use `homepage_section_content_items` for campaign slides, promotion cards, banner slides, and benefits. Content text renders escaped. CTA URLs must be internal relative URLs or HTTP/HTTPS URLs; `javascript:` and `data:` are rejected. Uploaded content images use Laravel Storage under `homepage/{section_id}` and accept only `jpg`, `jpeg`, `png`, and `webp` within the configured validation limit. Seeded/demo media remains referenced as relative storage paths and is not overwritten by Admin uploads.

Visibility is derived, not persisted as a separate status:

```text
visible =
    is_enabled
    and starts_at is null or starts_at <= now
    and ends_at is null or ends_at >= now
```

Expired sections do not automatically mutate `is_enabled`; the schedule remains an Admin configuration.

Rendering flow:

```text
Storefront\HomeController
    -> App\Services\Homepage\HomepageService
    -> resolved typed section payloads
    -> resources/views/storefront/home.blade.php
    -> resources/views/storefront/home/sections/{type}.blade.php
    -> reusable storefront components
```

Blade section partials do not query the database. `HomepageService` prepares active sections, resolves product/category/vendor/brand data with eager loading, applies per-section limits, preserves curated order, and collects wishlisted product IDs for the resolved product sets. Product presentation continues to use the existing ProductCard and pricing/rating behavior. The product showcase uses a real public Product payload for the large card; the old hardcoded demo product fallback was removed.

Admin management lives under:

```text
/admin/homepage
/admin/homepage/preview
/admin/homepage/sections/create
/admin/homepage/sections/{section}/edit
```

Only Admin users may manage or preview homepage CMS content. Vendors cannot feature their own products or stores directly; homepage merchandising remains Admin-controlled. The Admin preview route is protected by the normal Admin middleware and may show disabled, scheduled, and expired saved sections for review. There is no public `?preview=1` mode.

The seeder `Database\Seeders\HomepageSeeder` recreates the audited homepage with stable keys and is idempotent. Running `php artisan db:seed` updates seeded sections/content instead of duplicating them.

Adding a new homepage section type later should follow this workflow:

1. Add a `HomepageSectionType` enum case.
2. Add type-specific allowed settings and validation in `HomepageSectionSchema`.
3. Add Admin form fields for that type.
4. Add a resolver branch in `HomepageService` only if the type needs special data preparation.
5. Add a Blade renderer under `resources/views/storefront/home/sections`.
6. Seed any default content.
7. Add public rendering, Admin validation, and visibility tests.

Admin controls what appears, when it appears, and in what order. Developers control structural layout, design system classes, component behavior, and safe renderer mappings.

Deferred CMS features: unrestricted page builder, arbitrary HTML editor, arbitrary CSS/JS, full WYSIWYG, version history, rollback, A/B testing, personalization, recommendation engine, behavioral merchandising, segmentation, localization, multi-language CMS, full rich-text CMS, full sitewide CMS, and advanced campaign analytics.

## Frontend Strategy

Blade is the default rendering layer. Laravel should own URLs and navigation. Tailwind CSS 4 provides the design foundation through `resources/css/app.css`.

Use Vue 3 only for complex reactive components where Alpine or Blade would become awkward. Do not turn this project into a Vue SPA and do not add Vue Router.

Current shared UI primitives live in `resources/views/components`:

- `button`
- `input`
- `textarea`
- `select`
- `alert`
- `badge`
- `card`
- `container`
- `empty-state`

The base public layout is `resources/views/layouts/app.blade.php`. Future dedicated layouts can be added when needed:

- `layouts/customer.blade.php`
- `layouts/vendor.blade.php`
- `layouts/admin.blade.php`

## Visual System

The UI should feel modern, professional, clean, premium, spacious, marketplace-oriented, and trustworthy. Keep the system restrained:

- neutral surfaces
- focused brand color
- clear typography
- consistent spacing
- modest shadows
- 6-8px radius for most controls and cards
- accessible focus states

Avoid excessive gradients, decorative glass effects, oversized rounded corners, heavy animation, and template-like dashboard ornamentation.

## Testing And Quality

## Site Settings & Marketplace Configuration

Legitimate display settings use a typed developer-controlled `SettingsRegistry`, with code defaults overridden by the `settings` table and resolved through cached `SettingsService` access. Groups cover general marketplace name/tagline, public contact details, SEO defaults, selected social URLs, and products-per-page presentation. Unknown keys are ignored and there is no generic setting creation UI. Settings never store secrets or edit `.env`/PHP config. Currency remains developer-managed because orders, payments, refunds, commissions, and payouts persist currency snapshots; commission, promotions, shipping, tax, inventory, and payout rules remain dedicated domains. Header/footer and public SEO consume effective settings through view composers; cache invalidates after updates.

## Static & CMS Pages

Informational content is managed by `CmsPage` and ordered typed `CmsPageSection` records, with structured `CmsFaqItem` rows for help content. CMS pages use `/pages/{slug}` to avoid collisions with marketplace, account, vendor, and admin routes. Reserved slugs are rejected in the service layer. Pages support draft, published, and derived scheduled states (`published` with a future `published_at`), SEO metadata, preview, and lightweight header/footer eligibility. Public rendering uses the storefront layout and escapes all text; section types are mapped to application-controlled renderers, with no raw HTML, arbitrary Blade, CSS, or JavaScript. The CMS does not control transactional screens or marketplace business logic. Full navigation management, nested menus, revisions, localization, media libraries, and unrestricted page builders remain deferred.

Use the existing Laravel test stack:

- `php artisan optimize:clear`
- `php artisan test`
- `npm run build`
- `php artisan route:list` when route changes are made

Add tests alongside real behavior as modules are introduced. Keep early tests focused on user-visible behavior and critical domain rules.
## Navigation, Header, Mega Menu & Footer

Navigation is stored in developer-defined `NavigationMenu` locations (`header`, `footer_shop`, `footer_help`, `footer_sellers`, and `footer_company`) with controlled `NavigationItem` link types: route, category, CMS page, and validated HTTPS external links. `NavigationService` resolves and caches active targets, so Blade receives ready-to-render links and unavailable or unpublished references disappear automatically. The mega menu is category-driven from the active root and immediate-child hierarchy; categories are never duplicated into navigation records. Header branding and footer content come from the Settings service, while CMS pages remain the source for managed informational links. Mobile navigation reuses the same data through the existing drawer and accordion interaction. Advanced search suggestions, personalization, localization, analytics, newsletters, and theme-builder features remain deferred.
## Search & Product Discovery

Storefront discovery is database-backed for the MVP. `ProductDiscoveryService` centralizes public visibility, keyword matching, category/brand/vendor context, variant-aware pricing, attributes, sorting, and pagination. Query parameters are validated by `ProductDiscoveryRequest`, and all sort choices are mapped to controlled expressions. Predictive search is provided by `/search/suggestions`, returning small, visibility-safe product, category, brand, and approved-store results after two characters. Search pages remain progressively enhanced server-rendered views; dedicated search infrastructure, AI ranking, personalization, history, analytics, and typo-tolerance are deferred until catalog scale justifies them.
## Security & Production Readiness

Web responses include baseline security headers (`nosniff`, same-origin framing, strict referrer policy, and a restricted permissions policy). CSRF protection remains enabled except for the signature-verified Stripe webhook. Authentication throttles login attempts, role middleware and policies enforce tenant boundaries, and uploaded images are validated by MIME/type and size rules. Production deployments should set `APP_DEBUG=false`, use HTTPS with `SESSION_SECURE_COOKIE=true`, rotate secrets, and run queue workers for database-backed jobs. The current MVP uses SQLite/database cache and queue drivers without introducing infrastructure prematurely.
## Launch Product Architecture Roadmap

The current modular monolith remains the compatibility foundation for launch extensions. Money is persisted as integer minor units with a single configured marketplace currency; `App\Support\Currency` now validates ISO-4217-style metadata without changing current checkout behavior. Future market, currency, language, tax, wallet, gift-card, returns, disputes, invoice, seller-finance, analytics, export, and mobile API modules must be introduced incrementally with additive migrations and immutable order/payment snapshots. Payment gateways and courier integrations remain provider-selection decisions. No wallet, gift-card, return, dispute, invoice, or ledger tables are created prematurely.

Migration sequence: establish market/currency context, then tax rules and customer financial instruments, followed by returns/refunds/disputes and documents, then reporting and APIs. Existing orders, commissions, payouts, checkout, search, CMS, navigation, and settings remain backward compatible throughout.
## Multi-Country / Market Foundation

`Market` provides a stable commercial context with normalized code, ISO country, default currency metadata, activation, default selection, and ordering. `MarketService` resolves the active session market or the active default market and caches public market lists. Guests and authenticated users may switch markets through the POST `/market` endpoint; invalid or inactive selections fall back safely. Prompt 44 intentionally does not alter pricing, checkout currency, taxes, shipping, catalog ownership, or transactional snapshots. Those integrations belong to later prompts.
## Multi-Currency

Currencies are identified by uppercase ISO codes and configured as database metadata (`currencies`). Exchange-rate storage uses decimal precision with the convention `1 BASE = RATE QUOTE`; no external FX provider is integrated. `CurrencyContext` resolves a session-selected currency with safe fallback to the current Market default and legacy currency. The `/currency` endpoint validates selections. Existing order, payment, commission, payout, promotion, shipping, and checkout amounts remain immutable integer minor units; conversion and provider settlement behavior are introduced incrementally in later prompts.

`CurrencyConverter` performs explicit-rate conversion using integer minor units, decimal-rate parsing, target minor-unit scaling, and deterministic half-up rounding. Missing rates raise an error; no 1:1 fallback is used. Product and checkout price conversion remains opt-in until catalog price storage and transaction currency policies are migrated together.

Prompt 45C activates storefront pricing: product variants carry a backfilled `currency_code` (legacy USD), and `ProductVariant::storefrontPrice()` converts base/sale prices through the resolved `CurrencyContext`. Product aggregates and cart evaluation therefore use the selected currency, while order and financial records retain their transaction currency snapshots.
## Multilingual & Localization

Languages are managed as active metadata records with stable codes, native names, direction (`ltr`/`rtl`), ordering, and one default language. `LanguageContext` resolves a session-selected language with safe fallback, while `SetRequestLanguage` sets Laravel's locale once per web request. The public POST `/language` switch is guest-compatible and preserves market, currency, carts, and financial data. The layout emits language and direction metadata. Product/CMS translation tables and broad content translation workflows remain additive follow-up work.
## Multi-Country Tax Engine

Tax is modeled through reusable `TaxClass` and country/region-aware `TaxRule` records. `TaxEngine` resolves the highest-priority active rule and calculates tax in integer minor units using basis points. Order and vendor-order tax snapshot columns preserve historical totals. The engine is provider-independent and does not introduce external tax services; future checkout integration can apply class assignment and tax-inclusive/exclusive policy without rewriting financial history.

Products may reference a tax class. Cart evaluation resolves the customer's default delivery country, applies the active tax rule to discounted line amounts, exposes tax and grand totals, and snapshots `orders.tax_total` at placement. Products without a tax class remain zero-tax, preserving existing behavior.
## Customer Wallet & Store Credit

Wallets are currency-isolated customer store credit with an immutable, actor-attributed ledger. Admins can credit, debit, freeze, and review customer wallets; customers see paginated history. Checkout stores an opt-in for preview, then `PlaceOrder` locks the order and wallet together, rechecks balance, writes an order-referenced debit, and snapshots `orders.wallet_applied_total`. Full funding creates an internal paid wallet payment; partial funding keeps the existing payment flow for the remainder. Cancellation creates an idempotent compensating reversal capped at original wallet funding. Wallet funding does not alter tax, promotions, shipping, vendor totals, commission, or vendor ledgers. No FX, withdrawals, transfers, or vendor wallets are implemented.

Customer wallets are separate from vendor ledgers. Each customer has one wallet per currency and an immutable `wallet_transactions` ledger. Available balance is derived from credits, debits, reversals, and adjustments; no balance column is authoritative. `WalletService` enforces active-wallet and sufficient-balance checks and supports idempotent transaction keys. The account wallet view is read-only until checkout wallet tender and admin credit workflows are introduced.
## Gift Cards

Gift cards are separate stored value from promotions and customer wallets. Codes are normalized and SHA-256 hashed; only the last four characters are stored for display. `GiftCardTransaction` is immutable and the balance is derived from issue, redeem, refund, and adjustment entries. Redemption locks the card row, checks active status, expiry, currency, and balance, then creates an order-referenced idempotent debit. Checkout applies gift-card value after the final tax/shipping/promotion total and after wallet funding, without changing those totals. Refund credits can be recorded against the original card, including expired or disabled cards, while new spending remains blocked.

## Operational Returns — Prompt 50B

### Existing foundation and scope

The existing `returns`, `return_items`, and `return_histories` tables and models are extended additively. Existing dispute models/endpoints and financial refund actions remain in place. A return tracks physical operations; it does not credit money. Prompt 50C owns financial orchestration and Prompt 50D owns the remaining dispute operations.

### Eligibility and quantities

`ReturnEligibilityService` is the authoritative eligibility boundary. The customer must own the order, each item must belong to that order, and every item in one request must belong to the same VendorOrder. The service checks the historical `order_items.is_returnable` flag (default true), delivered VendorOrder status, and a real non-future `vendor_orders.delivered_at` timestamp. Missing delivery timestamps are rejected; there is no order-created-at fallback.

The return window is `returns.window_days` in the existing Settings registry, default 30 days, administrator-configurable from 1 to 365. A request remains eligible through the exact delivery timestamp plus that duration. The boolean returnability snapshot is the minimal historical policy; this pass does not introduce a product policy builder.

Remaining quantity is ordered/delivered item quantity minus return-item quantities whose parent request is neither rejected nor cancelled. Request creation locks the Order and selected OrderItem rows, then rechecks eligibility and quantities inside the transaction. Different vendors cannot be mixed. Cancelled/rejected requests release their quantities. Admin overrides recheck remaining quantities so a rejected request cannot reclaim quantities committed to a newer request.

### Operational status machine

`ReturnWorkflowService` owns request creation, transitions, and inspection. `ReturnStatus`, `ReturnReason`, and `ReturnCondition` constrain accepted values.

- requested → approved, rejected, cancelled
- approved → awaiting_return, cancelled (staff only at this stage)
- awaiting_return → received
- received → inspected (only through complete per-item inspection)
- inspected → refund_pending (at least one refund resolution) or completed (no refund resolutions)
- rejected → approved only as an Admin override with a reason and a new quantity check
- refund_pending is a handoff boundary; no manual completion is exposed before financial processing
- completed/cancelled are terminal; there is no general reopen function.

Customers may cancel only their own requested returns. Rejection/override explanations require at least five characters. All new requests and transitions append ReturnHistory with actor ID, role, from/to status, public explanation, and timestamp. Model update/delete operations reject history mutation. Workflow events use a lightweight `ReturnStatusChanged` event dispatched after transaction commit. No messages, email, SMS, or push integrations were added.

Legacy requests without items/vendor context remain visible to the owner and Admin. They cannot be advanced into an operational workflow; a customer may cancel a requested legacy record and submit an eligible item-based request. Existing rows are not fabricated or reassigned.

### Customer, Vendor, and Admin UI

A shared Blade presentation uses the existing account/vendor/admin layouts. Each role has a protected `/{role}/returns` list/detail with status, request/order reference, vendor, quantities, reason, evidence, timeline, and inspection. Staff lists support status, date, and return/order search; Admin also supports vendor/customer IDs. Order details show a Return an item link only if at least one item is eligible. The request page shows each item's eligibility and an item/quantity/reason/note/image form. Multi-item requests are supported by the backend; the launch form submits one item at a time.

Vendor queries and policies scope access through the request's VendorOrder and approved vendor owner. Customer access is scoped to the request owner. Admins can review marketplace returns and view order-level dispute references; unscoped disputes are not leaked to vendors. All displayed customer/staff text is Blade-escaped. Notes entered in these forms are explicitly public explanations; this pass has no private-note field.

### Evidence

`ReturnEvidence` uses a relational table, generated filenames, the configured private Laravel `local` disk, and paths under `returns/{id}/evidence`. Uploads accept JPEG/PNG/WebP only, with image content and MIME validation, 5 MB per image. `returns.evidence_limit` is configurable from 1–5, default 5. The locked parent request serializes evidence-limit checks. Unsupported/SVG/executable uploads are rejected. Files are removed when the upload operation fails. Initial invalid uploads roll back request/history creation. Downloads use policies and private/no-store response headers; no public storage URL is exposed.

### Inspection and inventory

Staff may inspect a received return only, including every ReturnItem exactly once. Inspected quantity cannot exceed requested quantity. A not_received condition requires zero; other inspected conditions require a positive quantity. Conditions are uninspected, restockable, damaged, defective, missing_parts, wrong_item, not_received. Resolution is refund, reject, or no_action; replacement is not exposed.

Only restockable inspected quantities invoke the existing `IncreaseInventory` action. It locks inventory and writes its normal before/after stock movement. Optional reference parameters added to that action preserve all existing callers. Return movements reference `return_item` and its ID; the movement reason includes return and order-item references. The ReturnItem stores a unique inventory-movement FK and restocked timestamp within the same transaction. The locked parent/item and received-only transition prevent repeated inspection/restocking. Damaged/defective/missing/wrong items stay outside sellable stock. There is no quarantine warehouse subsystem. Missing/archived original variants fail restocking safely rather than inventing substitute stock.

### Financial and currency boundaries

The return service never calls Wallet, Gift Card, payment, commission, payout, or vendor-ledger mutation services. It does not calculate or convert refund amounts. Item names/quantities refer to historical order snapshots. `refund_pending` means awaiting financial review, not a successful refund. Prompt 50C will link and orchestrate refunds and completion using the existing financial architecture.

### Demo preservation

`ReturnWorkflowSeeder` uses only existing delivered, within-window DEMO-LIFE order items with remaining quantity, and runs through the normal workflow. It seeds requested, awaiting_return, received, inspected/restockable, rejected, and refund_pending examples when valid items exist. Stable demo markers and order locks prevent duplication. It never creates replacement orders or fabricated quantities; insufficient eligible demo items are reported explicitly.

The existing general seeders rebuilt demo orders and stock movements. `DatabaseSeeder` now detects an existing order database and calls only the additive return seeder, preserving operational history. A fresh database still receives the original demo setup before return examples. Subsequent additions to other demo domains should use their specific safe seeders; running the general seeder is no longer a destructive refresh of an operational database.

### Verification boundaries

Dedicated feature tests in `tests/Feature/Returns/OperationalReturnsTest.php` cover eligibility, ownership, quantities, transitions/override, history, all role screens, safe uploads, inspection/restocking, seeder repeatability, financial non-mutation, and existing dispute endpoints. Tests use SQLite memory databases; row-lock behavior is implemented for the configured MySQL application database. Simultaneous MySQL stress testing is not claimed by the feature suite.

## Refund Financial Orchestration (Prompt 50C)

`RefundService` extends the existing Refund domain. RefundAllocation continues to describe item/shipping/adjustment economics; the new RefundFundingAllocation records the destination of that money. Order.customer is the customer reference, Order.currency is the original transaction currency, and optional ReturnRequest links permit both inspected returns and manual order refunds without synthetic returns.

Creation locks the order, payment and relevant items, validates ownership, collected payment, historical amounts and remaining capacities, then persists a pending refund and immutable destination instructions. Processing locks the order/refund and destination rows inside one database transaction. GiftCardService and WalletService restore their original funding; existing vendor ledger and commission actions post compensating entries in the same transaction. Failures roll back all financial effects. A reserved pending refund can be retried from Admin.

### Historical calculation and rounding

For each item, merchandise is OrderItem.customerLineTotal (historical line total minus allocated discount). Seller basis is sellerLineTotal (line total minus vendor-funded discount), which keeps marketplace-funded promotions separate. Refund amount is allocated merchandise plus allocated historical tax, plus explicit shipping or adjustment. No current ProductVariant, Promotion, TaxRule, shipping configuration or FX rate participates.

New orders persist per-line tax from the existing cart evaluation. Older orders with null item tax use remaining Order.tax_total after known snapshots, apportioned by historical net merchandise in ascending item ID order; the last line receives the remainder. This compatibility policy cannot reconstruct historical per-class rates that were never stored. Existing historical orders are not rewritten.

For a partial quantity, each remaining component uses floor(remaining component × requested quantity / remaining quantity); the last refundable quantity receives the exact remainder. BigInteger multiplication prevents intermediate integer overflow without floats. Discount, tax, net merchandise and seller basis reconcile independently. Shipping defaults to zero; an explicit admin amount is capped at remaining historical seller shipping. Marketplace adjustment has zero seller basis and still consumes order/funding capacity.

### Funding and capacities

Funding must reconcile: original Gift Card applied + original wallet applied + Payment.amount (external remainder) = Order.grand_total. Actual order-linked debit transactions verify internal source snapshots. Allocation priority is Gift Card, then wallet, then external payment, deducting previous credits and outstanding reservations. Item quantity/value, inspected ReturnItem quantity, seller shipping, total order and each original funding source are capped. Failed/cancelled reservations release capacity only before financial effects have posted.

Gift Card refunds are immutable credits through GiftCardService, including refunds to disabled/expired original cards; refunding never makes those cards usable. Wallet reversals use WalletService and the original customer's original-currency wallet. Neither source is converted or substituted. Destination instructions have unique idempotency keys and cannot change their financial identity or be deleted through models. Request keys have a stored instruction hash; reused keys with different instructions fail.

### External boundary and state

PendingExternalRefundGateway implements ExternalRefundGateway and returns pending_provider only. No external refund is marked successful without a verified provider. Existing legacy provider/webhook flows remain for historical legacy refunds, but cannot mark a new orchestrated refund successful while a destination is pending. No new payment gateway or notification implementation is included.

A fully restored internal refund is succeeded (the existing completion enum). Any external destination makes the refund partially_processed, with processed_at left null. Payment/PaymentAttempt external states remain unchanged. Pure internal payment status becomes partially_refunded/refunded as appropriate. economics_posted_at records the separate recognition of the approved seller liability. Late failure callbacks cannot release capacity after those effects are posted.

### Commission, ledger and payout treatment

Existing PostVendorRefundDebit posts one seller-attributed negative entry per commerce allocation; original sale records remain unchanged. Existing ApplyRefundCommissionAdjustment uses the original commission rate/basis and cumulative rounding, capped at the original commission, with one adjustment per allocation. Commission credits use the existing ledger action. The seller liability is recognized when refund processing is approved, including external-provider-pending refunds, so future payout eligibility reflects the obligation immediately.

Original commissions and paid payout records are not rewritten. Signed vendor ledger balances can become negative after a paid payout; requestable payout capacity may remain zero but the accounting liability is retained. Vendor A allocations never debit Vendor B. Recognition after an already-posted refund uses the existing pre-recognition net-basis behavior. The refund debit records the commission identity under seller-order/commission locks; reconciliation skips reversals already absorbed before recognition, including same-second operations.

### Returns, UI and authorization

RefundService never changes inventory. ReturnWorkflowService.completeRefund completes a refund_pending return only when all inspected refundable quantities have completed linked refunds and no linked financial refund remains pending. Existing inspection/restocking and history architecture remains authoritative.

Admin routes require auth and admin role. Services enforce admin role again. Admin can create manual item/shipping/adjustment refunds, review inspected returns, inspect funding destinations, seller debit/commission credit, and retry reserved pending refunds. Customers see safe merchandise/tax/shipping/adjustment and funding status in their own order/return views. Vendors see only their own seller effects. Gift Card codes and unrelated vendor funding details are never exposed.

### Demo and verification boundary

RefundWorkflowSeeder adds named DEMO-REFUND pending, internal, partial and split financial examples to an established demo catalog. Gift Card and wallet funding/restoration use their services; the split example has an explicitly seeded original demo cash receipt and a pending external refund. It never invents external refund success. Existing exhausted return examples are reported and preserved. General seeding preserves existing order and inventory history; repeated runs skip stable identifiers.

Feature tests use SQLite in memory and cover historical calculations, rounding, caps, source restoration, retries, rollback, authorization, currency, vendor isolation, return completion, payout immutability and negative seller liability. The application uses MySQL. Transaction/row-lock code is implemented for MySQL; the SQLite suite does not establish simultaneous MySQL race behavior. External provider execution remains an explicit future integration. Prompt 50D owns dispute operations; Prompt 51 owns transactional notifications.

## Dispute Operations and Final Returns / Refunds Integration (Prompt 50D)

### Domain and eligibility

Existing Dispute and DisputeMessage models are extended rather than replaced. Disputes reference the original order, one vendor order, optional return/refund, assigned admin and immutable final decision metadata. Eight controlled reasons describe non-delivery, condition, description, wrong item, seller disagreement, refund amount, return rejection and other concerns. DisputeEligibilityService verifies customer ownership and consistent seller/return/refund references. Single-seller legacy create requests infer the seller; multi-seller requests require selection. A unique active key prevents duplicate active disputes with the same order, seller, reason and related records. Legacy unscoped records remain readable to their customer/admin, with financial decisions blocked until a valid seller scope exists.

### Workflow

DisputeWorkflowService owns open, message, assignment, transition and resolution operations. Explicit transitions are open -> under_review; under_review -> awaiting_customer / awaiting_vendor; either awaiting state -> under_review; under_review -> resolved via a decision; resolved -> closed. The awaited participant's reply returns the case to review. Resolved/closed records reject messages and assignment changes. Identical decision retries are no-ops; conflicting decisions fail.

Messages and history reject model updates/deletes. History records actor, role, state change, decision and time. All conversation and decision notes are participant-visible; no hidden admin notes are mixed into this feed. Private image evidence is attached to a message, limited to five JPEG/PNG/WebP images of at most 5 MB each, validated by extension and MIME. Generated local paths are served only through policy-protected routes with no-store/nosniff headers. Failed transactions clean up newly stored files.

### Financial integration

Customer-favored full/partial resolutions call RefundService with a dispute-specific idempotency key. Item IDs must belong to the dispute's seller; historical order currency, pricing, tax, purchased quantities, inspected return quantities and funding caps remain authoritative. Return approval/rejection decisions call ReturnWorkflowService and append history, preserving earlier seller decisions. Additional refunds create new records; completed refunds and historical payouts are not rewritten.

Dispute code does not credit wallets/gift cards, reverse commissions, change vendor ledgers, or restore inventory. These effects remain in existing refund and return services. Order/dispute locks serialize decisions; downstream errors roll back the decision and internal financial effects. External refunds remain pending provider. A resolved operational dispute is not evidence that external money has been returned. The UI exposes this distinction. DisputeActivity events are emitted after commit for future notification integration, with no new delivery implementation.

### Roles and presentation

Customer routes provide order-linked creation, list, detail, reply and evidence. Approved vendors see only disputes for their vendor orders and seller-specific amounts. Admins have filters, assignment, review/wait actions and explicit resolution forms. Shared Blade views use the existing account/admin/vendor layouts; messages are paginated in batches of 20. Forms work without JavaScript. No other seller items, gift card secrets or wallet balances are rendered to vendors.

### Demo and verification

DisputeWorkflowSeeder creates seven stable scenarios using the existing refund demo: open, review, awaiting customer, awaiting vendor, vendor-favored, customer partial-refund and closed. Repeated seeds preserve records and financial entries. SQLite feature tests exercise journeys A-H, permissions, evidence, immutability, rollback and retries. A read-only two-connection MySQL probe verified row-lock contention and release without modifying the demo row. Simultaneous financial-resolution stress is only partially verified. See docs/prompt-50-final-report.md for exact final results and all 75 acceptance items. Prompt 51 remains outside this implementation.

## Transactional Notifications (Prompt 51)

### Existing foundation and event boundary

The original Laravel notifications table, notification_dispatches ledger, Notifiable User, notification subclasses, role inboxes, read operations and bells are retained. MarketplaceNotifier is now a compatibility event publisher: existing Actions call the same methods, which emit CommerceNotificationRequested after commit. CommerceNotificationListener resolves recipients and invokes SendUniqueNotification. ReturnStatusChanged and DisputeActivity are reused with history/message version identifiers; LifecycleNotificationListener handles them and the new FinancialNotificationRequested event. Financial services contain no SMTP/provider implementation.

### Event mapping

| Event / operation | Listener | Notification class(es) | Recipients | Eligible channels |
|---|---|---|---|---|
| CommerceNotificationRequested: orderPlaced/newVendorOrder | CommerceNotificationListener | OrderPlacedNotification, VendorOrderCreatedNotification | Customer; correct seller owner | database, mail |
| paymentChanged/paymentRequiresReview | CommerceNotificationListener | PaymentSucceededNotification, PaymentFailedNotification, PaymentRequiresReviewNotification | Customer; admin for review | database; mail for customer |
| vendorOrderStatusChanged/orderCancelled | CommerceNotificationListener | VendorOrderShippedNotification, VendorOrderDeliveredNotification, OrderCancelledNotification, VendorOrderCancelledNotification | Customer; affected seller | database; mail for customer |
| vendorApplicationSubmitted/vendorStatusChanged | CommerceNotificationListener | VendorApplicationSubmittedNotification, VendorApprovedNotification, VendorRejectedNotification, VendorSuspendedNotification, VendorReactivatedNotification | Admin; owning vendor | database; mail for vendor |
| productSubmitted/productStatusChanged | CommerceNotificationListener | ProductSubmittedNotification, ProductApprovedNotification, ProductRejectedNotification | Admin; owning vendor | database |
| payoutRequested/payoutStatusChanged | CommerceNotificationListener | PayoutRequestedNotification, PayoutApprovedNotification, PayoutProcessingNotification, PayoutPaidNotification, PayoutRejectedNotification | Admin for request; owning vendor | database; mail for paid/rejected |
| refundChanged | CommerceNotificationListener | RefundSucceededNotification, RefundFailedNotification, VendorRefundNotification | Customer; affected sellers only on authoritative success | database; mail for customer |
| ReturnStatusChanged | LifecycleNotificationListener.returning | ReturnRequestedNotification, ReturnApprovedNotification, ReturnRejectedNotification, ReturnReceivedNotification, ReturnInspectedNotification, ReturnCancelledNotification, ReturnRefundPendingNotification | Customer; seller for requested/cancelled; admin for refund review | database, mail |
| DisputeActivity | LifecycleNotificationListener.dispute | DisputeOpenedNotification, DisputeMessageNotification, DisputeResponseRequiredNotification, DisputeResolvedNotification, DisputeClosedNotification | Participants; assigned/all admins for opened/messages; only awaited role for response requests | database, mail |
| FinancialNotificationRequested: refund | LifecycleNotificationListener.financial | RefundProcessingNotification, RefundExternalPendingNotification; existing success notification; RefundImpactNotification | Customer; admin for pending provider; scoped seller impact | database, mail |
| FinancialNotificationRequested: wallet/gift_card | LifecycleNotificationListener.financial | WalletCreditNotification, WalletDebitNotification, GiftCardIssuedNotification | Associated customer only | database, mail |
| FinancialNotificationRequested: financial_failure | LifecycleNotificationListener.financial | SystemFinancialFailureNotification | Admin | database, mail |

Existing review moderation notifications also remain supported through CommerceNotificationRequested. There is no bulk moderation API to summarize; no digest engine was added. A vendor new-order notification waits for the existing CanFulfillVendorOrder eligibility (COD or confirmed online/internal payment). Payment confirmation retries that seller notification with the same logical key.

### Delivery, recovery and idempotency

SendUniqueNotification defers until commit, writes a unique recipient/source dispatch with a minimal replay snapshot, then invokes native queued Laravel notifications. Database and mail use Laravel channel subclasses with dispatch-row locks and separate database_sent_at/mail_sent_at receipts. Replaying a delivery skips completed channels. Mail jobs have five attempts and increasing backoff. The queue connection is configuration-driven.

Dispatch enqueue failures retain status=failed and safe exception class metadata. notifications:retry requeues pending/failed records; the scheduler runs it once per minute without overlap. Native failed jobs remain inspectable/retryable through Laravel. Legacy dispatches have status=legacy and no replay snapshot; they are preserved, not resent. A queued status means handed to Laravel, not necessarily delivered; channel receipts are authoritative. Failed listener preparation is logged safely for investigation.

SMTP has an unavoidable acknowledgement gap: if the provider accepts mail and the process crashes before recording its receipt, a retry may deliver again. Exactly-once external delivery requires provider idempotency support. Local channel replay/concurrent row-lock protection does not make that guarantee. Commerce is already committed before dispatch; mail exceptions cannot reverse its business state.

### Preferences, locale and security

notification_preferences stores one row per user/category, with in-app/email booleans. Account, vendor, order, payment, return, refund, dispute, wallet, gift_card and payout categories are required transactional. Product, fulfillment and system updates are optional. Financial-failure notices use the required account policy. Required channel settings are enforced server-side; existing database-only event mappings remain database-only. No marketing subscriptions or unsupported channel switches are introduced.

All three roles have GET/PUT notifications/preferences routes. users.notification_locale accepts en/ur/ar, set explicitly in preferences; otherwise app.locale is used. It is not inferred from currency or country. Laravel locale-aware notification rendering, translated core titles/chrome, and HTML email dir=rtl support Urdu/Arabic; unprovided long-form translations fall back to English. Financial text retains the entity currency snapshot.

Payloads contain controlled category/severity, title/message, internal named-route action URL/label and source key, never complete models, wallet balances, gift-card codes/hashes, private notes or cross-seller commerce. Message authors are excluded from their own dispute reply alerts. Return rejection explanations come only from the existing participant-visible history. Inbox and read queries stay scoped to the authenticated user; following an action still invokes destination authorization. Bells use a count and at most five rows, without per-notification relation queries.

### Mail and production operation

The responsive HTML/plain-text transactional templates use SettingsService marketplace name/support address with application fallback. There is no configurable logo in the current settings registry, so mail uses text branding. Named routes/configured APP_URL supply links. Configure a production MAIL_MAILER, sender and verified provider credentials, HTTPS APP_URL, a supervised queue worker, the scheduler, failed-job monitoring and alerts for failed dispatches before launch. Keep worker timeout below queue retry_after and configure finite transport timeouts. Local verification uses the database queue and log mailer; real SMTP delivery is not claimed.

SMS, WhatsApp and mobile push are not integrated. Laravel channels remain the extension point. No notification history is pruned. See docs/prompt-51-report.md for verification and acceptance results.

## Invoice & Order Documents (Prompt 52)

### Audit and boundary

No existing invoice table, document service, PDF dependency or download route existed. Reused Order, VendorOrder and OrderItem snapshots; authoritative Payment status; Refund/RefundAllocation/RefundFundingAllocation; customer/vendor/admin roles; SettingsService; notification locale preferences; and existing order/refund screens. No commerce operation or financial posting is performed by document generation.

### Architecture

`Authenticated named route -> CommerceDocumentPolicy -> DocumentService -> CommerceDocument + DocumentSnapshot -> DocumentRenderer -> Blade print HTML / mPDF PDF`.

Controlled types are `order_invoice`, `order_summary`, `vendor_order`, `credit_note`. The additive `commerce_documents` table stores source foreign keys, unique logical identity, unique immutable number, historical currency and precision, stable locale, issuer/recipient snapshots, issue timestamp and minimal displayed content. It does not serialize whole Eloquent models or secrets. Order/item fields are historical snapshots but are not model-enforced immutable in this project; therefore displayed line values and status are frozen once at document issuance as well. Subsequent rendering only reads the document payload.

### Numbering, issuance and concurrency

Issuance is lazy on the first authorized request (or demo seed). Rendering is separate. A transaction locks the source Order with `FOR UPDATE`, checks the unique logical key `type:source-id`, and returns an existing document unchanged or inserts one. Numbers are `INV|ORD|SEL|CRN-issue-year-source-id-padded-to-8`; no MAX+1 calculation or random numbering. Unique constraints on logical key and number back the order lock; transaction deadlocks are retried up to three times. Credit-note creation reuses/creates the original invoice within the transaction. The same refund cannot create a second credit note; distinct refunds retain separate identities.

Documents have no edit/delete endpoint. Model update/delete hooks and the document Eloquent builder reject mutation. As with other application integrity policies, privileged raw database access is outside that guard. Foreign keys restrict source deletion. No voiding workflow or settlement statement is introduced.

### Historical values and seller isolation

Customer lines use OrderItem product name, variant text, SKU, quantity, unit_price, line_total, discount_total, tax_total and canonical customerLineTotal(). Totals reproduce stored Order subtotal, discount, shipping, tax and grand_total. No ProductVariant price, TaxRule, Promotion, shipping rate or FX quote is fetched. Multi-vendor invoices group by actual VendorOrder. Seller documents include only the selected VendorOrder, its own items and recorded total; no customer wallet/gift-card funding or another vendor's lines appear.

Legacy seller totals can exclude customer item tax: PlaceOrder historically stored seller grand_total without that tax. Seller documents preserve those values and explicitly distinguish recorded seller totals from item tax shown for reference. They are operational records, not payout/commission statements. Wallet and gift-card application are separate tender allocations, never additional discounts; remaining external funding is not represented as proof of collection. Payment status is the authoritative Payment state at issuance.

### Refunds

Completed refunds receive credit notes linked to the original invoice/order and refund number, using actual allocation quantities, net merchandise, included discounts, tax, shipping, adjustments and total. No refund is recomputed or rewritten. Safe funding destination labels/amounts/statuses are included when recorded. Codes, balances, payment credentials, commissions and internal notes are excluded.

An economically recognized `partially_processed` refund receives an on-demand, non-persisted processing statement explicitly saying provider funds have not yet been returned. It has a `STAT-refund-reference` identifier, is labelled a statement rather than a completed credit note, and may reflect later processing progress. Pending/unapproved refunds cannot create credit notes. Refund currency must match the original invoice; mismatches fail rather than silently converting.

### Issuer and recipient

Default: marketplace-issued commercial records, configurable through `config/documents.php` / `DOCUMENT_ISSUER`. `vendor` mode permits single-vendor customer invoices; aggregate multi-vendor customer invoices require marketplace mode. Seller documents are vendor-issued. Credit notes retain their original invoice issuer even if current configuration changes.

`DOCUMENT_LEGAL_NAME`, `DOCUMENT_BUSINESS_ADDRESS`, `DOCUMENT_TAX_NUMBER` are optional verified client inputs. Marketplace name, support email/address come from existing SettingsService, website from APP_URL. Vendor documents snapshot the vendor's recorded business name/address/tax number/website. Missing optional fields are hidden; text branding works without a logo. No arbitrary/remote images or HTML are accepted. The client must confirm the issuer model, business details and jurisdiction-specific tax requirements; no statutory compliance is asserted.

Recipient identity/address use Order shipping snapshots, not the customer's current profile/address book. Legacy seller/issuer identity was not snapshotted at checkout; it is frozen at first issuance, not reconstructed for the earlier order date.

### Currency, localization and rendering

Money::formatWithPrecision uses integer arithmetic and the document's stored ISO currency/minor-unit precision. Metadata is resolved at issuance from Currency, with the existing configured base-currency precision as the only fallback. Unknown unconfigured currencies fail explicitly. No exchange-rate conversion occurs. Existing commerce Money::format behavior is unchanged.

Locale precedence: original invoice for credit notes; otherwise recipient's notification locale (seller recipient for seller document), then active marketplace default Language, then documents.fallback_locale captured from APP_LOCALE. Browser/session language cannot select the formal document locale. Once stored it remains stable. Laravel translation keys support en/ar/ur with English fallback for untranslated detailed text. Renderer restores the prior application locale in a finally block. mPDF auto-script/font selection and RTL direction support Arabic/Urdu text and mixed numeric tables.

mPDF 8.2.7 renders server-side A4 PDFs from escaped Blade. PDFs are generated on demand and returned as private, no-store attachments with sanitized filenames and nosniff. No public PDF storage or public token link exists. Renderer cache is private at storage/app/private/document-renderer and must be writable by the PHP worker. It contains font metadata, not public customer PDFs. Diagnostic samples stay under private document-qa. Print HTML uses the same snapshot/template and restrictive CSP; native browser printing works without JavaScript. Tables repeat headers across pages and include page-number footers.

### UI and notifications

Customer order detail has Download invoice and Print summary; eligible refund entries have Refund document. Vendor order detail has Download seller document. Admin order/refund details expose the corresponding documents and seller-specific links. All eight routes use auth plus their exact role and centralized ownership checks before issuance. The existing notification order/refund destinations lead to these screens; no duplicate notification event or mail attachment is introduced.

### Demo and operations

CommerceDocumentSeeder issues records only from existing customer@example.com demo commerce, including bounded standard, multi-vendor and completed-refund samples. It does not manufacture financial history, and repeated seeding reuses logical identities. Maintain composer-installed mPDF and its font dependencies; production PHP requires gd/mbstring and a writable private rendering cache. Back up commerce_documents alongside the historical commerce tables. See docs/prompt-52-report.md for exact verification results, limitations, routes and the 75-item acceptance checklist.
