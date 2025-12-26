<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'caused_by',
        'note',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'caused_by');
    }
}
