<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Maps location for each pickup branch, a per-product "live video call with staff" switch,
 * and the queue of video-call requests staff answer from the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'map_url')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('map_url', 500)->nullable()->after('opening_hours');
                $table->decimal('latitude', 10, 7)->nullable()->after('map_url');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }
        if (! Schema::hasColumn('products', 'video_call_enabled')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('video_call_enabled')->default(true)->after('is_featured');
            });
        }
        if (! Schema::hasTable('video_call_requests')) {
            Schema::create('video_call_requests', function (Blueprint $table) {
                $table->id();
                $table->string('token', 40)->unique();
                $table->string('room', 80);
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('phone', 32);
                $table->string('locale', 5)->default('en');
                $table->string('status', 16)->default('waiting');
                $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_call_requests');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('video_call_enabled'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['map_url', 'latitude', 'longitude']));
    }
};
