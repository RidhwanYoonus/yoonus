<?php

namespace App\Http\Controllers;

use App\Support\Policies;
use App\Support\Seo;

class PolicyController extends Controller
{
    public function show(string $slug)
    {
        $key = Policies::keyForSlug($slug);
        abort_unless($key, 404);
        $locale = app()->getLocale();

        return view('shop.policy', [
            'seo' => Seo::page(Policies::title($key)),
            'key' => $key,
            'title' => Policies::title($key),
            'custom' => Policies::custom($key, $locale),
            'sections' => Policies::sections($key, $locale),
        ]);
    }
}
