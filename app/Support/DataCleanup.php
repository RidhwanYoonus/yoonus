<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Settings → Delete data: wipes chosen groups of shop data (e.g. test orders before going live).
 * Products, gold rates, settings, branches and staff logins are never touched.
 */
class DataCleanup
{
    /** key => [label, explanation, tables (children first)] */
    public const GROUPS = [
        'orders' => ['Orders & payments', 'All orders with their items, payments, status history, price changes, coupon / gift-card use, loyalty points from orders, affiliate commissions on them and the customers\' ID photos saved with orders. Invoice numbers keep counting (VAT rule).',
            ['affiliate_commissions', 'coupon_redemptions', 'gift_card_transactions', 'payments', 'order_price_overrides', 'order_status_history', 'order_items', 'referrals', 'loyalty_ledger', 'orders']],
        'carts' => ['Bags & pending carts', 'Shopping bags (pending carts) and wishlists.', ['carts', 'wishlists']],
        'affiliates' => ['Affiliate data', 'Affiliates, their commissions, collections, adjustments, clicks, price-lock links, applications, boosters / targets and their coupon codes. Their login accounts stay (as customers).',
            ['affiliate_commissions', 'affiliate_payouts', 'affiliate_adjustments', 'affiliate_clicks', 'affiliate_price_locks', 'affiliate_applications', 'affiliate_campaigns', 'affiliates']],
        'customers' => ['Customer accounts', 'Customer logins (not admin or staff) with their addresses, wishlists, reviews, loyalty points and ID photos. Their past orders stay, without the account link.',
            ['addresses', 'wishlists', 'loyalty_ledger', 'reviews', 'activity_log']],
        'enquiries' => ['Enquiries', 'Video-call requests, store-visit bookings, gold price alerts and newsletter sign-ups.',
            ['video_call_requests', 'store_visits', 'gold_price_alerts', 'newsletter_subscribers']],
        'logs' => ['Logs', 'Messages sent log, security log, visitor activity log and price recalculation log.', ['notification_log', 'security_events', 'activity_log', 'price_recalc_log']],
        'images' => ['Unused images', 'Photo files no product, review, campaign, lab or certificate uses any more (e.g. photos of deleted products). Images in use are kept.', []],
    ];

