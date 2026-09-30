<?php
namespace Database\Seeders;use App\Models\Language;use Illuminate\Database\Seeder;
class LanguageSeeder extends Seeder{public function run():void{foreach([['en','English','English','ltr',true],['ur','Urdu','اردو','rtl',false],['ar','Arabic','العربية','rtl',false]] as [$code,$name,$native,$dir,$default])Language::updateOrCreate(['code'=>$code],['name'=>$name,'native_name'=>$native,'direction'=>$dir,'is_active'=>true,'is_default'=>$default]);}}
