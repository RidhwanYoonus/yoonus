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
        'reports' => ['Reports & dashboard figures', 'Sales'],
        'gold_rates' => ['Gold rates (update / fetch rates)', 'Gold rates'],
        'products' => ['Products', 'Catalogue'],
        'categories' => ['Categories', 'Catalogue'],
        'campaigns' => ['Campaigns', 'Offers'],
        'coupons' => ['Coupons', 'Offers'],
        'gift_cards' => ['Gift cards', 'Offers'],
        'customers' => ['Customers', 'Customers'],
        'reviews' => ['Review moderation', 'Customers'],
        'video_calls' => ['Live video calls', 'Customers'],
        'affiliates' => ['Affiliates, commission report & collections', 'Affiliates'],
        'newsletter' => ['Newsletter', 'Marketing'],
        'gold_alerts' => ['Gold price alerts', 'Marketing'],
        'messages' => ['Messages sent', 'Marketing'],
        'redirects' => ['301 redirects', 'Marketing'],
        'branches' => ['Branches & pickup', 'Settings'],
        'shipping_zones' => ['Delivery zones', 'Settings'],
        'vat' => ['VAT', 'Settings'],
        'settings' => ['Settings page (payments, messaging, store details)', 'Settings'],
    ];

    /** Ready-made permission sets for common jobs. */
    public const PRESETS = [
        'sales' => ['Sales / shop floor', ['orders' => ['view', 'edit'], 'pending_carts' => ['view'], 'customers' => ['view'], 'video_calls' => ['view', 'edit'], 'products' => ['view'], 'gold_rates' => ['view']]],
        'delivery' => ['Delivery / pickup counter', ['orders' => ['view', 'edit']]],
        'catalogue' => ['Catalogue manager', ['products' => ['view', 'create', 'edit', 'delete'], 'categories' => ['view', 'create', 'edit', 'delete'], 'gold_rates' => ['view', 'edit'], 'reviews' => ['view', 'edit', 'delete']]],
        'marketing' => ['Marketing', ['campaigns' => ['view', 'create', 'edit', 'delete'], 'coupons' => ['view', 'create', 'edit', 'delete'], 'gift_cards' => ['view', 'create', 'edit'], 'newsletter' => ['view'], 'gold_alerts' => ['view'], 'messages' => ['view'], 'pending_carts' => ['view'], 'reports' => ['view']]],
        'accounts' => ['Accounts', ['orders' => ['view'], 'reports' => ['view'], 'customers' => ['view'], 'gift_cards' => ['view']]],
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
