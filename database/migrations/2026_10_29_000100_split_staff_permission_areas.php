<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff & permissions got finer areas. Existing staff keep what they could do:
 * affiliates → commissions / collections / boosters / applications, products → certificate labs,
 * orders → store visits, and Print / Export stays on for everyone who had any area.
 */
return new class extends Migration
{
    private const COPY = [
        'affiliates' => ['affiliate_commissions', 'affiliate_payouts', 'affiliate_campaigns', 'affiliate_applications'],
        'products' => ['certificate_labs'],
        'orders' => ['store_visits'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'permissions')) {
            return;
        }
        DB::table('users')->where('is_staff', true)->whereNotNull('permissions')->orderBy('id')->each(function ($u) {
            $perms = json_decode((string) $u->permissions, true) ?: [];
            $before = $perms;
            foreach (self::COPY as $from => $targets) {
                foreach ($targets as $to) {
                    if (! empty($perms[$from]) && ! array_key_exists($to, $perms)) {
                        $perms[$to] = $perms[$from];
                    }
                }
            }
            if (array_filter($perms) && ! array_key_exists('export', $perms)) {
                $perms['export'] = ['view'];
            }
            if ($perms !== $before) {
                DB::table('users')->where('id', $u->id)->update(['permissions' => json_encode($perms)]);
            }
        });
    }

    public function down(): void
    {
        // The new areas are simply ignored by older code.
    }
};
