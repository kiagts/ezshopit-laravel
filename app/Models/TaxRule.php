<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaxRule extends Model{protected $fillable=['tax_class_id','country_code','region_code','rate_basis_points','is_compound','is_active','priority'];protected function casts():array{return ['rate_basis_points'=>'integer','is_compound'=>'boolean','is_active'=>'boolean','priority'=>'integer'];}public function taxClass():BelongsTo{return $this->belongsTo(TaxClass::class);}}
