<?php

namespace App\Filament\Actions;

use App\Filament\Resources\ProductResource;

class ProductExportAction extends BaseExportAction
{
    protected static string $resourceClass = ProductResource::class;
    
    protected static string $exportClass = \App\Exports\ProductExport::class;
    
    protected static array $availableColumns = [
        'id' => 'ID',
        'name' => 'Name',
        'barcode' => 'Barcode',
        'sku' => 'SKU',
        'price' => 'Selling Price',
        'cost_price' => 'Cost Price',
        'quantity' => 'Quantity',
        'minimum_stock' => 'Minimum Stock',
        'tax' => 'Tax (%)',
        'status' => 'Status',
        'image' => 'Image URL',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ];
    
    protected static array $exportOptions = [
        'store_filter' => true,
        'date_range' => true,
        'column_selection' => true,
        'relations' => false,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Export Products')
            ->modalHeading('Export Products')
            ->modalDescription('Export products to CSV, Excel, or JSON format.')
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
}