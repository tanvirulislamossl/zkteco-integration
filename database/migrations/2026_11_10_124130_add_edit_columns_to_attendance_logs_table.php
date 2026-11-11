<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the device's original time so punches can be edited here, while
     * the device re-sending the same punch is still recognised as a duplicate.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dateTime('original_punched_at')->nullable()->after('punched_at');
            $table->unsignedTinyInteger('original_status')->nullable()->after('status');
            $table->timestamp('edited_at')->nullable()->after('verify_type');
        });

        DB::table('attendance_logs')->update([
            'original_punched_at' => DB::raw('punched_at'),
            'original_status' => DB::raw('status'),
        ]);

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique(['device_sn', 'pin', 'punched_at']);
            $table->unique(['device_sn', 'pin', 'original_punched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendance_logs')->whereNotNull('original_punched_at')->update([
            'punched_at' => DB::raw('original_punched_at'),
            'status' => DB::raw('original_status'),
        ]);

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique(['device_sn', 'pin', 'original_punched_at']);
            $table->unique(['device_sn', 'pin', 'punched_at']);
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['original_punched_at', 'original_status', 'edited_at']);
        });
    }
};
