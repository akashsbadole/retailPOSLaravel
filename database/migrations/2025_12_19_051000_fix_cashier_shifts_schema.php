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
        Schema::table('cashier_shifts', function (Blueprint $table) {
            // Add missing fields if they don't exist
            if (!Schema::hasColumn('cashier_shifts', 'opening_time')) {
                $table->timestamp('opening_time')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('cashier_shifts', 'closing_time')) {
                $table->timestamp('closing_time')->nullable()->after('opening_time');
            }
            
            // Add additional fields for better shift management
            if (!Schema::hasColumn('cashier_shifts', 'expected_cash')) {
                $table->decimal('expected_cash', 10, 2)->default(0)->after('closing_cash');
            }
            
            if (!Schema::hasColumn('cashier_shifts', 'cash_discrepancy')) {
                $table->decimal('cash_discrepancy', 10, 2)->default(0)->after('expected_cash');
            }
            
            if (!Schema::hasColumn('cashier_shifts', 'total_orders')) {
                $table->integer('total_orders')->default(0)->after('upi_sales');
            }
            
            if (!Schema::hasColumn('cashier_shifts', 'opened_by')) {
                $table->foreignId('opened_by')->nullable()->after('user_id')->constrained('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('cashier_shifts', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('opened_by')->constrained('users')->onDelete('set null');
            }
            
            // Add indexes for better performance
            $table->index(['status', 'start_time']);
            $table->index(['user_id', 'start_time']);
            $table->index('created_at');
        });
        
        // Update existing records to maintain data consistency
        \DB::statement('
            UPDATE cashier_shifts 
            SET opening_time = start_time, 
                closing_time = end_time,
                opened_by = user_id,
                closed_by = user_id
            WHERE opening_time IS NULL AND start_time IS NOT NULL
        ');
        
        // Update total orders count
        \DB::statement('
            UPDATE cashier_shifts cs 
            SET total_orders = (
                SELECT COUNT(*) 
                FROM orders o 
                WHERE o.cashier_shift_id = cs.id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropIndex(['status', 'start_time']);
            $table->dropIndex(['user_id', 'start_time']);
            $table->dropIndex('created_at');
            
            if (Schema::hasColumn('cashier_shifts', 'closed_by')) {
                $table->dropForeign(['closed_by']);
                $table->dropColumn('closed_by');
            }
            
            if (Schema::hasColumn('cashier_shifts', 'opened_by')) {
                $table->dropForeign(['opened_by']);
                $table->dropColumn('opened_by');
            }
            
            if (Schema::hasColumn('cashier_shifts', 'total_orders')) {
                $table->dropColumn('total_orders');
            }
            
            if (Schema::hasColumn('cashier_shifts', 'cash_discrepancy')) {
                $table->dropColumn('cash_discrepancy');
            }
            
            if (Schema::hasColumn('cashier_shifts', 'expected_cash')) {
                $table->dropColumn('expected_cash');
            }
            
            if (Schema::hasColumn('cashier_shifts', 'closing_time')) {
                $table->dropColumn('closing_time');
            }
            
            if (Schema::hasColumn('cashier_shifts', 'opening_time')) {
                $table->dropColumn('opening_time');
            }
        });
    }
};