<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Migrations shipped with an update that have not been run on this database yet.
 * The admin shows a banner (with a one-click fix) so a forgotten `artisan migrate`
 * can't silently break saving or checkout.
 */
class PendingMigrations
{
    public static function list(): array
    {
        try {
            $migrator = app('migrator');
            if (! Schema::hasTable('migrations')) {
                return [];
            }
            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

            return array_values(array_diff($files, $migrator->getRepository()->getRan()));
        } catch (\Throwable) {
            return [];
        }
    }
}
