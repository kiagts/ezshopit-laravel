<?php
namespace Database\Seeders;
use App\Models\{User,Vendor,Order,VendorOrder,OrderItem,Payment,Refund,RefundAllocation,VendorPayoutMethod};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class VendorFinanceSeeder extends Seeder {
 public function run():void {
  $admin=User::where('email','admin@example.com')->first();$customer=User::where('email','customer@example.com')->first();
  if(!$admin || !$customer)return;
  foreach(['positive','vendor-promotion','marketplace-promotion','refund','paid','negative','multi-usd','multi-pkr','pending','processing','rejected'] as $scenario){
   DB::transaction(function()use($scenario,$admin,$customer){
    $number='DEMO-FINANCE-'.strtoupper($scenario);
    if(Order::where('order_number',$number)->exists())return;
    $group=str_starts_with($scenario,'multi-')?'multi':$scenario;
    $email='finance.'.$group.'@example.com';
    $user=User::where('email',$email)->first()??User::factory()->vendor()->create(['email'=>$email,'name'=>'Finance Demo '.ucfirst($group)]);
    $vendor=Vendor::where('user_id',$user->id)->first()??Vendor::factory()->approved()->create(['user_id'=>$user->id,'slug'=>'finance-demo-'.$group,'display_name'=>'Finance Demo '.ucfirst($group)]);
    $currency=$scenario==='multi-pkr'?'PKR':'USD';$discount=str_contains($scenario,'promotion')?1000:0;$sellerDiscount=$scenario==='vendor-promotion'?$discount:0;
    $order=Order::factory()->create(['order_number'=>$number,'user_id'=>$customer->id,'currency'=>$currency,'status'=>'delivered','payment_status'=>'paid','items_subtotal'=>20000,'shipping_total'=>0,'tax_total'=>0,'discount_total'=>$discount,'grand_total'=>20000-$discount]);
    $vo=VendorOrder::factory()->create(['vendor_order_number'=>$number.'-01','vendor_id'=>$vendor->id,'order_id'=>$order->id,'status'=>'delivered','delivered_at'=>now()->subDays(30),'items_subtotal'=>20000,'shipping_total'=>0,'tax_total'=>0,'discount_total'=>$discount,'vendor_discount_total'=>$sellerDiscount,'marketplace_discount_total'=>$discount-$sellerDiscount,'grand_total'=>20000-$discount]);
    $item=OrderItem::factory()->create(['vendor_order_id'=>$vo->id,'product_name'=>'Seller finance demonstration item','quantity'=>1,'unit_price'=>20000,'line_total'=>20000,'discount_total'=>$discount,'vendor_discount_total'=>$sellerDiscount,'marketplace_discount_total'=>$discount-$sellerDiscount,'net_line_total'=>20000-$discount]);
    $payment=Payment::factory()->create(['order_id'=>$order->id,'currency'=>$currency,'amount'=>20000-$discount,'status'=>'paid','paid_at'=>now()->subDays(30)]);
    app(\App\Actions\Commissions\RecognizeVendorOrderCommission::class)->handle($vo);
    if(in_array($scenario,['paid','negative','pending','processing','rejected'],true)){
     $method=VendorPayoutMethod::factory()->create(['vendor_id'=>$vendor->id,'label'=>'Finance demonstration bank']);
     $amount=$scenario==='negative'?app(\App\Services\VendorPayouts\VendorPayoutBalanceService::class)->summaryFor($vendor,$currency)->requestableBalance:5000;
     $payout=app(\App\Actions\VendorPayouts\RequestVendorPayout::class)->handle($vendor,$user,$method,$amount,$currency);
     if($scenario==='rejected')app(\App\Actions\VendorPayouts\RejectVendorPayout::class)->handle($payout,$admin,'Demonstration rejection');
     elseif($scenario!=='pending'){
      app(\App\Actions\VendorPayouts\ApproveVendorPayout::class)->handle($payout,$admin);
      app(\App\Actions\VendorPayouts\StartVendorPayoutProcessing::class)->handle($payout->refresh(),$admin);
      if($scenario!=='processing')app(\App\Actions\VendorPayouts\MarkVendorPayoutPaid::class)->handle($payout->refresh(),$admin,'DEMO-MANUAL-'.$scenario);
     }
    }
    if(in_array($scenario,['refund','negative'],true)){
     // Explicit offline demo refund records; no external provider success is claimed.
     $refund=Refund::factory()->create(['refund_number'=>$number.'-REF','payment_id'=>$payment->id,'order_id'=>$order->id,'amount'=>10000,'currency'=>$currency,'requested_by'=>$admin->id,'reason'=>'Offline finance demonstration refund']);
     RefundAllocation::factory()->create(['refund_id'=>$refund->id,'vendor_order_id'=>$vo->id,'order_item_id'=>$item->id,'type'=>'item','amount'=>10000,'seller_amount'=>10000]);
     app(\App\Actions\Refunds\MarkRefundSucceeded::class)->handle($refund,'DEMO-OFFLINE-REFUND');
    }
   });
  }
 }
}
