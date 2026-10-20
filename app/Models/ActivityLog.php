<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step a visitor took on the site (page, add to bag, order…), for Customers → Activity log and Live visitors. */
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected $guarded = ['id'];

    public const EVENTS = [
        'page_view' => 'Viewed page', 'browse' => 'Browsed shop', 'product_view' => 'Viewed product', 'search' => 'Searched',
        'view_bag' => 'Viewed bag', 'add_to_bag' => 'Added to bag', 'change_qty' => 'Changed quantity', 'remove_from_bag' => 'Removed from bag',
        'cart_recovered' => 'Opened bag reminder', 'checkout' => 'Opened checkout', 'order_placed' => 'Placed order', 'checkout_failed' => 'Checkout not completed',
        'order_complete' => 'Order confirmation', 'view_order' => 'Viewed order', 'download_invoice' => 'Downloaded invoice', 'retry_payment' => 'Retried payment',
        'payment_return' => 'Back from payment', 'track_order' => 'Tracked order',
        'login' => 'Signed in', 'login_failed' => 'Sign-in failed', 'logout' => 'Signed out', 'register' => 'Created account', 'login_google' => 'Signed in with Google',
        'account' => 'Updated account', 'phone_verify' => 'Phone verification',
        'wishlist' => 'Wishlist', 'compare' => 'Compare', 'review' => 'Wrote review', 'video_call' => 'Asked for video call', 'store_visit' => 'Booked store visit',
        'gold_alert' => 'Set gold alert', 'newsletter' => 'Newsletter sign-up',
        'affiliate_page' => 'Affiliate page', 'affiliate' => 'Affiliate dashboard', 'price_lock' => 'Opened price-lock link',
        'admin_page' => 'Admin page', 'not_found' => 'Page not found',
    ];

    /** Events worth a colour in the log. */
    public const COLORS = [
        'order_placed' => 'success', 'order_complete' => 'success', 'add_to_bag' => 'warning', 'checkout' => 'warning', 'checkout_failed' => 'danger',
        'login_failed' => 'danger', 'not_found' => 'danger', 'login' => 'info', 'register' => 'info', 'login_google' => 'info', 'logout' => 'gray',
        'remove_from_bag' => 'gray', 'admin_page' => 'gray', 'search' => 'info',
    ];

    protected function casts(): array
    {
        return ['details' => 'array', 'is_staff' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }

    public function who(): string
    {
        return $this->user ? $this->user->name.' · '.$this->user->email : ($this->email ? 'Guest · '.$this->email : 'Guest');
    }

    /** "iPhone · Safari" style label from the browser's user agent. */
    public static function device(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => (str_contains($ua, 'Mobile') ? 'Android phone' : 'Android tablet'),
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'CrOS') => 'Chromebook', str_contains($ua, 'Linux') => 'Linux',
            default => 'Other',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'FxiOS') || str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari', default => 'Browser',
        };

        return $os.' · '.$browser;
    }
}
