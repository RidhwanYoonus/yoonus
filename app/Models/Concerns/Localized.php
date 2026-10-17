<?php

namespace App\Models\Concerns;

/** Reads `<field>_ar` when the storefront is in Arabic, falling back to English. */
trait Localized
{
    public function localized(string $field): ?string
    {
        if (app()->getLocale() === 'ar' && filled($this->{$field.'_ar'} ?? null)) {
            return $this->{$field.'_ar'};
        }

        return $this->{$field};
    }
}
