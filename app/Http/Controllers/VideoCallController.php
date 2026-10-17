<?php

namespace App\Http\Controllers;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Product;
use App\Models\VideoCallRequest;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;

/** "Live video call with our staff" from a product page. Staff answer the request from the admin. */
class VideoCallController extends Controller
{
    public function store(Request $request, Product $product, Messenger $messenger)
    {
        abort_unless($product->is_active && $product->videoCallAvailable(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
        ]);

        $call = VideoCallRequest::create($data + [
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'locale' => app()->getLocale(),
        ]);

        $to = Settings::get('video_call_notify_email') ?: Settings::get('store_email');
        $messenger->email($to, new ShopMessage(
            'Live video call request: '.$product->name,
            [$call->name.' ('.$call->phone.') is waiting for a video call about '.$product->name.' ('.$product->sku.').', 'Open the admin and press "Join call".'],
            'Open video calls',
            url('/admin/video-call-requests'),
        ), 'video_call_staff');

        return redirect()->route('video-call.show', $call);
    }

    public function show(VideoCallRequest $call)
    {
        $call->load('product');

        return view('shop.video-call', [
            'seo' => Seo::page(__('shop.video.title')),
            'call' => $call,
            'whatsapp' => Settings::get('whatsapp_number'),
        ]);
    }

    /** Polled by the waiting page so the customer sees when staff have joined. */
    public function status(VideoCallRequest $call)
    {
        return response()->json(['status' => $call->status]);
    }
}
