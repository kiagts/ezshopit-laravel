<?php
namespace Database\Seeders;
use App\Models\Market; use Illuminate\Database\Seeder;
class MarketSeeder extends Seeder {public function run():void{Market::updateOrCreate(['code'=>'pk'],['name'=>'Pakistan','country_code'=>'PK','default_currency_code'=>'USD','is_active'=>true,'is_default'=>true,'sort_order'=>1]);}}