    /** @param array<string> $groups @return array<string,int> what was removed, e.g. ['orders' => 12, 'image files' => 30] */
    public static function run(array $groups, ?User $by = null): array
    {
        $done = [];
        $groups = array_values(array_intersect(array_keys(self::GROUPS), $groups));

        // Files first, while the rows that point at them still exist.
        $files = [];
        if (in_array('orders', $groups, true)) {
            $files = [...$files, ...self::column('orders', ['id_front_path', 'id_back_path'])];
        }
        if (in_array('customers', $groups, true)) {
            $files = [...$files, ...self::column('users', ['id_front_path', 'id_back_path'], fn ($q) => self::customers($q))];
        }

        DB::transaction(function () use ($groups, &$done) {
            foreach ($groups as $group) {
                [$label, , $tables] = self::GROUPS[$group];
                $count = 0;
                if ($group === 'affiliates' && Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'affiliate_id')) {
                    $count += DB::table('coupons')->whereNotNull('affiliate_id')->delete();
                }
                if ($group === 'customers') {
                    $ids = self::customers(DB::table('users'))->pluck('id');
                    foreach ($tables as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                            DB::table($table)->whereIn('user_id', $ids)->delete();
                        }
                    }
                    foreach (['orders', 'carts', 'gold_price_alerts', 'video_call_requests', 'store_visits', 'security_events', 'coupon_redemptions'] as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                            DB::table($table)->whereIn('user_id', $ids)->update(['user_id' => null]);
                        }
                    }
                    if (Schema::hasTable('affiliates')) {
                        $ids = $ids->diff(DB::table('affiliates')->pluck('user_id'));   // affiliates keep their login
                    }
                    DB::table('sessions')->whereIn('user_id', $ids)->delete();
                    $count += DB::table('users')->whereIn('id', $ids)->delete();
                    $done[$label] = $count;

                    continue;
                }
                foreach ($tables as $table) {
                    if (Schema::hasTable($table)) {
                        $rows = DB::table($table)->delete();
                        $count += in_array($table, ['orders', 'carts', 'affiliates', 'notification_log', 'security_events', 'activity_log', 'video_call_requests', 'store_visits', 'gold_price_alerts', 'newsletter_subscribers', 'price_recalc_log', 'wishlists'], true) ? $rows : 0;
                    }
                }
                if ($group === 'orders') {
                    // Sold pieces come back in the shop only when staff add stock again; just clear the "hidden after sale" flag.
                    if (Schema::hasColumn('products', 'hidden_after_sale')) {
                        DB::table('products')->where('hidden_after_sale', true)->update(['hidden_after_sale' => false]);
                    }
                }
                $done[$label] = $count;
            }
        });

        foreach (array_filter(array_unique($files)) as $path) {
            Storage::disk(IdDocument::DISK)->delete($path);
        }
        if (in_array('images', $groups, true)) {
            $done['Unused image files'] = self::deleteUnusedImages();
        }
        \Illuminate\Support\Facades\Cache::flush();
        SecurityLog::record('data_deleted', $by, details: ['groups' => $groups, 'removed' => $done]);

        return $done;
    }

    /** Files in the public folders that nothing points at. */
    public static function unusedImages(): array
    {
        $used = collect();
        foreach (DB::table('products')->pluck('images') as $json) {
            $used = $used->merge((array) json_decode((string) $json, true));
        }
        $used = $used->merge(self::column('products', ['certificate_file']))
            ->merge(self::column('categories', ['image']))
            ->merge(self::column('certificate_labs', ['logo']))
            ->merge(self::column('campaigns', ['banner_image']))
            ->merge(self::column('settings', ['value'], fn ($q) => $q->where('value', 'like', '%/%.%')));
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'photos')) {
            foreach (DB::table('reviews')->pluck('photos') as $json) {
                $used = $used->merge((array) json_decode((string) $json, true));
            }
        }
        $used = $used->filter()->map(fn ($p) => ltrim(preg_replace('#^.*/storage/#', '', (string) $p), '/'))->flip();

        $disk = Storage::disk('public');

        return collect(['products', 'categories', 'reviews', 'labs', 'certificates', 'campaigns'])
            ->flatMap(fn ($dir) => $disk->exists($dir) ? $disk->allFiles($dir) : [])
            ->reject(fn ($f) => isset($used[$f]) || str_starts_with(basename($f), '.'))
            ->values()->all();
    }

    private static function deleteUnusedImages(): int
    {
        $files = self::unusedImages();
        Storage::disk('public')->delete($files);

        return count($files);
    }

    /** Row counts per group, shown before deleting. */
    public static function counts(): array
    {
        $n = fn (string $t, ?\Closure $q = null) => Schema::hasTable($t) ? ($q ? $q(DB::table($t)) : DB::table($t))->count() : 0;

        return [
            'orders' => $n('orders'), 'carts' => $n('carts'), 'affiliates' => $n('affiliates'),
            'customers' => self::customers(DB::table('users'))->count(),
            'enquiries' => $n('video_call_requests') + $n('store_visits') + $n('gold_price_alerts') + $n('newsletter_subscribers'),
            'logs' => $n('notification_log') + $n('security_events') + $n('activity_log') + $n('price_recalc_log'),
            'images' => count(self::unusedImages()),
        ];
    }

    private static function customers($query)
    {
        return $query->where('is_admin', false)->when(Schema::hasColumn('users', 'is_staff'), fn ($q) => $q->where('is_staff', false));
    }

    private static function column(string $table, array $columns, ?\Closure $scope = null): array
    {
        $columns = array_values(array_filter($columns, fn ($c) => Schema::hasTable($table) && Schema::hasColumn($table, $c)));
        if (! $columns) {
            return [];
        }
        $q = DB::table($table);
        $scope && $scope($q);

        return $q->get($columns)->flatMap(fn ($r) => array_values((array) $r))->filter()->map(fn ($v) => (string) $v)->all();
    }
}
