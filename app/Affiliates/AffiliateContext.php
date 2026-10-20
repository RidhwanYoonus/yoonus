<?php

namespace App\Affiliates;

use App\Models\Affiliate;

/** Which affiliate (if any) sent the current visitor, and whether they came through the white page. */
class AffiliateContext
{
    public function __construct(public ?Affiliate $affiliate = null, public bool $whitePage = false) {}

    public function markupBp(): int
    {
        return $this->whitePage && $this->affiliate ? $this->affiliate->effectiveMarkupBp() : 0;
    }
}
