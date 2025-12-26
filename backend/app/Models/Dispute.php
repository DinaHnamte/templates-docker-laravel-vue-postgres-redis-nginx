<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'opened_by',
        'status',
        'reason',
        'resolution_note',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}

