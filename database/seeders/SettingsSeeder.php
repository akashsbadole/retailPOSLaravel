<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'currency_symbol', 'value' => '$'],
            ['key' => 'site_description', 'value' => 'This is an awesome site!'],
            ['key' => 'site_email', 'value' => 'admin@example.com'],
            ['key' => 'site_name', 'value' => 'My Laravel App'],
            // Tax Configuration Settings
            ['key' => 'tax_enabled', 'value' => '1'],
            ['key' => 'default_tax_rate', 'value' => '5.00'],
            ['key' => 'tax_name', 'value' => 'GST'],
            ['key' => 'tax_number', 'value' => 'GSTIN1234567890'],
            ['key' => 'company_name', 'value' => 'SuperMart Store'],
            ['key' => 'company_address', 'value' => '123 Main Street, City, State 12345'],
            // Payment Method Settings
            ['key' => 'payment_cash_enabled', 'value' => '1'],
            ['key' => 'payment_card_enabled', 'value' => '1'],
            ['key' => 'payment_upi_enabled', 'value' => '1'],
            // Inventory Settings
            ['key' => 'low_stock_alerts_enabled', 'value' => '1'],
            ['key' => 'default_minimum_stock', 'value' => '10'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }
}
