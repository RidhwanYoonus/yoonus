<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Support\Seo;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /**
     * /w/CODE — the affiliate's own page: their name on top and the collection with their
     * mark-up in every price. The visitor stays linked to them while browsing the shop.
     */
    public function page(Request $request, string $code)
    {
        $affiliate = Affiliate::where('code', strtoupper($code))->where('is_active', true)->firstOrFail();
        $filters = $request->validate(['category' => 'nullable|integer', 'q' => 'nullable|string|max:100', 'sort' => 'nullable|in:newest,price_asc,price_desc']);
        $query = \App\Models\Product::with('goldCategory', 'category')->active()
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('name_ar', 'like', "%{$s}%")));
        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('cached_subtotal'),
            'price_desc' => $query->orderByDesc('cached_subtotal'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        return view('shop.affiliate-page', [
            'seo' => Seo::page($affiliate->name()),
            'affiliate' => $affiliate,
            'products' => $query->paginate(24)->withQueryString(),
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $filters,
            'preview' => app(\App\Affiliates\AffiliateContext::class)->preview,
        ]);
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
            'markup_mode' => 'required|in:percent,amount',
            'making_markup_percent' => ['required_if:markup_mode,percent', 'nullable', 'numeric', 'min:0', 'max:'.(float) $affiliate->max_making_markup_percent],
            'fixed_markup_percent' => ['required_if:markup_mode,percent', 'nullable', 'numeric', 'min:0', 'max:'.(float) $affiliate->max_fixed_markup_percent],
            'markup_amount' => ['required_if:markup_mode,amount', 'nullable', 'numeric', 'min:0', 'max:'.(float) $affiliate->max_markup_amount],
        ]);
        $affiliate->update([
            'display_name' => $data['display_name'] ?? null,
            'markup_mode' => $data['markup_mode'],
            'making_markup_percent' => $data['making_markup_percent'] ?? 0,
            'fixed_markup_percent' => $data['fixed_markup_percent'] ?? 0,
            'markup_amount' => $data['markup_amount'] ?? 0,
        ]);

        return back()->with('status', __('shop.affiliate.saved'));
    }

    private function affiliate(Request $request): Affiliate
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        return $affiliate;
    }
}
