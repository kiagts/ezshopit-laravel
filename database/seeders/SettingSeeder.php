<?php
namespace Database\Seeders; use App\Models\Setting; use Illuminate\Database\Seeder;
class SettingSeeder extends Seeder { public function run():void { foreach([['general','marketplace_name','EzShopIt'],['general','tagline','A cleaner marketplace for trusted shopping.'],['contact','support_email','support@ezshopit.example'],['seo','default_title','EzShopIt | Trusted marketplace'],['seo','default_description','Shop quality products from verified marketplace sellers.']] as [$group,$key,$value]) Setting::updateOrCreate(['group'=>$group,'key'=>$key],['value'=>$value]); } }
