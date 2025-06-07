<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');

            $table->foreignId('tenant_id')->after('id')->constrained()->onDelete('cascade');
            $table->string('booking_reference')->nullable()->after('notes');
            $table->decimal('light_surcharge', 10, 2)->default(0)->after('price');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['tenant_id', 'booking_reference', 'light_surcharge', 'approved_by', 'approved_at']);

            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed')->change();
        });
    }
};
