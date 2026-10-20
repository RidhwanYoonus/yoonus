<?php

namespace App\Affiliates;

use App\Models\Affiliate;

/** Which affiliate (if any) sent the current visitor, and whether they came through the white page. */
class AffiliateContext
{
    /** $preview: the affiliate looking at their own page — prices shown with the mark-up, nothing is tracked. */
    public function __construct(public ?Affiliate $affiliate = null, public bool $whitePage = false, public bool $preview = false) {}

    public function markup(): \App\Pricing\Markup
    {
        return $this->whitePage && $this->affiliate ? $this->affiliate->effectiveMarkup() : \App\Pricing\Markup::none();
    }
}
