<?php
namespace Database\Seeders;
use App\Enums\NavigationMenuLocation; use App\Models\NavigationMenu; use Illuminate\Database\Seeder;
class NavigationSeeder extends Seeder { public function run():void { foreach(NavigationMenuLocation::cases() as $loc) NavigationMenu::firstOrCreate(['location'=>$loc->value],['name'=>$loc->label(),'is_active'=>true]); } }
