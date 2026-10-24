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
            'platform' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(VideoCallRequest::PLATFORMS))],
        ]);

        $call = VideoCallRequest::create($data + [
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'locale' => app()->getLocale(),
        ]);

        $to = Settings::get('video_call_notify_email') ?: Settings::get('store_email');
        $line = $call->name.' ('.$call->phone.') wants a '.$call->platformName().' video call about '.$product->name.' ('.$product->sku.').';
        // Staff WhatsApp numbers (Settings → Video calls), so someone picks it up quickly.
        foreach (\App\Support\StaffAlerts::numbers('video_call_staff_whatsapp') as $number) {
            $messenger->whatsapp($number, "📹 {$line}\n".url('/admin/video-call-requests'), 'video_call_staff');
        }
        $messenger->email($to, new ShopMessage(
            'Live video call request: '.$product->name,
            [$line, 'Call them on '.$call->platformName().', or open the admin to send a meeting link.'],
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
        // The join link too: staff may have sent a new meeting link for this call.
        return response()->json(['status' => $call->status, 'join' => $call->customerJoinUrl()]);
    }
}
