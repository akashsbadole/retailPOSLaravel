<?php

namespace App\Filament\Actions;

use App\Filament\Resources\OrderResource;

class OrderExportAction extends BaseExportAction
{
    protected static string $resourceClass = OrderResource::class;
    
    protected static string $exportClass = \App\Exports\OrderExport::class;
    
    protected static array $availableColumns = [
        'id' => 'ID',
        'customer_name' => 'Customer Name',
        'customer_email' => 'Customer Email',
        'customer_phone' => 'Customer Phone',
        'total_price' => 'Total Price',
        'discount_amount' => 'Discount Amount',
        'final_amount' => 'Final Amount',
        'status' => 'Status',
        'items_count' => 'Items Count',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ];
    
    protected static array $exportOptions = [
        'store_filter' => true,
        'date_range' => true,
        'column_selection' => true,
        'relations' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Export Orders')
            ->modalHeading('Export Orders')
            ->modalDescription('Export orders to CSV, Excel, or JSON format.')
            ->modalWidth('2xl');
    }
    
    protected static function supportsStores(): bool
    {
        return true;
    }
    
    protected static function supportsDateRange(): bool
    {
        return true;
    }
    
    protected static function supportsRelations(): bool
    {
        return true;
    }
}