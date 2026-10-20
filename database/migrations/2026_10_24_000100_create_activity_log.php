<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_log')) {
            return;
        }
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 40)->index();          // browser cookie: links a guest's visits, before and after sign-in
            $table->foreignId('user_id')->nullable()->index();
            $table->string('email', 191)->nullable()->index();  // signed-in user's, or what a guest typed at checkout / sign-in
            $table->string('event', 32)->index();
            $table->string('subject', 191)->nullable();          // product, order number, search words…
            $table->string('method', 8)->default('GET');
            $table->string('path', 255);
            $table->string('referrer', 255)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->string('device', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('is_staff')->default(false)->index();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
