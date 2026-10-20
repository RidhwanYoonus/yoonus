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
        $since = now()->subDays(30);
        $visits = $affiliate->clicks()->where('type', 'visit')->where('created_at', '>=', $since)->count();
        $orders = $affiliate->orders()->where('created_at', '>=', $since)->whereNotIn('payment_status', ['expired'])->count();

        return view('shop.affiliate', [
            'seo' => Seo::page(__('shop.affiliate.title')),
            'affiliate' => $affiliate,
            'totals' => $affiliate->totals(),
            'level' => $affiliate->level(),
            'stats' => [
                'visits' => $visits,
                'views' => $affiliate->clicks()->where('type', 'product')->where('created_at', '>=', $since)->count(),
                'orders' => $orders,
                'conversion' => $visits ? round(100 * $orders / $visits, 1) : 0,
            ],
            'commissions' => $affiliate->commissions()->with('order')->latest()->limit(100)->get(),
            'adjustments' => $affiliate->adjustments()->latest()->limit(50)->get(),
            'payouts' => $affiliate->payouts()->with('branch')->latest('paid_at')->get(),
            'campaigns' => \App\Models\AffiliateCampaign::running()->with('category')->get()->filter(fn ($c) => $c->appliesTo($affiliate))
                ->map(function ($c) use ($affiliate) {
                    if ($c->type === 'target') {
                        [$pieces, $sales] = app(\App\Affiliates\AffiliateService::class)->deliveredIn($affiliate, $c->starts_at, $c->ends_at);
                        $c->progress = $c->target_metric === 'sales' ? \App\Pricing\Money::toAed($sales) : $pieces;
                        $c->reached = $affiliate->adjustments()->where('campaign_id', $c->id)->exists();
                    }

                    return $c;
                }),
            'leaderboard' => $this->leaderboard(),
            'picks' => \App\Models\Product::with('goldCategory')->active()->where('stock_qty', '>', 0)->orderByDesc('is_featured')->latest()->take(6)->get(),
            'rates' => \App\Models\GoldCategory::where('is_active', true)->orderByDesc('karat')->get(),
            'locks' => $affiliate->can_lock_price ? \App\Models\AffiliatePriceLock::with('product')->where('affiliate_id', $affiliate->id)->latest()->limit(10)->get() : collect(),
            'lockProducts' => $affiliate->can_lock_price ? \App\Models\Product::active()->where('stock_qty', '>', 0)->orderBy('name')->get(['id', 'name', 'sku']) : collect(),
            'team' => $affiliate->recruits()->with('user')->get(),
            'visits' => \App\Models\StoreVisit::where('affiliate_id', $affiliate->id)->latest()->limit(10)->get(),
        ]);
    }

    /** Top 10 this month by sales (names only — amounts stay private). */
    private function leaderboard()
    {
        $start = now()->startOfMonth();

        return \App\Models\Order::query()->whereNotNull('affiliate_id')->where('created_at', '>=', $start)
            ->whereNotIn('status', ['cancelled', 'refunded'])->where('payment_status', '!=', 'expired')
            ->selectRaw('affiliate_id, count(*) as orders, sum(taxable_amount) as sales')->groupBy('affiliate_id')
            ->orderByDesc('sales')->limit(10)->get()
            ->map(fn ($row) => ['affiliate' => Affiliate::find($row->affiliate_id), 'orders' => (int) $row->orders])
            ->filter(fn ($r) => $r['affiliate']?->is_active)->values();
    }

    /** Affiliate freezes today's price of a piece for a customer (when the admin allows it). */
    public function lock(Request $request)
    {
        $affiliate = $this->affiliate($request);
        abort_unless($affiliate->can_lock_price && $affiliate->is_active, 403);
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id']);
        $product = \App\Models\Product::active()->findOrFail($data['product_id']);
        $lock = \App\Affiliates\PriceLocks::create($affiliate, $product);

        return back()->with('status', __('shop.affiliate.lock_created', ['hours' => \App\Affiliates\PriceLocks::hours()]))->with('new_lock', $lock->link());
    }

    /** Customer opens a price-lock link: price held, visitor linked to the affiliate. */
    public function openLock(Request $request, string $token)
    {
        $lock = \App\Models\AffiliatePriceLock::with('affiliate', 'product')->where('token', $token)->firstOrFail();
        if (! $lock->isValid() || ! $lock->product) {
            return redirect()->route($lock->product ? 'product.show' : 'home', $lock->product ?? [])->withErrors(['lock' => __('shop.affiliate.lock_expired')]);
        }
        \App\Affiliates\PriceLocks::remember($lock);
        if ($lock->affiliate->user_id !== $request->user()?->id) {
            $request->session()->put(\App\Http\Middleware\TrackAffiliate::COOKIE, $lock->affiliate->code.'|'.($lock->affiliate->white_page_enabled ? 'w' : 'r'));
        }

        return redirect()->route('product.show', $lock->product)
            ->with('status', __('shop.affiliate.lock_open', ['time' => $lock->expires_at->locale(app()->getLocale())->isoFormat('lll')]));
    }

    /** "Book a store visit" from the affiliate's page or a product page. */
    public function visit(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
            'preferred_at' => 'nullable|date|after:now',
            'branch_id' => 'nullable|exists:branches,id',
            'product_id' => 'nullable|exists:products,id',
            'message' => 'nullable|string|max:1000',
        ]);
        $context = app(\App\Affiliates\AffiliateContext::class);
        \App\Models\StoreVisit::create($data + ['affiliate_id' => $context->preview ? null : $context->affiliate?->id]);

        return back()->with('status', __('shop.affiliate.visit_booked'));
    }

    public function applyForm(Request $request)
    {
        abort_unless(Affiliate::signupEnabled(), 404);
        return view('shop.affiliate-apply', [
            'seo' => Seo::page(__('shop.affiliate.apply_title')),
            'ref' => ! Affiliate::teamEnabled() ? null : Affiliate::where('code', strtoupper((string) $request->query('ref')))->where('is_active', true)->first(),
        ]);
    }

    public function apply(Request $request)
    {
        abort_unless(Affiliate::signupEnabled(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:191',
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
            'social' => 'nullable|string|max:191',
            'message' => 'nullable|string|max:1000',
            'ref' => 'nullable|string|max:32',
            'website' => 'prohibited',   // bots fill the hidden field
        ]);
        $ref = Affiliate::teamEnabled() && filled($data['ref'] ?? null) ? Affiliate::where('code', strtoupper($data['ref']))->value('id') : null;
        \App\Models\AffiliateApplication::create(collect($data)->except(['ref', 'website'])->all() + ['referred_by_id' => $ref, 'email' => strtolower($data['email'])]);

        return redirect()->route('affiliate.apply')->with('status', __('shop.affiliate.apply_thanks'));
    }

    /** The affiliate sets their white-page name and mark-up, never above the admin's maximum. */
    public function update(Request $request)
    {
        $affiliate = $this->affiliate($request);
        abort_unless($affiliate->white_page_enabled, 403);
        // Each "in use" value may not go above the admin's maximum; a maximum of 0 means no limit.
        $capped = fn (string $max) => array_filter(['nullable', 'numeric', 'min:0', (float) $affiliate->{$max} > 0 ? 'max:'.(float) $affiliate->{$max} : null]);
        $pairs = [
            'making_markup_percent' => 'max_making_markup_percent', 'making_markup_per_gram' => 'max_making_markup_per_gram',
            'fixed_markup_percent' => 'max_fixed_markup_percent', 'fixed_markup_amount' => 'max_fixed_markup_amount',
            'markup_amount' => 'max_markup_amount',
        ];
        $data = $request->validate([
            'display_name' => 'nullable|string|max:80',
            'markup_mode' => 'required|in:percent,amount',
            'making_markup_type' => 'nullable|in:percent,per_gram',
            'fixed_markup_type' => 'nullable|in:percent,per_piece',
        ] + collect($pairs)->map(fn ($max) => $capped($max))->all(),
            collect($pairs)->keys()->mapWithKeys(fn ($f) => ["{$f}.max" => __('shop.affiliate.over_max', ['max' => ':max'])])->all());
        $affiliate->update([
            'display_name' => $data['display_name'] ?? null,
            'markup_mode' => $data['markup_mode'],
            'making_markup_type' => $data['making_markup_type'] ?? 'percent',
            'fixed_markup_type' => $data['fixed_markup_type'] ?? 'percent',
        ] + collect($pairs)->keys()->mapWithKeys(fn ($f) => [$f => $data[$f] ?? 0])->all());

        return back()->with('status', __('shop.affiliate.saved'));
    }

    private function affiliate(Request $request): Affiliate
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        return $affiliate;
    }
}
