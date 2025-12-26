<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'amount',
        'distance_km',
        'eta_minutes',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'distance_km' => 'float',
        'eta_minutes' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
