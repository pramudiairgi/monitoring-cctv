<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stream_telemetry', function (Blueprint $table) {
            $table->index(['camera_id', 'event_type', 'created_at'], 'idx_telemetry_lookup');
            $table->index('created_at', 'idx_telemetry_created_at');
        });

        Schema::table('cameras', function (Blueprint $table) {
            $table->index('category_id', 'idx_cameras_category_id');
            $table->index('status', 'idx_cameras_status');
        });
    }

    public function down(): void
    {
        Schema::table('stream_telemetry', function (Blueprint $table) {
            $table->dropIndex('idx_telemetry_lookup');
            $table->dropIndex('idx_telemetry_created_at');
        });

        Schema::table('cameras', function (Blueprint $table) {
            $table->dropIndex('idx_cameras_category_id');
            $table->dropIndex('idx_cameras_status');
        });
    }
};
