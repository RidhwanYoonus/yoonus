<?php

namespace App\Console\Commands;

use App\Support\Settings;
use Illuminate\Console\Command;

/** Recovery switch when nobody can receive the admin sign-in code: php artisan shop:two-factor off */
class TwoFactorSwitch extends Command
{
    protected $signature = 'shop:two-factor {state : on or off}';

    protected $description = 'Turn admin two-step sign-in on or off';

    public function handle(): int
    {
        $on = strtolower($this->argument('state')) === 'on';
        Settings::set(['admin_two_factor' => $on]);
        $this->info('Admin two-step sign-in is now '.($on ? 'ON' : 'OFF').'.');

        return self::SUCCESS;
    }
}
