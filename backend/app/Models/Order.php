<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'vendor_id',
        'address_id',
        'fulfillment_type',
        'status',
        'subtotal',
        'delivery_fee',
        'service_fee',
        'tax',
        'discount',
        'total',
        'locked_at',
        'placed_at',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'delivery_fee' => 'float',
        'service_fee' => 'float',
        'tax' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'locked_at' => 'datetime',
        'placed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusEvents()
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function assignment()
    {
        return $this->hasOne(Assignment::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
