<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of identity captured at checkout (saved on the account if the customer wants), and the
 * one-time handover code the customer gives our staff when the order is delivered or collected.
 * ID numbers are stored encrypted; ID photos live on the private disk.
 */
return new class extends Migration
{
    private const ID_COLUMNS = ['id_type', 'id_number', 'id_last4', 'id_expiry', 'id_front_path', 'id_back_path'];

    public function up(): void
    {
        foreach (['users', 'orders'] as $table) {
            if (! Schema::hasColumn($table, 'id_type')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->string('id_type', 24)->nullable();
                    $t->text('id_number')->nullable();
                    $t->string('id_last4', 8)->nullable();
                    $t->date('id_expiry')->nullable();
                    $t->string('id_front_path')->nullable();
                    $t->string('id_back_path')->nullable();
                    if ($table === 'users') {
                        $t->timestamp('id_saved_at')->nullable();
                    }
                });
            }
        }
        if (! Schema::hasColumn('orders', 'handover_otp_hash')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('handover_otp_hash')->nullable();
                $t->timestamp('handover_otp_sent_at')->nullable();
                $t->unsignedTinyInteger('handover_otp_attempts')->default(0);
                $t->string('handover_verified_via', 16)->nullable();
                $t->timestamp('handover_verified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn([...self::ID_COLUMNS, 'handover_otp_hash', 'handover_otp_sent_at', 'handover_otp_attempts', 'handover_verified_via', 'handover_verified_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([...self::ID_COLUMNS, 'id_saved_at']));
    }
};
