<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use Localized;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pickup_enabled' => 'boolean'];
    }

    public function stock(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }
}
