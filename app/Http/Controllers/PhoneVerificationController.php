<?php

namespace App\Http\Controllers;

use App\Shop\PhoneVerification;
use App\Support\Seo;
use Illuminate\Http\Request;
use RuntimeException;

class PhoneVerificationController extends Controller
{
    public function __construct(private readonly PhoneVerification $verification) {}

    public function show(Request $request)
    {
        return view('shop.verify-phone', ['seo' => Seo::page(__('shop.verify.title')), 'user' => $request->user()]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
            'channel' => 'nullable|in:whatsapp,sms,email',
            'email' => 'nullable|required_if:channel,email|email:rfc|max:191',
        ], ['email.required_if' => __('shop.verify.email_needed')]);
        $channel = in_array($data['channel'] ?? 'whatsapp', PhoneVerification::channels(), true) ? ($data['channel'] ?? 'whatsapp') : 'whatsapp';
        try {
            $code = $this->verification->send($data['phone'], app()->getLocale(), $channel, $data['email'] ?? $request->user()?->email);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_filter([
            'message' => __('shop.verify.sent_'.$channel),
            'test_code' => $code,   // only while WhatsApp is not set up (testing)
        ]));
    }

    public function check(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:32', 'code' => 'required|string|max:10']);
        try {
            $this->verification->check($request, $data['phone'], $data['code']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['verified' => true, 'phone' => PhoneVerification::normalise($data['phone']), 'message' => __('shop.verify.done')]);
    }
}
