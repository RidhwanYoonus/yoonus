<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment('local', 'testing') || $this->command?->confirm('Load demo products and an admin login?', false)) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
