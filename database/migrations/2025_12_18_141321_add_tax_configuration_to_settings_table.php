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
        // Insert default tax configuration settings
        DB::table('settings')->insert([
            [
                'key' => 'tax_enabled',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'default_tax_rate',
                'value' => '0.00',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'tax_name',
                'value' => 'GST',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'tax_number',
                'value' => '',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_name',
                'value' => '',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'company_address',
                'value' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove tax configuration settings
        DB::table('settings')->whereIn('key', [
            'tax_enabled',
            'default_tax_rate',
            'tax_name',
            'tax_number',
            'company_name',
            'company_address'
        ])->delete();
    }
};
