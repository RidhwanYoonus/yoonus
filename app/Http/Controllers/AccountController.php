<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\ShippingZone;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('shop.account', [
            'seo' => Seo::page(__('shop.nav.account')),
            'user' => $user,
            'orders' => $user->orders()->with('items')->take(20)->get(),
            'points' => $user->loyaltyBalance(),
            'pointValue' => (float) Settings::get('loyalty_point_value', 0.10),
            'loyalty' => $user->loyaltyEntries()->take(10)->get(),
            'addresses' => $user->addresses,
            'zones' => ShippingZone::where('is_active', true)->orderBy('sort_order')->get(),
            'coupons' => Coupon::currentlyValid()->where('issued_to_user_id', $user->id)->get(),
            'karats' => GoldCategory::where('is_active', true)->orderByDesc('karat')->get(),
            'referralLink' => route('register', ['ref' => $user->referral_code]),
        ]);
    }

    public function storeAddress(Request $request)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:32',
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:32',
            'line1' => 'required|string|max:191',
            'line2' => 'nullable|string|max:191',
            'city' => 'required|string|max:64',
            'zone_code' => 'required|exists:shipping_zones,code',
        ]);
        $request->user()->addresses()->create($data + ['is_default' => ! $request->user()->addresses()->exists()]);

        return back()->with('status', __('shop.account.address_saved'));
    }

    public function deleteAddress(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $address->delete();

        return back();
    }

    /** Forget the saved ID (copies kept with past orders for delivery records stay with those orders). */
    public function forgetId(Request $request)
    {
        $request->user()->forceFill(['id_type' => null, 'id_number' => null, 'id_last4' => null, 'id_expiry' => null,
            'id_front_path' => null, 'id_back_path' => null, 'id_saved_at' => null])->save();

        return back();
    }
}
