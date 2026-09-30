<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['status' => DisputeStatus::class, 'resolved_at' => 'datetime', 'last_activity_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function vendorOrder()
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    public function resolutionRefund()
    {
        return $this->belongsTo(Refund::class, 'resolution_refund_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages()
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('id');
    }

    public function history()
    {
        return $this->hasMany(DisputeHistory::class)->orderBy('id');
    }
}
