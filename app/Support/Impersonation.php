<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "log in as" an affiliate or customer, to see exactly what they see. Quiet: their last-login time,
 * activity log and security log entries are not touched; only the security log notes which admin did it.
 * Orders can't be placed while looking.
 */
class Impersonation
{
    public const KEY = 'impersonator_id';

    public static function allowed(?User $admin, User $target): bool
    {
        return (bool) $admin?->is_admin && ! $target->is_admin && ! $target->is_staff && $admin->id !== $target->id;
    }

    public static function start(User $admin, User $target): void
    {
        abort_unless(self::allowed($admin, $target), 403);
        SecurityLog::record('impersonate', $admin, details: ['as' => $target->email]);
        session()->put(self::KEY, $admin->id);
        Auth::guard('web')->login($target);
    }

    public static function active(): bool
    {
        return session()->has(self::KEY);
    }

    public static function admin(): ?User
    {
        return self::active() ? User::find(session(self::KEY)) : null;
    }

    /** Back to the admin's own login. */
    public static function stop(): ?User
    {
        $admin = self::admin();
        if ($admin) {
            session()->put('impersonation_leaving', true);
            Auth::guard('web')->login($admin);
        }
        session()->forget([self::KEY, 'impersonation_leaving']);

        return $admin;
    }

    /** Sign-in events caused by starting or ending a look-in are not real sign-ins. */
    public static function quiet(): bool
    {
        return rescue(fn () => session()->has(self::KEY) || session()->has('impersonation_leaving'), false, false);
    }
}
