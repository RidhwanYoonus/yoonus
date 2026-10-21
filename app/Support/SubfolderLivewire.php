<?php

namespace App\Support;

use Illuminate\Foundation\Http\Events\RequestHandled;

/**
 * Shop in a sub-folder (e.g. example.com/newweb): Livewire prints its "send form here" address as /livewire/update,
 * without the folder, so admin forms and sign-in would go to the wrong place. Add the folder in the page.
 */
class SubfolderLivewire
{
    /** Runs after Livewire has added its script to the page (Livewire does that once the request is handled). */
    public static function handle(RequestHandled $event): void
    {
        $response = $event->response;
        $base = rtrim($event->request->getBaseUrl(), '/');
        if ($base === '' || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }
        $html = $response->getContent();
        if (is_string($html) && str_contains($html, '/livewire/update')) {
            $response->setContent(strtr($html, [
                'data-update-uri="/livewire/update"' => 'data-update-uri="'.$base.'/livewire/update"',
                '"uri":"\/livewire\/update"' => '"uri":"'.str_replace('/', '\/', $base).'\/livewire\/update"',
            ]));
        }
    }
}
