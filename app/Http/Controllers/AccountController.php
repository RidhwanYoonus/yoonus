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

    /** Customer adds or replaces their ID in My account, so checkout is one tap later. */
    public function storeId(Request $request)
    {
        $photos = \App\Http\Controllers\CheckoutController::idPhotos();
        $file = ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:15360'];
        $data = $request->validate([
            'id_type' => ['required', \Illuminate\Validation\Rule::in(\App\Support\IdDocument::TYPES)],
            'id_number' => ['required', 'string', 'max:32', \App\Support\IdDocument::numberRule($request->input('id_type'))],
            'id_expiry' => ['required', 'date', 'after:today'],
            'id_country' => ['nullable', 'required_if:id_type,passport', \Illuminate\Validation\Rule::in(array_keys(\App\Support\TaxFree::countries()))],
            'id_front' => [$photos === 'required' && ! $request->user()->id_front_path ? 'required' : 'nullable', ...$file],
            'id_back' => [$photos === 'required' && ! $request->user()->id_back_path ? 'required' : 'nullable', ...$file],
        ]);
        $user = $request->user();
        $number = \App\Support\IdDocument::normalise($data['id_type'], $data['id_number']);
        $changed = $user->id_type !== $data['id_type'] || $user->id_number !== $number;
        $user->forceFill([
            'id_type' => $data['id_type'], 'id_number' => $number, 'id_last4' => \App\Support\IdDocument::last4($number), 'id_expiry' => $data['id_expiry'],
            // A different ID drops the photos of the old one; the same ID keeps them unless new ones are added.
            'id_front_path' => $request->hasFile('id_front') ? \App\Support\IdDocument::store($request->file('id_front')) : ($changed ? null : $user->id_front_path),
            'id_back_path' => $request->hasFile('id_back') ? \App\Support\IdDocument::store($request->file('id_back')) : ($changed ? null : $user->id_back_path),
            'id_saved_at' => now(),
            ...(\Illuminate\Support\Facades\Schema::hasColumn('users', 'id_country') ? ['id_country' => $data['id_type'] === 'passport' ? ($data['id_country'] ?? null) : null] : []),
        ])->save();

        return redirect()->to(route('account').'#id')->with('status', __('shop.id.saved_ok'));
    }

    /** Forget the saved ID (copies kept with past orders for delivery records stay with those orders). */
    public function forgetId(Request $request)
    {
        $request->user()->forceFill(['id_type' => null, 'id_number' => null, 'id_last4' => null, 'id_expiry' => null,
            'id_front_path' => null, 'id_back_path' => null, 'id_saved_at' => null,
            ...(\Illuminate\Support\Facades\Schema::hasColumn('users', 'id_country') ? ['id_country' => null] : [])])->save();

        return back();
    }
}
