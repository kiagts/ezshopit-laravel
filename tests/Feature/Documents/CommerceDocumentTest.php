<?php

namespace Tests\Feature\Documents;

use App\Enums\CommerceDocumentType as Type;
use App\Enums\RefundStatus;
use App\Models\CommerceDocument;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundAllocation;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\DocumentService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $o = Order::factory()->create(['items_subtotal' => 10000, 'discount_total' => 1000, 'shipping_total' => 500, 'tax_total' => 900, 'grand_total' => 10400, 'wallet_applied_total' => 2000, 'gift_card_applied_total' => 3000]);
        $v = VendorOrder::factory()->create(['order_id' => $o->id, 'items_subtotal' => 10000, 'discount_total' => 1000, 'shipping_total' => 500, 'tax_total' => 0, 'grand_total' => 9500]);
        OrderItem::factory()->create(['vendor_order_id' => $v->id, 'product_name' => 'Historical lamp', 'sku' => 'OLD-001', 'quantity' => 2, 'unit_price' => 5000, 'line_total' => 10000, 'discount_total' => 1000, 'net_line_total' => 9000, 'tax_total' => 900]);

        return $o;
    }

    private function invoice(Order $o): CommerceDocument
    {
        return app(DocumentService::class)->issue(Type::OrderInvoice, $o);
    }

    public function test_historical_totals_and_funding_are_preserved(): void
    {
        $o = $this->order();
        $d = $this->invoice($o);
        $this->assertSame(10400, $d->content['totals']['total']);
        $this->assertSame(['gift_card' => 3000, 'wallet' => 2000, 'external' => 5400], $d->content['funding']);
        $this->assertSame(900, $d->content['totals']['tax']);
        $this->assertSame('OLD-001', $d->content['groups'][0]['lines'][0]['sku']);
    }

    public function test_repeat_issuance_freezes_number_issuer_address_items_and_amounts(): void
    {
        $o = $this->order();
        $d = $this->invoice($o);
        $o->update(['grand_total' => 1, 'shipping_first_name' => 'Changed']);
        $o->vendorOrders->first()->items->first()->update(['product_name' => 'Changed']);
        config(['documents.legal_name' => 'Changed']);
        $again = $this->invoice($o);
        $this->assertSame($d->id, $again->id);
        $this->assertEquals($d->toArray(), $again->toArray());
        $this->assertDatabaseCount('commerce_documents', 1);
    }

    public function test_customer_can_print_and_cannot_access_another_order(): void
    {
        $o = $this->order();
        $this->actingAs($o->user)->get(route('account.documents.print', $o))->assertOk()->assertSee('Historical lamp')->assertSee('Payment funding allocation');
        $this->actingAs(User::factory()->customer()->create())->get(route('account.documents.print', $o))->assertForbidden();
        $this->assertDatabaseCount('commerce_documents', 1);
    }

    public function test_guest_is_redirected_without_issuing_document(): void
    {
        $o = $this->order();
        $this->get(route('account.documents.invoice', $o))->assertRedirect(route('login'));
        $this->assertDatabaseCount('commerce_documents', 0);
    }

    public function test_vendor_isolation_and_customer_route_role_protection(): void
    {
        $o = $this->order();
        $v = $o->vendorOrders->first();
        $other = VendorOrder::factory()->create(['order_id' => $o->id]);
        $d = app(DocumentService::class)->issue(Type::VendorOrder, $v);
        $this->assertCount(1, $d->content['groups']);
        $this->assertArrayNotHasKey('funding', $d->content);
        $this->assertSame($v->vendor_id, $d->vendor_id);
        $this->actingAs($other->vendor->user)->get(route('vendor.documents.seller', $v))->assertForbidden();
        $this->actingAs($v->vendor->user)->get(route('account.documents.invoice', $o))->assertForbidden();
    }

    public function test_admin_can_print_customer_order(): void
    {
        $o = $this->order();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.documents.print', $o))->assertOk();
    }

    public function test_pdf_download_has_private_headers_and_valid_pdf(): void
    {
        $o = $this->order();
        $response = $this->actingAs($o->user)->get(route('account.documents.invoice', $o))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_multi_vendor_invoice_groups_and_vendor_document_only_own_lines(): void
    {
        $o = $this->order();
        $v = VendorOrder::factory()->create(['order_id' => $o->id]);
        OrderItem::factory()->create(['vendor_order_id' => $v->id, 'product_name' => 'Other seller item']);
        $this->assertCount(2, $this->invoice($o)->content['groups']);
        $d = app(DocumentService::class)->issue(Type::VendorOrder, $v);
        $html = app(DocumentRenderer::class)->html($d);
        $this->assertStringContainsString('Other seller item', $html);
        $this->assertStringNotContainsString('Historical lamp', $html);
        $this->assertStringNotContainsString('Payment funding allocation', $html);
    }

    public function test_currency_precision_is_explicit_and_historical(): void
    {
        $this->assertSame('JPY 1,234', Money::formatWithPrecision(1234, 'JPY', 0));
        $this->assertSame('KWD 1.234', Money::formatWithPrecision(1234, 'KWD', 3));
        $o = $this->order();
        Currency::create(['code' => 'KWD', 'name' => 'Dinar', 'minor_unit' => 3, 'is_active' => true]);
        $o->update(['currency' => 'KWD']);
        $d = $this->invoice($o);
        config(['marketplace.currency.code' => 'USD']);
        $this->assertSame('KWD', $d->currency_code);
        $this->assertSame(3, $d->minor_unit);
        $this->assertStringContainsString('KWD 10.400', app(DocumentRenderer::class)->html($d));
    }

    public function test_document_cannot_be_updated(): void
    {
        $d = $this->invoice($this->order());
        $this->expectException(\LogicException::class);
        $d->update(['document_number' => 'CHANGED']);
    }

    public function test_document_cannot_be_bulk_deleted(): void
    {
        $this->invoice($this->order());
        $this->expectException(\LogicException::class);
        CommerceDocument::query()->delete();
    }

    public function test_escaped_content_and_rtl_locale_are_rendered_and_locale_restored(): void
    {
        $o = $this->order();
        $o->user->forceFill(['notification_locale' => 'ar'])->save();
        $o->vendorOrders->first()->items->first()->update(['product_name' => '<script>alert(1)</script>']);
        $d = $this->invoice($o);
        $html = app(DocumentRenderer::class)->html($d);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('en', app()->getLocale());
    }

    private function refund(Order $o, RefundStatus $status): Refund
    {
        $r = Refund::factory()->create(['order_id' => $o->id, 'currency' => $o->currency, 'amount' => 4950, 'status' => $status, 'economics_posted_at' => now(), 'processed_at' => now()]);
        $v = $o->vendorOrders->first();
        RefundAllocation::factory()->create(['refund_id' => $r->id, 'vendor_order_id' => $v->id, 'order_item_id' => $v->items->first()->id, 'type' => 'item', 'quantity' => 1, 'amount' => 4950, 'gross_amount' => 5000, 'discount_amount' => 500, 'merchandise_amount' => 4500, 'tax_amount' => 450]);

        return $r;
    }

    public function test_partial_refund_credit_note_uses_recorded_allocations_and_original_invoice(): void
    {
        $o = $this->order();
        $r = $this->refund($o, RefundStatus::Succeeded);
        $d = app(DocumentService::class)->issue(Type::CreditNote, $r);
        $this->assertSame(4950, $d->content['totals']['total']);
        $this->assertSame(450, $d->content['totals']['tax']);
        $this->assertSame(4500, $d->content['totals']['merchandise']);
        $this->assertSame(1, $d->content['lines'][0]['quantity']);
        $this->assertSame($this->invoice($o)->document_number, $d->content['invoice_reference']);
        $this->assertSame($d->id, app(DocumentService::class)->issue(Type::CreditNote, $r)->id);
    }

    public function test_external_pending_statement_does_not_issue_credit_note(): void
    {
        $r = $this->refund($this->order(), RefundStatus::PartiallyProcessed);
        $d = app(DocumentService::class)->pendingStatement($r);
        $html = app(DocumentRenderer::class)->html($d);
        $this->assertFalse($d->exists);
        $this->assertStringContainsString('has not been returned', $html);
        $this->assertDatabaseMissing('commerce_documents', ['document_type' => 'credit_note']);
    }

    public function test_unfinished_refund_cannot_issue_credit_note(): void
    {
        $r = $this->refund($this->order(), RefundStatus::Pending);
        $this->expectException(ValidationException::class);
        app(DocumentService::class)->issue(Type::CreditNote, $r);
    }

    public function test_cross_customer_refund_is_forbidden_before_issuance(): void
    {
        $r = $this->refund($this->order(), RefundStatus::Succeeded);
        $this->actingAs(User::factory()->customer()->create())->get(route('account.documents.refund', $r))->assertForbidden();
        $this->assertDatabaseCount('commerce_documents', 0);
    }

    public function test_vendor_issuer_rejects_aggregate_multi_vendor_invoice(): void
    {
        $o = $this->order();
        VendorOrder::factory()->create(['order_id' => $o->id]);
        config(['documents.issuer' => 'vendor']);
        $this->expectException(ValidationException::class);
        $this->invoice($o);
    }

    public function test_separate_types_have_separate_stable_numbers(): void
    {
        $o = $this->order();
        $invoice = $this->invoice($o);
        $summary = app(DocumentService::class)->issue(Type::OrderSummary,$o);
        $this->assertNotSame($invoice->document_number,$summary->document_number);
        $this->assertDatabaseCount('commerce_documents',2);
    }

    public function test_request_language_does_not_select_document_locale(): void
    {
        $o=$this->order();app()->setLocale('ar');
        $d=$this->invoice($o);$this->assertSame('en',$d->locale);
        $o->user->forceFill(['notification_locale'=>'ur'])->save();
        $this->assertSame('en',$this->invoice($o)->locale);
    }

    public function test_current_catalog_prices_and_rules_are_not_used_for_initial_issuance(): void
    {
        $o=$this->order();$i=$o->vendorOrders->first()->items->first();
        $i->product->update(['name'=>'Renamed catalog product']);
        $i->variant->update(['base_price'=>1,'sale_price'=>null,'sku'=>'NEW-SKU']);
        $queries=[];\Illuminate\Support\Facades\DB::listen(function($query)use(&$queries){$queries[]=$query->sql;});
        $d=$this->invoice($o);
        $this->assertSame('Historical lamp',$d->content['groups'][0]['lines'][0]['name']);
        $this->assertSame(5000,$d->content['groups'][0]['lines'][0]['unit_price']);
        $this->assertSame('OLD-001',$d->content['groups'][0]['lines'][0]['sku']);
        $this->assertSame(10400,$d->content['totals']['subtotal']-$d->content['totals']['discount']+$d->content['totals']['tax']+$d->content['totals']['shipping']);
        foreach($queries as $sql)$this->assertDoesNotMatchRegularExpression('/from ["`]*(product_variants|products|tax_rules|promotions|shipping_methods|exchange_rates)["`]*\b/i',$sql);
    }

    public function test_multiple_refunds_have_distinct_credit_notes_and_shipping_is_not_recomputed(): void
    {
        $o = $this->order();
        $first = $this->refund($o, RefundStatus::Succeeded);
        $second = $this->refund($o, RefundStatus::Succeeded);
        $second->update(['amount'=>5450]);
        RefundAllocation::factory()->create(['refund_id'=>$second->id,'type'=>'shipping','amount'=>500]);
        $a = app(DocumentService::class)->issue(Type::CreditNote,$first);
        $b = app(DocumentService::class)->issue(Type::CreditNote,$second);
        $this->assertNotSame($a->document_number,$b->document_number);
        $this->assertSame(500,$b->content['totals']['shipping']);
        $this->assertSame(5450,$b->content['totals']['total']);
        $this->assertSame(500,$b->content['lines'][0]['discount']);
        $this->assertSame(4950,$a->fresh()->content['totals']['total']);
    }

    public function test_issuer_address_registration_and_recipient_are_frozen(): void
    {
        config(['documents.legal_name'=>'Configured issuer','documents.tax_number'=>'CLIENT-123','documents.business_address'=>'Recorded address']);
        $o=$this->order();$d=$this->invoice($o);
        config(['documents.legal_name'=>'Changed','documents.tax_number'=>'CHANGED','documents.business_address'=>'New address']);
        $o->update(['shipping_address_line_1'=>'New address']);
        $again=$this->invoice($o);
        $this->assertSame('Configured issuer',$again->issuer_snapshot['name']);
        $this->assertSame('CLIENT-123',$again->issuer_snapshot['tax_number']);
        $this->assertSame('Recorded address',$again->issuer_snapshot['address']);
        $this->assertSame($d->recipient_snapshot,$again->recipient_snapshot);
        $this->assertEquals($d->issued_at,$again->issued_at);
    }

    public function test_document_creation_has_no_commerce_side_effects_or_private_payloads(): void
    {
        $o=$this->order();$v=$o->vendorOrders->first();
        $before=$o->fresh()->toArray();
        $tables=['orders','payments','refunds','wallet_transactions','gift_card_transactions','vendor_ledger_entries','vendor_payouts'];
        $counts=[];foreach($tables as $table)if(\Illuminate\Support\Facades\Schema::hasTable($table))$counts[$table]=\Illuminate\Support\Facades\DB::table($table)->count();
        $d=$this->invoice($o);$this->invoice($o);
        $seller=app(DocumentService::class)->issue(Type::VendorOrder,$v);
        $this->assertEquals($before,$o->fresh()->toArray());
        foreach($counts as $table=>$count)$this->assertSame($count,\Illuminate\Support\Facades\DB::table($table)->count(),$table);
        foreach(['commission','ledger','secret','code_hash','wallet_balance','provider_reference','password'] as $private){
            $this->assertStringNotContainsString($private,json_encode($d->content));
            $this->assertStringNotContainsString($private,json_encode($seller->content));
        }
    }

    public function test_database_rejects_duplicate_logical_identity(): void
    {
        $d=$this->invoice($this->order());$copy=$d->getAttributes();unset($copy['id']);$copy['document_number']='INV-DUPLICATE';
        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('commerce_documents')->insert($copy);
    }

    public function test_refund_mismatched_currency_is_rejected_and_rolled_back(): void
    {
        $r=$this->refund($this->order(),RefundStatus::Succeeded);$r->update(['currency'=>'PKR']);
        try{app(DocumentService::class)->issue(Type::CreditNote,$r);$this->fail('Mismatch should fail');}
        catch(ValidationException){$this->assertDatabaseCount('commerce_documents',0);}
    }

    public function test_non_admin_cannot_use_admin_routes_and_owned_seller_download_works(): void
    {
        $o=$this->order();$v=$o->vendorOrders->first();
        $this->actingAs($o->user)->get(route('admin.documents.invoice',$o))->assertForbidden();
        $this->actingAs($v->vendor->user)->get(route('vendor.documents.seller',$v))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.documents.seller',$v))->assertOk();
    }

    public function test_demo_document_seeding_is_repeatable(): void
    {
        $o=$this->order();$o->user->update(['email'=>'customer@example.com']);
        $this->seed(\Database\Seeders\CommerceDocumentSeeder::class);
        $this->assertDatabaseCount('commerce_documents',3);
        $this->seed(\Database\Seeders\CommerceDocumentSeeder::class);
        $this->assertDatabaseCount('commerce_documents',3);
    }

    public function test_representative_pdf_documents_render_without_optional_logo_or_legal_fields(): void
    {
        config(['documents.legal_name'=>null,'documents.tax_number'=>null,'documents.business_address'=>null]);
        $o=$this->order();$documents=['standard'=>$this->invoice($o)];
        $multi=$this->order();
        $vo=VendorOrder::factory()->create(['order_id'=>$multi->id,'items_subtotal'=>5000,'discount_total'=>0,'shipping_total'=>0,'tax_total'=>500,'grand_total'=>5500]);
        OrderItem::factory()->create(['vendor_order_id'=>$vo->id,'quantity'=>1,'unit_price'=>5000,'line_total'=>5000,'net_line_total'=>5000,'tax_total'=>500,'product_name'=>'Second seller desk accessory']);
        $multi->update(['items_subtotal'=>15000,'tax_total'=>1400,'grand_total'=>15900]);
        $documents['multi-vendor']=$this->invoice($multi);
        $documents['seller']=app(DocumentService::class)->issue(Type::VendorOrder,$vo);
        $documents['partial-credit']=app(DocumentService::class)->issue(Type::CreditNote,$this->refund($o,RefundStatus::Succeeded));
        $rtl=$this->order();$rtl->user->forceFill(['notification_locale'=>'ar'])->save();
        $rtl->vendorOrders->first()->items->first()->update(['product_name'=>'مصباح مكتبي للاستخدام اليومي']);
        $documents['rtl']=$this->invoice($rtl);
        foreach($documents as $name=>$document){
            $pdf=app(DocumentRenderer::class)->pdf($document);
            $this->assertStringStartsWith('%PDF-',$pdf,$name);
            $this->assertGreaterThan(1000,strlen($pdf),$name);
            if(getenv('DOCUMENT_QA_OUTPUT')){
                $directory=storage_path('app/private/document-qa');if(!is_dir($directory))mkdir($directory,0700,true);
                file_put_contents($directory.'/'.$name.'.pdf',$pdf);
                file_put_contents($directory.'/'.$name.'.html',app(DocumentRenderer::class)->html($document));
            }
        }
    }
}
