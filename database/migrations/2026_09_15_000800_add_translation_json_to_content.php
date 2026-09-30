<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{foreach(['products','categories','cms_pages'] as $table)if(Schema::hasTable($table)&&!Schema::hasColumn($table,'translations'))Schema::table($table,fn(Blueprint $t)=>$t->json('translations')->nullable());}public function down():void{foreach(['products','categories','cms_pages'] as $table)if(Schema::hasColumn($table,'translations'))Schema::table($table,fn(Blueprint $t)=>$t->dropColumn('translations'));}};
