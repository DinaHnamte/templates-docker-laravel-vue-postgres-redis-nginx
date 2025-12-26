<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'availability_status',
        'vehicle_type',
        'license_number',
        'current_lat',
        'current_lng',
    ];

    protected $casts = [
        'current_lat' => 'float',
        'current_lng' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
