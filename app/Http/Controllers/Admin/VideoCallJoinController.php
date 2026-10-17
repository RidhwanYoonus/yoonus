<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoCallRequest;
use Illuminate\Http\Request;

class VideoCallJoinController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        abort_unless($request->user()?->is_admin, 403);
        $call = VideoCallRequest::findOrFail($id);
        if ($call->status === 'waiting') {
            $call->update(['status' => 'answered', 'staff_id' => $request->user()->id, 'answered_at' => now()]);
        }

        return redirect()->away($call->roomUrl().'#userInfo.displayName='.rawurlencode('"'.$request->user()->name.' (staff)"').'&config.prejoinPageEnabled=false');
    }
}
