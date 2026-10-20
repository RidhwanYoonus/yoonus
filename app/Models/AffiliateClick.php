<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One visit through an affiliate link, or one product viewed by such a visitor. */
class AffiliateClick extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['white_page' => 'boolean'];
    }
}
