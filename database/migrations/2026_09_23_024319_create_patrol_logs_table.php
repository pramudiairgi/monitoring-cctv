<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status'); // 'success', 'partial', 'failed', 'no_change'
            $table->integer('total_cameras');
            $table->integer('online_count');
            $table->integer('offline_count');
            $table->integer('status_changed_count')->nullable();
            $table->integer('patrol_online_count')->nullable(); // PATROLI category online
            $table->integer('patrol_offline_count')->nullable(); // PATROLI category offline
            $table->text('details')->nullable(); // JSON: per-camera status
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_logs');
    }
};
