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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')->nullable()->after('customer_id');
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->onDelete('set null');
            $table->index('cashier_shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cashier_shift_id']);
            $table->dropIndex(['cashier_shift_id']);
            $table->dropColumn('cashier_shift_id');
        });
    }
};