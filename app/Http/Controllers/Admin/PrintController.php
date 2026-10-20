<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/** Printable copy of an admin report, made by the "Print" button on a list. */
class PrintController extends Controller
{
    public function __invoke(string $token)
    {
        $data = Cache::get('print:'.$token);
        abort_unless($data && $data['user_id'] === auth()->id(), 404);

        $tables = $data['tables'] ?? [['title' => null, 'headings' => $data['headings'], 'rows' => $data['rows']]];

        return view('admin.print', ['title' => $data['title'], 'tables' => $tables, 'shop' => Settings::get('store_name', config('app.name'))]);
    }
}
