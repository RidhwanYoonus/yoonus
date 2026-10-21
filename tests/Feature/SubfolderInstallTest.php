<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class SubfolderInstallTest extends TestCase
{
    private function fix(array $server): array
    {
        return (require base_path('bootstrap/subfolder.php'))($server);
    }

    private function request(string $uri, string $script): Request
    {
        $server = $this->fix(['SCRIPT_NAME' => $script, 'PHP_SELF' => $script, 'SCRIPT_FILENAME' => '/home/u/site/newweb/public/index.php',
            'REQUEST_URI' => $uri, 'HTTP_HOST' => 'almasroorjewellery.com', 'HTTPS' => 'on']);

        return Request::create($server['REQUEST_URI'], 'GET', server: $server);
    }

    public function test_shop_in_a_sub_folder_gets_the_right_base_and_pages(): void
    {
        foreach (['/newweb/' => '/', '/newweb' => '/', '/newweb/shop?karat[]=1' => '/shop', '/newweb/admin/login' => '/admin/login', '/newweb/build/assets/app.css' => '/build/assets/app.css'] as $uri => $path) {
            $r = $this->request($uri, '/newweb/public/index.php');
            $this->assertSame('/newweb', $r->getBaseUrl(), $uri);
            $this->assertSame($path, $r->getPathInfo(), $uri);
        }
    }

    public function test_normal_domain_set_ups_are_untouched(): void
    {
        // Domain pointing at public/ (the later live set-up).
        $this->assertSame(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/shop'], $this->fix(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/shop']));
        // Files directly in public_html with the root .htaccess.
        $r = $this->request('/shop', '/public/index.php');
        $this->assertSame('', $r->getBaseUrl());
        $this->assertSame('/shop', $r->getPathInfo());
    }

    public function test_links_and_files_use_the_sub_folder(): void
    {
        $r = $this->request('/newweb/shop', '/newweb/public/index.php');
        $this->app->instance('request', $r);
        app('url')->setRequest($r);
        $this->assertSame('https://almasroorjewellery.com/newweb/product/band', url('/product/band'));
        $this->assertSame('https://almasroorjewellery.com/newweb/build/x.css', asset('build/x.css'));
    }

    public function test_admin_forms_post_to_the_sub_folder(): void
    {
        $html = '<script src="/newweb/livewire/livewire.min.js" data-update-uri="/livewire/update"></script>';
        $response = new \Illuminate\Http\Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        \App\Support\SubfolderLivewire::handle(new \Illuminate\Foundation\Http\Events\RequestHandled($this->request('/newweb/admin/login', '/newweb/public/index.php'), $response));
        $this->assertStringContainsString('data-update-uri="/newweb/livewire/update"', $response->getContent());

        // Normal domain: untouched.
        $response = new \Illuminate\Http\Response($html, 200, ['Content-Type' => 'text/html']);
        \App\Support\SubfolderLivewire::handle(new \Illuminate\Foundation\Http\Events\RequestHandled(Request::create('/admin/login'), $response));
        $this->assertSame($html, $response->getContent());
    }
}
