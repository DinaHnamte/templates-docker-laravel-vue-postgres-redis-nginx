<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'formatted_address',
        'lat',
        'lng',
        'base_delivery_fee',
        'min_order_total',
        'allow_cod',
        'is_active',
    ];

    protected $casts = [
        'base_delivery_fee' => 'float',
        'min_order_total' => 'float',
        'allow_cod' => 'boolean',
        'is_active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
