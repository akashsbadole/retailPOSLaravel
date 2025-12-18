<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update existing orders to include the required fields with default values
        Schema::table('orders', function (Blueprint $table) {
            // Add NOT NULL constraints with default values for existing records
            // This ensures all existing records have the required fields
            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 8, 2)->default(0.00)->after('total_price')->nullable(false);
            }
            
            if (!Schema::hasColumn('orders', 'status')) {
                $table->string('status')->default('pending')->after('discount_amount')->nullable(false);
            }
            
            // Change existing nullable discount_price to have a default if it exists
            if (Schema::hasColumn('orders', 'discount_price')) {
                // First update existing NULL values
                \Illuminate\Support\Facades\DB::table('orders')->whereNull('discount_price')->update(['discount_price' => 0.00]);
                // Then change the column
                $table->decimal('discount_price', 8, 2)->default(0.00)->change();
            }
        });

        // Update any existing records that might have NULL values
        \Illuminate\Support\Facades\DB::table('orders')->update([
            'discount_amount' => 0.00,
            'status' => 'pending'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'status']);
        });
    }
};
