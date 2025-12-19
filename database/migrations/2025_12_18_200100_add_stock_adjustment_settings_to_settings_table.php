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
        // Add stock adjustment settings to the existing settings table
        \DB::table('settings')->insert([
            [
                'key' => 'stock_adjustment_require_approval',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'stock_adjustment_max_amount',
                'value' => '1000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'stock_adjustment_enable_damage',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'stock_adjustment_enable_theft',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'stock_adjustment_enable_manual_correction',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'stock_adjustment_enable_other',
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
        \DB::table('settings')
            ->whereIn('key', [
                'stock_adjustment_require_approval',
                'stock_adjustment_max_amount',
                'stock_adjustment_enable_damage',
                'stock_adjustment_enable_theft',
                'stock_adjustment_enable_manual_correction',
                'stock_adjustment_enable_other',
            ])
            ->delete();
    }
};