<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** A diamond grading lab (GIA, IGI…) picked on a product's certificate, shown with its small logo. */
class CertificateLab extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function label(): string
    {
        return $this->short_name ?: $this->name;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    /** The lab's report-check page for a certificate number, when the lab has one. */
    public function verifyUrl(?string $number): ?string
    {
        if (! $this->verify_url || ! filled($number)) {
            return null;
        }

        return str_contains($this->verify_url, '{number}') ? str_replace('{number}', rawurlencode($number), $this->verify_url) : $this->verify_url;
    }
}
