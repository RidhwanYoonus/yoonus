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
        return ['pickup_enabled' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    /** Google Maps embed (no API key needed): exact pin when coordinates are set, else the address. */
    public function mapEmbedUrl(): string
    {
        $q = $this->latitude && $this->longitude ? $this->latitude.','.$this->longitude : $this->address.', '.$this->emirate.', UAE';

        return 'https://maps.google.com/maps?q='.rawurlencode($q).'&z=16&output=embed&hl='.app()->getLocale();
    }

    /** "Get directions" link: the shop's own Google Maps link if pasted, else a directions search. */
    public function directionsUrl(): string
    {
        if ($this->map_url) {
            return $this->map_url;
        }
        $q = $this->latitude && $this->longitude ? $this->latitude.','.$this->longitude : $this->address.', '.$this->emirate.', UAE';

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($q);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }
}
