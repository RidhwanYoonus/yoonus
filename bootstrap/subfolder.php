<?php

/**
 * The shop can live in a sub-folder (e.g. https://example.com/newweb) with the folder's .htaccess sending
 * requests into public/. The web server then reports the script as /newweb/public/index.php, so Laravel would
 * not see /newweb as the shop's base and every page would be "404". Present the script as /newweb/index.php
 * instead. On a normal set-up (domain pointing at public/) nothing changes.
 */
return static function (array $server): array {
    $script = (string) ($server['SCRIPT_NAME'] ?? '');
    $uri = (string) ($server['REQUEST_URI'] ?? '/');
    if (! str_ends_with($script, '/public/index.php')) {
        return $server;
    }
    $base = substr($script, 0, -strlen('/public/index.php'));   // '' or '/newweb'
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    if (str_starts_with($path, $base.'/public/') || $path === $base.'/public') {
        return $server;   // someone typed /public/… themselves: leave it
    }
    if ($base !== '') {
        // Laravel's cached route list drops the folder from the home page address (error 405): never use it here.
        foreach (glob(__DIR__.'/cache/routes-v*.php') ?: [] as $cached) {
            @unlink($cached);
        }
    }
    if ($base === '' || $path === $base || str_starts_with($path, $base.'/')) {
        $server['SCRIPT_NAME'] = $base.'/index.php';
        $server['PHP_SELF'] = $base.'/index.php';
        if ($base !== '' && $path === $base) {   // "/newweb" without the closing slash = the home page
            $server['REQUEST_URI'] = $base.'/'.substr($uri, strlen($base));
        }
    }

    return $server;
};
