<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'lat',
        'lng',
        'captured_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'captured_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}
