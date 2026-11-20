<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentPage extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'route_name', 'visits', 'visited_at'];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
