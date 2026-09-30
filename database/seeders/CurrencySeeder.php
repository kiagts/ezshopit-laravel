<?php
namespace Database\Seeders;
use App\Models\Currency; use Illuminate\Database\Seeder;
class CurrencySeeder extends Seeder {public function run():void{foreach([['USD','US Dollar','$'],['PKR','Pakistani Rupee','Rs'],['AED','UAE Dirham','د.إ'],['GBP','Pound Sterling','£'],['EUR','Euro','€']] as [$code,$name,$symbol])Currency::updateOrCreate(['code'=>$code],['name'=>$name,'symbol'=>$symbol,'minor_unit'=>2,'is_active'=>true]); foreach([['USD','PKR','280'],['USD','AED','3.67'],['USD','GBP','0.79'],['USD','EUR','0.92']] as [$base,$quote,$rate])\Illuminate\Support\Facades\DB::table('exchange_rates')->updateOrInsert(['base_currency_code'=>$base,'quote_currency_code'=>$quote],['rate'=>$rate,'effective_at'=>now(),'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);}}
