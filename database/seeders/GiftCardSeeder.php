<?php
namespace Database\Seeders;
use App\Models\User; use App\Services\GiftCardService; use Illuminate\Database\Seeder;
class GiftCardSeeder extends Seeder { public function run():void{$user=User::where('email','customer@example.com')->first();if(!$user)return;$s=app(GiftCardService::class);if(\App\Models\GiftCard::where('recipient_email','demo@example.com')->exists())return;$s->issue(5000,'USD',null,['recipient_email'=>'demo@example.com','message'=>'Developer demo card']);} }
