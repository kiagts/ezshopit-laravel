<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{if(!Schema::hasColumn('products','tax_class_id'))Schema::table('products',function(Blueprint $t){$t->foreignId('tax_class_id')->nullable()->after('category_id')->constrained('tax_classes')->nullOnDelete();});}public function down():void{if(Schema::hasColumn('products','tax_class_id'))Schema::table('products',fn(Blueprint $t)=>$t->dropConstrainedForeignId('tax_class_id'));}};
