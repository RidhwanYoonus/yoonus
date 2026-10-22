<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PendingMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseUpdateBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_pending_update_and_can_run_it_with_one_click(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assertSame([], PendingMigrations::list());

        Artisan::call('migrate:rollback', ['--step' => 9, '--force' => true]); // the newest migrations
        $this->assertFalse(Schema::hasTable('stone_types'));
        $this->assertFalse(Schema::hasColumn('products', 'created_by'));
        $this->assertFalse(Schema::hasColumn('orders', 'tax_free_requested'));
        $this->assertFalse(Schema::hasColumn('products', 'meta_title_ar'));
        $this->assertFalse(Schema::hasTable('activity_log'));
        $this->assertFalse(Schema::hasColumn('products', 'diamond_origin'));
        $this->assertFalse(Schema::hasTable('certificate_labs'));
        $this->assertNotEmpty(PendingMigrations::list());

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Update database now');

        $this->actingAs($admin)->post('/admin/update-database')->assertRedirect();
        $this->assertTrue(Schema::hasColumn('products', 'updated_by'));
        $this->assertTrue(Schema::hasColumn('users', 'id_country'));
        $this->assertTrue(Schema::hasColumn('categories', 'meta_description_ar'));
        $this->assertTrue(Schema::hasTable('activity_log'));
        $this->assertTrue(Schema::hasColumn('products', 'diamond_origin'));
        $this->assertTrue(Schema::hasTable('certificate_labs') && Schema::hasColumn('products', 'certificate_lab_id'));
        $this->assertSame([], PendingMigrations::list());
        $this->actingAs($admin)->get('/admin')->assertDontSee('Update database now');
    }

    public function test_customers_cannot_run_database_update(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->post('/admin/update-database')->assertForbidden();
    }

    public function test_fixed_pricing_migration_can_rerun_on_a_half_updated_database(): void
    {
        $m = require database_path('migrations/2026_10_18_000100_add_fixed_pricing_to_products.php');
        $m->up(); // columns already exist: must not fail
        $this->assertTrue(Schema::hasColumn('products', 'pricing_type'));
    }
}
