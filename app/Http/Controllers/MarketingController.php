<?php

namespace App\Http\Controllers;

use App\Jobs\SyncNewsletterSubscriber;
use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\GiftCard;
use App\Models\GoldPriceAlert;
use App\Models\NewsletterSubscriber;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class MarketingController extends Controller
{
    /** Newsletter with double opt-in. */
    public function subscribe(Request $request, Messenger $messenger)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:191', 'website' => 'nullable|max:0']);   // `website` = honeypot
        $sub = NewsletterSubscriber::firstOrCreate(['email' => strtolower($data['email'])], ['locale' => app()->getLocale()]);

        if (! $sub->confirmed_at || $sub->unsubscribed_at) {
            $messenger->email($sub->email, new ShopMessage(
                __('shop.newsletter.confirm_subject'),
                [__('shop.newsletter.confirm_body')],
                __('shop.newsletter.confirm_cta'),
                URL::temporarySignedRoute('newsletter.confirm', now()->addDays(3), ['subscriber' => $sub->id]),
                app()->getLocale(),
            ), 'newsletter_confirm');
        }

        return back()->with('status', __('shop.newsletter.check_inbox'));
    }

    public function confirm(NewsletterSubscriber $subscriber)
    {
        $subscriber->update(['confirmed_at' => now(), 'unsubscribed_at' => null]);
        SyncNewsletterSubscriber::dispatch($subscriber->id);

        return redirect()->route('home')->with('status', __('shop.newsletter.confirmed'));
    }

    public function unsubscribe(NewsletterSubscriber $subscriber)
    {
        $subscriber->update(['unsubscribed_at' => now()]);

        return redirect()->route('home')->with('status', __('shop.newsletter.unsubscribed'));
    }

    public function alert(Request $request)
    {
        $data = $request->validate([
            'gold_category_id' => 'required|exists:gold_categories,id',
            'email' => 'required_without:phone|nullable|email:rfc|max:191',
            'phone' => 'required_without:email|nullable|string|max:32',
            'target_rate' => 'nullable|numeric|min:1',
        ]);
        GoldPriceAlert::updateOrCreate(
            ['gold_category_id' => $data['gold_category_id'], 'email' => isset($data['email']) ? strtolower($data['email']) : null, 'phone' => $data['phone'] ?? null],
            ['target_rate' => $data['target_rate'] ?? null, 'locale' => app()->getLocale(), 'is_active' => true],
        );

        return back()->with('status', __('shop.alerts.subscribed'));
    }

    public function giftCards(Request $request)
    {
        $card = null;
        if ($code = $request->query('code')) {
            $card = GiftCard::where('code', strtoupper(trim($code)))->first();
        }

        return view('shop.gift-cards', ['seo' => Seo::page(__('shop.gift_card.title')), 'card' => $card, 'checked' => (bool) $code]);
    }
}
