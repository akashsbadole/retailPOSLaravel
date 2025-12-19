<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert return settings as key-value pairs
        DB::table('settings')->insert([
            [
                'key' => 'return_require_approval',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'return_time_limit_days',
                'value' => '30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'return_restocking_fee_percentage',
                'value' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'return_reasons',
                'value' => json_encode([
                    'damaged' => 'Damaged',
                    'defective' => 'Defective',
                    'wrong_item' => 'Wrong Item',
                    'not_needed' => 'Not Needed',
                    'customer_dissatisfaction' => 'Customer Dissatisfaction',
                    'expired' => 'Expired',
                    'other' => 'Other'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'return_auto_stock_adjustment',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'return_require_approval',
            'return_time_limit_days',
            'return_restocking_fee_percentage',
            'return_reasons',
            'return_auto_stock_adjustment'
        ])->delete();
    }
};