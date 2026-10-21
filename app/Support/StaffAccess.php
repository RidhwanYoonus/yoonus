<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may do what in the admin. Admins can do everything; staff only what is ticked for them
 * in Staff & permissions (per area: view / create / edit / delete).
 */
class StaffAccess
{
    public const ACTIONS = ['view' => 'View', 'create' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete'];

    /** area => [label, group] — one entry per admin screen. */
    public const AREAS = [
        'orders' => ['Orders (incl. status, handover, invoices)', 'Sales'],
        'pending_carts' => ['Pending carts', 'Sales'],
        'store_visits' => ['Store visit bookings', 'Sales'],
        'reports' => ['Reports & dashboard figures', 'Sales'],
        'gold_rates' => ['Gold rates (update / fetch rates)', 'Gold rates'],
        'products' => ['Products (incl. import CSV, show / hide in shop)', 'Catalogue'],
        'categories' => ['Categories', 'Catalogue'],
        'certificate_labs' => ['Certificate labs', 'Catalogue'],
        'campaigns' => ['Campaigns', 'Offers'],
        'coupons' => ['Coupons', 'Offers'],
        'gift_cards' => ['Gift cards', 'Offers'],
        'customers' => ['Customers', 'Customers'],
        'reviews' => ['Review moderation', 'Customers'],
        'video_calls' => ['Live video calls', 'Customers'],
        'activity' => ['Live visitors & activity log', 'Customers'],
        'affiliates' => ['Affiliate accounts (add, edit, disable, links)', 'Affiliates'],
        'affiliate_commissions' => ['Commission report, in-store sale credit, adjustments', 'Affiliates'],
        'affiliate_payouts' => ['Collections (hand over affiliate money)', 'Affiliates'],
        'affiliate_campaigns' => ['Boosters & targets', 'Affiliates'],
        'affiliate_applications' => ['Affiliate applications (approve / reject)', 'Affiliates'],
        'newsletter' => ['Newsletter', 'Marketing'],
        'gold_alerts' => ['Gold price alerts', 'Marketing'],
        'messages' => ['Messages sent', 'Marketing'],
        'redirects' => ['301 redirects', 'Marketing'],
        'export' => ['Print / Export lists (Excel / CSV, includes customer details)', 'Data'],
        'branches' => ['Branches & pickup', 'Settings'],
        'shipping_zones' => ['Delivery zones', 'Settings'],
        'vat' => ['VAT', 'Settings'],
        'settings' => ['Settings page (payments, messaging, store details)', 'Settings'],
    ];

    /** Only admins, whatever is ticked: shown on the staff form so nobody looks for them. */
    public const ADMIN_ONLY = [
        'Staff & permissions', 'Security log', 'Settings → Security tab (two-step sign-in, idle sign-out, activity log)',
        'Settings → Delete data', 'Undo last order status', 'Affiliate bonus / correction', 'Database update button',
    ];

    /** Ready-made permission sets for common jobs. */
    public const PRESETS = [
        'sales' => ['Sales / shop floor', ['orders' => ['view', 'edit'], 'pending_carts' => ['view'], 'store_visits' => ['view', 'edit'], 'customers' => ['view'],
            'video_calls' => ['view', 'edit'], 'activity' => ['view'], 'products' => ['view'], 'gold_rates' => ['view']]],
        'delivery' => ['Delivery / pickup counter', ['orders' => ['view', 'edit'], 'store_visits' => ['view']]],
        'catalogue' => ['Catalogue manager', ['products' => ['view', 'create', 'edit', 'delete'], 'categories' => ['view', 'create', 'edit', 'delete'],
            'certificate_labs' => ['view', 'create', 'edit'], 'gold_rates' => ['view', 'edit'], 'reviews' => ['view', 'edit', 'delete'], 'redirects' => ['view', 'create', 'edit']]],
        'marketing' => ['Marketing', ['campaigns' => ['view', 'create', 'edit', 'delete'], 'coupons' => ['view', 'create', 'edit', 'delete'], 'gift_cards' => ['view', 'create', 'edit'],
            'newsletter' => ['view'], 'gold_alerts' => ['view'], 'messages' => ['view'], 'pending_carts' => ['view'], 'reports' => ['view'], 'activity' => ['view'],
            'affiliate_campaigns' => ['view', 'create', 'edit']]],
        'affiliate_manager' => ['Affiliate manager', ['affiliates' => ['view', 'create', 'edit'], 'affiliate_commissions' => ['view', 'edit'], 'affiliate_payouts' => ['view'],
            'affiliate_campaigns' => ['view', 'create', 'edit', 'delete'], 'affiliate_applications' => ['view', 'edit'], 'store_visits' => ['view', 'edit']]],
        'cashier' => ['Cashier (affiliate collections)', ['affiliates' => ['view'], 'affiliate_commissions' => ['view'], 'affiliate_payouts' => ['view', 'create'], 'orders' => ['view']]],
        'accounts' => ['Accounts', ['orders' => ['view'], 'reports' => ['view'], 'customers' => ['view'], 'gift_cards' => ['view'],
            'affiliate_commissions' => ['view'], 'affiliate_payouts' => ['view'], 'export' => ['view']]],
    ];

    public static function can(?User $user, string $area, string $action = 'view'): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }
        if (! $user->is_staff) {
            return false;
        }
        $granted = (array) (($user->permissions ?? [])[$area] ?? []);

        // Any right on an area includes seeing it.
        return $action === 'view' ? $granted !== [] : in_array($action, $granted, true);
    }

    public static function current(string $area, string $action = 'view'): bool
    {
        return self::can(auth()->user(), $area, $action);
    }
}
