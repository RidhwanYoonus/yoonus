<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Support\Seo;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /** /w/CODE — the affiliate's white page: the shop with their mark-up in every price. */
    public function page(string $code)
    {
        $affiliate = Affiliate::where('code', strtoupper($code))->where('is_active', true)->firstOrFail();

        return redirect()->route('home')->with('status', __('shop.affiliate.welcome', ['name' => $affiliate->name()]));
    }

    public function dashboard(Request $request)
    {
        $affiliate = $this->affiliate($request);

        return view('shop.affiliate', [
            'seo' => Seo::page(__('shop.affiliate.title')),
            'affiliate' => $affiliate,
            'totals' => $affiliate->totals(),
            'commissions' => $affiliate->commissions()->with('order')->latest()->limit(100)->get(),
            'payouts' => $affiliate->payouts()->with('branch')->latest('paid_at')->get(),
        ]);
    }

    /** The affiliate sets their white-page name and mark-up, never above the admin's maximum. */
    public function update(Request $request)
    {
        $affiliate = $this->affiliate($request);
        abort_unless($affiliate->white_page_enabled, 403);
        $data = $request->validate([
            'display_name' => 'nullable|string|max:80',
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:'.(float) $affiliate->max_markup_percent],
        ]);
        $affiliate->update($data);

        return back()->with('status', __('shop.affiliate.saved'));
    }

    private function affiliate(Request $request): Affiliate
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        return $affiliate;
    }
}
