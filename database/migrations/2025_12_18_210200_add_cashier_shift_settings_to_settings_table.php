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
        // Add cashier shift settings to existing settings table
        \DB::table('settings')->insert([
            [
                'key' => 'require_shift_opening',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'maximum_shift_duration',
                'value' => '480',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'cash_discrepancy_tolerance',
                'value' => '50.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_close_shifts',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'shift_opening_instructions',
                'value' => 'Count the cash in the drawer and enter the opening amount. Verify all payment methods are working before starting sales.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'shift_closing_instructions',
                'value' => 'Count all cash in the drawer. Enter the actual cash amount. Any discrepancies should be noted in the shift notes.',
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
                'require_shift_opening',
                'maximum_shift_duration',
                'cash_discrepancy_tolerance',
                'auto_close_shifts',
                'shift_opening_instructions',
                'shift_closing_instructions',
            ])
            ->delete();
    }
};