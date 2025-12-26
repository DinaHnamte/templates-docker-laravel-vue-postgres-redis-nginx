<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'status',
        'provider_payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
