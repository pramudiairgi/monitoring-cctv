<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Camera extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stream_url',
        'adaptive_url',
        'category_id',
        'status',
        'order',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
