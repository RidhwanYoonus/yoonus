<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use Localized;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['flat_rate' => 'decimal:2', 'free_above' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
