<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_telemetry', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('camera_id')->nullable();
            $table->string('camera_name')->nullable();
            $table->integer('bitrate_kbps')->nullable();
            $table->string('resolution')->nullable();
            $table->float('buffer_health')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('event_type');
            $table->text('error_message')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_telemetry');
    }
};
