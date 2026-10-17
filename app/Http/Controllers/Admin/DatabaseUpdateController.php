<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DatabaseUpdateController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('optimize:clear');
            $request->session()->flash('db_update', ['ok' => true, 'message' => 'Database updated.']);
        } catch (\Throwable $e) {
            report($e);
            $request->session()->flash('db_update', ['ok' => false, 'message' => 'Database update failed: '.$e->getMessage()]);
        }

        return back();
    }
}
