<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('sn', 50)->unique();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn', 50)->index();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('command');
            $table->string('status', 20)->default('pending')->index();
            $table->string('return_code', 20)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_commands');
        Schema::dropIfExists('devices');
    }
};
