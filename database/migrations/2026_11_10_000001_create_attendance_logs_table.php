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
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn', 50);
            $table->string('pin', 50)->index();
            $table->dateTime('punched_at')->index();
            $table->unsignedTinyInteger('status')->nullable();
            $table->unsignedTinyInteger('verify_type')->nullable();
            $table->timestamps();

            $table->unique(['device_sn', 'pin', 'punched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
