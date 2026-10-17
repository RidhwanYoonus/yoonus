<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'last_known_total' => 'decimal:2',
            'last_reminder_at' => 'datetime',
            'checkout_started_at' => 'datetime',
            'converted_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
