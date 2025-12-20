<?php

namespace App\Filament\Actions;

use App\Filament\Resources\PurchaseResource;

class PurchaseExportAction extends BaseExportAction
{
    protected static string $resourceClass = PurchaseResource::class;
    
    protected static string $exportClass = \App\Exports\PurchaseExport::class;
    
    protected static array $availableColumns = [
        'id' => 'ID',
        'supplier_name' => 'Supplier Name',
        'supplier_email' => 'Supplier Email',
        'supplier_phone' => 'Supplier Phone',
        'purchase_date' => 'Purchase Date',
        'total_amount' => 'Total Amount',
        'discount_amount' => 'Discount Amount',
        'tax_amount' => 'Tax Amount',
        'final_amount' => 'Final Amount',
        'status' => 'Status',
        'payment_status' => 'Payment Status',
        'items_count' => 'Items Count',
        'total_paid' => 'Total Paid',
        'remaining_amount' => 'Remaining Amount',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ];
    
    protected static array $exportOptions = [
        'store_filter' => false,
        'date_range' => true,
        'column_selection' => true,
        'relations' => true,
    ];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Export Purchases')
            ->modalHeading('Export Purchases')
            ->modalDescription('Export purchases to CSV, Excel, or JSON format.')
            ->modalWidth('2xl');
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