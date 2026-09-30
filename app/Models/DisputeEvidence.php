<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeEvidence extends Model
{
    protected $table = 'dispute_evidence';

    protected $guarded = ['id'];

    public function message()
    {
        return $this->belongsTo(DisputeMessage::class, 'dispute_message_id');
    }
}
