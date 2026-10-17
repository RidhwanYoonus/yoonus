<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Redirect $r) {
            $r->from_path = '/'.ltrim(parse_url($r->from_path, PHP_URL_PATH) ?? '', '/');
        });
        static::saved(fn () => cache()->forget('redirects.map'));
        static::deleted(fn () => cache()->forget('redirects.map'));
    }
}
