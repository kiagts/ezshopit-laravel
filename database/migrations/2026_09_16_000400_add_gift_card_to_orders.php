<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('orders',function(Blueprint $t){$t->unsignedBigInteger('gift_card_applied_total')->default(0);$t->string('gift_card_last_four',4)->nullable();});} public function down():void{Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['gift_card_applied_total','gift_card_last_four']));} };
