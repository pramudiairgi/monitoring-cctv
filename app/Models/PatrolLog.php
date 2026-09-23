<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatrolLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'total_cameras',
        'online_count',
        'offline_count',
        'status_changed_count',
        'patrol_online_count',
        'patrol_offline_count',
        'details',
        'checked_at',
    ];

    protected $casts = [
        'details' => 'array',
        'checked_at' => 'datetime',
    ];
}
