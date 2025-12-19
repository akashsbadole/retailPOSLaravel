<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Pages\Page;
use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class Settings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.settings';

    public array $settings = [];

    public function mount(): void
    {
        $this->settings = Setting::pluck('value', 'key')->toArray();
        $this->settings['currency_symbol'] = $this->settings['currency_symbol'] ?? '$';
        $this->settings['tax_enabled'] = $this->settings['tax_enabled'] ?? '1';
        $this->settings['default_tax_rate'] = $this->settings['default_tax_rate'] ?? '0.00';
        $this->settings['tax_name'] = $this->settings['tax_name'] ?? 'GST';
        $this->settings['tax_number'] = $this->settings['tax_number'] ?? '';
        $this->settings['company_name'] = $this->settings['company_name'] ?? '';
        $this->settings['company_address'] = $this->settings['company_address'] ?? '';
        $this->settings['payment_cash_enabled'] = $this->settings['payment_cash_enabled'] ?? '1';
        $this->settings['payment_card_enabled'] = $this->settings['payment_card_enabled'] ?? '1';
        $this->settings['payment_upi_enabled'] = $this->settings['payment_upi_enabled'] ?? '1';
        $this->settings['low_stock_alerts_enabled'] = $this->settings['low_stock_alerts_enabled'] ?? '1';
        $this->settings['default_minimum_stock'] = $this->settings['default_minimum_stock'] ?? '10';
        
        // Return settings
        $this->settings['return_require_approval'] = $this->settings['return_require_approval'] ?? '1';
        $this->settings['return_time_limit_days'] = $this->settings['return_time_limit_days'] ?? '30';
        $this->settings['return_restocking_fee_percentage'] = $this->settings['return_restocking_fee_percentage'] ?? '0.00';
        $this->settings['return_auto_stock_adjustment'] = $this->settings['return_auto_stock_adjustment'] ?? '1';
        $this->settings['return_reasons'] = $this->settings['return_reasons'] ?? json_encode([
            'damaged' => 'Damaged',
            'defective' => 'Defective',
            'wrong_item' => 'Wrong Item',
            'not_needed' => 'Not Needed',
            'customer_dissatisfaction' => 'Customer Dissatisfaction',
            'expired' => 'Expired',
            'other' => 'Other',
        ]);
    }

    public function save(): void
    {
        foreach ($this->settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        session()->flash('success', 'Settings updated successfully!');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // General Settings Group
            TextInput::make('settings.site_name')
                ->label('Site Name')
                ->required()
                ->helperText('The name of your store or website'),
            
            TextInput::make('settings.site_email')
                ->label('Admin Email')
                ->email()
                ->helperText('Primary contact email for your store'),
            
            Textarea::make('settings.site_description')
                ->label('Site Description')
                ->helperText('A brief description of your store'),
            
            TextInput::make('settings.currency_symbol')
                ->default('$')
                ->label('Currency Symbol')
                ->helperText('Symbol to display before prices (e.g., $, €, £)'),
            
            // Tax Configuration Group
            Toggle::make('settings.tax_enabled')
                ->label('Enable Tax')
                ->helperText('Enable tax calculation on orders')
                ->default(true),
            
            TextInput::make('settings.default_tax_rate')
                ->label('Default Tax Rate (%)')
                ->numeric()
                ->step(0.01)
                ->suffix('%')
                ->default('0.00')
                ->helperText('Default tax rate for products without specific tax rates'),
            
            TextInput::make('settings.tax_name')
                ->label('Tax Name')
                ->default('GST')
                ->helperText('Name of the tax (e.g., GST, VAT, Sales Tax)'),
            
            TextInput::make('settings.tax_number')
                ->label('Tax Number')
                ->helperText('Your business tax identification number'),
            
            // Payment Methods Group
            Toggle::make('settings.payment_cash_enabled')
                ->label('Enable Cash Payments')
                ->helperText('Allow customers to pay with cash')
                ->default(true),
            
            Toggle::make('settings.payment_card_enabled')
                ->label('Enable Card Payments')
                ->helperText('Allow customers to pay with credit/debit cards')
                ->default(true),
            
            Toggle::make('settings.payment_upi_enabled')
                ->label('Enable UPI Payments')
                ->helperText('Allow customers to pay with UPI')
                ->default(true),
            
            // Inventory Settings Group
            Toggle::make('settings.low_stock_alerts_enabled')
                ->label('Enable Low Stock Alerts')
                ->helperText('Show alerts when products are running low on stock')
                ->default(true),
            
            TextInput::make('settings.default_minimum_stock')
                ->label('Default Minimum Stock')
                ->numeric()
                ->minValue(1)
                ->default(10)
                ->helperText('Default minimum stock level for new products'),
            
            // Return Settings Group
            Toggle::make('settings.return_require_approval')
                ->label('Require Return Approval')
                ->helperText('Require manager approval before processing returns')
                ->default(true),
            
            TextInput::make('settings.return_time_limit_days')
                ->label('Return Time Limit (Days)')
                ->numeric()
                ->minValue(1)
                ->default(30)
                ->helperText('Number of days after purchase when returns are allowed'),
            
            TextInput::make('settings.return_restocking_fee_percentage')
                ->label('Restocking Fee (%)')
                ->numeric()
                ->step(0.01)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->default('0.00')
                ->helperText('Percentage to deduct from refund amount for restocking'),
            
            Toggle::make('settings.return_auto_stock_adjustment')
                ->label('Auto Stock Adjustment')
                ->helperText('Automatically adjust stock when returns are approved')
                ->default(true),
            
            // Company Information Group
            TextInput::make('settings.company_name')
                ->label('Company Name')
                ->helperText('Your company or store name for invoices'),
            
            Textarea::make('settings.company_address')
                ->label('Company Address')
                ->rows(3)
                ->helperText('Your company address for invoices'),
        ]);
    }
}
