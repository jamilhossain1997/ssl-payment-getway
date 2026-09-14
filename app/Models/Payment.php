<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'tran_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'val_id',
        'bank_tran_id',
        'card_type',
        'card_issuer',
        'raw_init_response',
        'raw_ipn_payload',
        'raw_validation_response',
        'paid_at',
    ];

    protected $casts = [
        'raw_init_response' => 'array',
        'raw_ipn_payload' => 'array',
        'raw_validation_response' => 'array',
        'paid_at' => 'datetime',
    ];
}
