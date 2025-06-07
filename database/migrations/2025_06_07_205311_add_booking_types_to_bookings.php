<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('booking_type', ['free', 'premium'])->default('free')->after('status');
            $table->date('booking_week_start')->nullable()->after('booking_type');
            $table->index(['tenant_id', 'booking_week_start']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['booking_type', 'booking_week_start']);
        });
    }
};
