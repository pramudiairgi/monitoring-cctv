<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('operator')->after('password');
        });

        // Backfill: every row present at this point predates roles, so all
        // existing users become admins. Both branches are needed because
        // drivers differ — some backfill the new column with the default
        // ('operator'), others with NULL.
        DB::table('users')->whereNull('role')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'operator')->update(['role' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
