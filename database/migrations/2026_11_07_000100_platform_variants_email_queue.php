<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video calls: the customer's preferred app. Products: sizes and gold colours to choose from (kept on the
 * order line). Emails over the daily limit wait in a queue and go out the next day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('video_call_requests', 'platform')) {
            Schema::table('video_call_requests', fn (Blueprint $t) => $t->string('platform', 20)->nullable());
        }
        foreach (['sizes', 'gold_colours'] as $c) {
            if (! Schema::hasColumn('products', $c)) {
                Schema::table('products', fn (Blueprint $t) => $t->json($c)->nullable());
            }
        }
        if (! Schema::hasColumn('order_items', 'options')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->json('options')->nullable());
        }
        if (! Schema::hasTable('queued_emails')) {
            Schema::create('queued_emails', function (Blueprint $t) {
                $t->id();
                $t->string('to');
                $t->string('template', 64);
                $t->string('subject')->nullable();
                $t->longText('payload');   // the serialized email
                $t->timestamp('sent_at')->nullable();
                $t->string('error', 500)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('queued_emails');
        if (Schema::hasColumn('order_items', 'options')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('options'));
        }
        foreach (['sizes', 'gold_colours'] as $c) {
            if (Schema::hasColumn('products', $c)) {
                Schema::table('products', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        if (Schema::hasColumn('video_call_requests', 'platform')) {
            Schema::table('video_call_requests', fn (Blueprint $t) => $t->dropColumn('platform'));
        }
    }
};
