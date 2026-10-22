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
        $data = $request->validate(self::idRules($request, $request->user()));
        self::saveId($request, $request->user(), $data);

        if ($request->input('back') === 'cart') {
            return redirect()->to(route('cart.show').'#cart-id')->with('status', __('shop.id.saved_ok'))->with('id_saved_here', true);
        }

        return redirect()->to(route('account').'#id')->with('status', __('shop.id.saved_ok'));
    }

    /** Rules for saving an ID (My account, or the optional step at sign-up). A tourist's ID must be a passport. */
    public static function idRules(Request $request, ?\App\Models\User $user): array
    {
        $photos = \App\Http\Controllers\CheckoutController::idPhotos();
        $file = ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:15360'];
        $types = $request->boolean('is_tourist') && \App\Support\TaxFree::enabled() ? ['passport'] : \App\Support\IdDocument::TYPES;

        return [
            'id_type' => ['required', \Illuminate\Validation\Rule::in($types)],
            'id_number' => ['required', 'string', 'max:32', \App\Support\IdDocument::numberRule($request->input('id_type'))],
            'id_expiry' => ['nullable', 'date', 'after:today'],   // optional: not every customer has it at hand
            'id_country' => ['nullable', 'required_if:id_type,passport', \Illuminate\Validation\Rule::in(array_keys(\App\Support\TaxFree::countries()))],
            'id_front' => [$photos === 'required' && ! $user?->id_front_path ? 'required' : 'nullable', ...$file],
            'id_back' => [$photos === 'required' && ! $user?->id_back_path ? 'required' : 'nullable', ...$file],
            'is_tourist' => ['nullable', 'boolean'],
        ];
    }

    public static function saveId(Request $request, \App\Models\User $user, array $data): void
    {
        $number = \App\Support\IdDocument::normalise($data['id_type'], $data['id_number']);
        $changed = $user->id_type !== $data['id_type'] || $user->id_number !== $number;
        $user->forceFill([
            'id_type' => $data['id_type'], 'id_number' => $number, 'id_last4' => \App\Support\IdDocument::last4($number), 'id_expiry' => $data['id_expiry'] ?? null,
            // A different ID drops the photos of the old one; the same ID keeps them unless new ones are added.
            'id_front_path' => $request->hasFile('id_front') ? \App\Support\IdDocument::store($request->file('id_front')) : ($changed ? null : $user->id_front_path),
            'id_back_path' => $request->hasFile('id_back') ? \App\Support\IdDocument::store($request->file('id_back')) : ($changed ? null : $user->id_back_path),
            'id_saved_at' => now(),
            ...(\Illuminate\Support\Facades\Schema::hasColumn('users', 'id_country') ? ['id_country' => $data['id_type'] === 'passport' ? ($data['id_country'] ?? null) : null] : []),
            ...(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_tourist') && $request->has('is_tourist') ? ['is_tourist' => $request->boolean('is_tourist')] : []),
        ])->save();
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
