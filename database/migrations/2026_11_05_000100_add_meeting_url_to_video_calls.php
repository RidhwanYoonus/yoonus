<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A meeting link staff send for one call (e.g. a new Google Meet or Zoom link). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('video_call_requests', 'meeting_url')) {
            Schema::table('video_call_requests', fn (Blueprint $t) => $t->string('meeting_url', 500)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('video_call_requests', 'meeting_url')) {
            Schema::table('video_call_requests', fn (Blueprint $t) => $t->dropColumn('meeting_url'));
        }
    }
};
