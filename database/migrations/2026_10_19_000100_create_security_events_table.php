<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Security log (sign-ins, wrong passwords, lockouts, two-step codes) and proof that the customer accepted the terms. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('security_events')) {
            Schema::create('security_events', function (Blueprint $table) {
                $table->id();
                $table->string('event', 40)->index();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('email')->nullable();
                $table->string('ip', 45)->nullable()->index();
                $table->string('user_agent', 255)->nullable();
                $table->json('details')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('orders', 'terms_accepted_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('terms_accepted_at')->nullable();
                $table->string('terms_accepted_ip', 45)->nullable();
                $table->string('terms_version', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        if (Schema::hasColumn('orders', 'terms_accepted_at')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['terms_accepted_at', 'terms_accepted_ip', 'terms_version']));
        }
    }
};
